#!/usr/bin/env bash
# Menú para instalar/actualizar y administrar SiGeRU en otra PC.
set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
INSTALL_DIR="/opt/sigeru"
CONFIG_FILE="/etc/sigeru-installer.conf"
# Copia de seguridad de los secretos, fuera de la carpeta de instalación.
# Permite reinstalar en otra carpeta (o después de borrarla) sin perder el
# acceso a la base de datos que ya existe en el volumen sigeru_mysql_data.
ENV_BACKUP="/etc/sigeru/env.backup"
DB_VOLUME="sigeru_mysql_data"
SIGN_CONTROLLER="/var/www/html/GIT/api/controladores/Controller_Sign.php"

if [[ -r "$CONFIG_FILE" ]]; then
    IFS= read -r saved_dir < "$CONFIG_FILE" || true
    [[ -n "${saved_dir:-}" ]] && INSTALL_DIR="$saved_dir"
fi

fail() { printf '[ERROR] %s\n' "$*" >&2; return 1; }
info() { printf '[INFO] %s\n' "$*"; }
ok() { printf '[OK] %s\n' "$*"; }
pause_menu() { echo; read -r -p 'Presione ENTER para continuar...' _ || true; }

usage() {
    cat <<'HELP'
Uso: bash install.sh [--help]

Sin argumentos abre el menú interactivo para:
  - instalar/actualizar SiGeRU desde un paquete .tar.gz de imágenes;
  - iniciar, detener, reiniciar, consultar estado y ver logs;
  - crear un administrador de prueba (cédula y contraseña);
  - desinstalar los contenedores, conservando los datos por defecto.
    - usar las opciones avanzadas de Docker desde docker-admin-avanzado.sh.

La opción Instalar pide una ruta de paquete. ENTER busca automáticamente
sigeru-docker-*.tar.gz en ../../ respecto a este script.
HELP
}

compose_instalado() {
    [[ -f "$INSTALL_DIR/compose.yaml" && -f "$INSTALL_DIR/.env" ]] || {
        fail "No se encontró una instalación completa en $INSTALL_DIR. Instale SiGeRU primero."
        return 1
    }
    docker compose --project-directory "$INSTALL_DIR" --file "$INSTALL_DIR/compose.yaml" \
        --env-file "$INSTALL_DIR/.env" --project-name sigeru "$@"
}

docker_disponible() {
    command -v docker >/dev/null 2>&1 || { fail "Docker no está instalado."; return 1; }
    docker compose version >/dev/null 2>&1 || { fail "Falta Docker Compose v2."; return 1; }
    docker info >/dev/null 2>&1 || { fail "No se puede conectar al servicio Docker. Ejecute con sudo o revise permisos."; return 1; }
}

instalar_docker() {
    if command -v apt-get >/dev/null 2>&1; then
        apt-get update || return 1
        apt-get install -y docker.io docker-compose-v2 \
            || apt-get install -y docker.io docker-compose-plugin \
            || { fail "No se pudo instalar Docker Compose v2 desde los repositorios."; return 1; }
    elif command -v dnf >/dev/null 2>&1; then
        dnf install -y docker docker-compose-plugin \
            || { fail "No se pudo instalar Docker desde los repositorios."; return 1; }
    else
        fail "Instalación automática no soportada. Instale Docker Engine y Compose v2 manualmente."
        return 1
    fi
    systemctl enable --now docker || { fail "No se pudo iniciar Docker."; return 1; }
}

buscar_paquete() {
    local ruta="$1" carpeta
    if [[ -z "$ruta" ]]; then
        carpeta="$SCRIPT_DIR/../.."
    elif [[ -d "$ruta" ]]; then
        carpeta="$ruta"
    else
        [[ -f "$ruta" ]] || { fail "No existe el archivo indicado: $ruta"; return 1; }
        printf '%s\n' "$ruta"
        return 0
    fi

    local encontrados=()
    mapfile -t encontrados < <(find "$carpeta" -maxdepth 5 -type f -name 'sigeru-docker-*.tar.gz' -print 2>/dev/null | sort)
    if ((${#encontrados[@]} == 1)); then
        info "Paquete encontrado: ${encontrados[0]}" >&2
        printf '%s\n' "${encontrados[0]}"
        return 0
    fi

    if ((${#encontrados[@]} > 1)); then
        printf 'Se encontraron varios paquetes en %s:\n' "$carpeta" >&2
        printf '  %s\n' "${encontrados[@]}" >&2
    else
        printf 'No se encontró ningún paquete en %s.\n' "$carpeta" >&2
    fi
    read -r -p 'Escribe la ruta completa del .tar.gz: ' ruta
    [[ -f "$ruta" ]] || { fail "No existe el archivo indicado: $ruta"; return 1; }
    printf '%s\n' "$ruta"
}

ruta_segura_tar() {
    local archive="$1" entry
    while IFS= read -r entry; do
        [[ "$entry" == sigeru || "$entry" == sigeru/* ]] || { fail "El paquete contiene una ruta fuera de la carpeta sigeru/."; return 1; }
        [[ "$entry" != /* ]] || { fail "El paquete contiene una ruta absoluta no permitida."; return 1; }
        [[ "/$entry/" != *"/../"* ]] || { fail "El paquete contiene una ruta relativa no permitida."; return 1; }
    done < <(tar -tzf "$archive")
    while IFS= read -r entry; do
        case "${entry:0:1}" in
            -|d) ;;
            *) fail "El paquete contiene enlaces o archivos especiales no permitidos."; return 1 ;;
        esac
    done < <(tar -tvzf "$archive")
}

actualizar_env() {
    local env_file="$1" key value tmp
    shift
    tmp="$(mktemp "${env_file}.XXXXXX")"
    [[ -f "$env_file" ]] && cp "$env_file" "$tmp"
    while (($#)); do
        key="$1"; value="$2"; shift 2
        grep -v -E "^${key}=" "$tmp" > "${tmp}.new" || true
        mv "${tmp}.new" "$tmp"
        printf '%s=%s\n' "$key" "$value" >> "$tmp"
    done
    install -m 600 "$tmp" "$env_file"
    rm -f "$tmp"
}

secreto() { od -An -N32 -tx1 /dev/urandom | tr -d ' \n'; }

# Un .env sirve si trae los secretos con los que se creó la base.
env_valido() {
    local archivo="$1" clave
    [[ -f "$archivo" ]] || return 1
    for clave in MYSQL_PASSWORD MYSQL_ROOT_PASSWORD TOKEN_SIGNING_SECRET PASSWORD_HASH_SECRET; do
        grep -q -E "^${clave}=.+" "$archivo" || return 1
    done
}

guardar_copia_env() {
    local env_file="$1"
    install -d -m 700 "$(dirname -- "$ENV_BACKUP")"
    install -m 600 "$env_file" "$ENV_BACKUP"
}

# Decide de dónde salen los secretos cuando la carpeta destino no tiene .env:
#  1. el .env de la instalación anterior (otra carpeta);
#  2. la copia de seguridad en /etc/sigeru;
#  3. si ya existe una base pero no hay secretos, se pide el .env viejo;
#  4. si no hay base, se generan secretos nuevos.
preparar_env() {
    local env_file="$1" previo="$2" ruta respuesta
    [[ -f "$env_file" ]] && return 0

    if [[ "$previo" != "$(dirname -- "$env_file")" ]] && env_valido "$previo/.env"; then
        install -m 600 "$previo/.env" "$env_file"
        ok "Se reutilizó el .env de la instalación anterior ($previo)."
        return 0
    fi

    if env_valido "$ENV_BACKUP"; then
        install -m 600 "$ENV_BACKUP" "$env_file"
        ok "Se reutilizó la copia de seguridad del .env ($ENV_BACKUP)."
        return 0
    fi

    if docker volume inspect "$DB_VOLUME" >/dev/null 2>&1; then
        echo
        echo "ATENCIÓN: ya existe una base de datos de SiGeRU (volumen $DB_VOLUME),"
        echo "pero no se encontró el .env con el que se creó. Con secretos nuevos las"
        echo "APIs no podrían conectarse a esa base."
        read -r -p 'Ruta del .env anterior (ENTER si no lo tiene): ' ruta
        if [[ -n "$ruta" ]]; then
            env_valido "$ruta" || { fail "El archivo $ruta no es un .env válido de SiGeRU."; return 1; }
            install -m 600 "$ruta" "$env_file"
            ok "Se usó el .env indicado."
            return 0
        fi
        echo "Sin ese archivo, la única opción es borrar la base y crear una nueva."
        read -r -p 'Escriba BORRAR para eliminar PERMANENTEMENTE los datos (otra cosa cancela): ' respuesta
        [[ "$respuesta" == "BORRAR" ]] || { fail "Instalación cancelada. No se modificó nada."; return 1; }
        docker ps -aq --filter "volume=$DB_VOLUME" | xargs -r docker rm -f >/dev/null
        docker volume rm "$DB_VOLUME" >/dev/null || { fail "No se pudo borrar el volumen $DB_VOLUME."; return 1; }
        ok "Base anterior eliminada; se crearán secretos nuevos."
    fi

    umask 077
    : > "$env_file"
    actualizar_env "$env_file" \
        MYSQL_DATABASE DB \
        MYSQL_USER app_user \
        MYSQL_PASSWORD "$(secreto)" \
        MYSQL_ROOT_PASSWORD "$(secreto)" \
        TOKEN_SIGNING_SECRET "$(secreto)" \
        PASSWORD_HASH_SECRET "$(secreto)"
}

# Ejecuta SQL como root dentro del contenedor de la base (lee el SQL de stdin).
sql_root() {
    compose_instalado exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -N -B "$MYSQL_DATABASE"'
}

esperar_servicios() {
    local intento
    for intento in $(seq 1 30); do
        if echo 'SELECT 1;' | sql_root >/dev/null 2>&1 \
            && compose_instalado exec -T api-planificador php -r 'exit(0);' >/dev/null 2>&1; then
            return 0
        fi
        sleep 2
    done
    fail "No se pudo entrar a la base como root o la API no responde. Revise que el .env coincida con la base y los logs (opción 6)."
}

# Crea un administrador de sistema solo con cédula y contraseña.
# La contraseña se hashea con el propio código de la aplicación
# (Controller_Sign::hash_password) dentro del contenedor de la API, así que
# usa el mismo PASSWORD_HASH_SECRET que el inicio de sesión.
crear_admin_prueba() {
    local ci clave clave2 hash existe
    docker_disponible || return 1
    esperar_servicios || return 1

    read -r -p 'Cédula del administrador (solo números, hasta 9 dígitos): ' ci
    [[ "$ci" =~ ^[0-9]{1,9}$ ]] || { fail "Cédula inválida."; return 1; }

    existe="$(printf "SELECT (SELECT COUNT(*) FROM usuario WHERE cedula='%s') + (SELECT COUNT(*) FROM solicitud_usuario WHERE cedula='%s');\n" "$ci" "$ci" | sql_root)" \
        || { fail "No se pudo consultar la base. ¿El .env coincide con la base?"; return 1; }
    [[ "$existe" == "0" ]] || { fail "Ya existe un usuario o una solicitud con la cédula $ci."; return 1; }

    read -r -s -p 'Contraseña: ' clave; echo
    read -r -s -p 'Repetir contraseña: ' clave2; echo
    [[ -n "$clave" ]] || { fail "La contraseña no puede estar vacía."; return 1; }
    [[ "$clave" == "$clave2" ]] || { fail "Las contraseñas no coinciden."; return 1; }

    hash="$(printf '%s' "$clave" | compose_instalado exec -T api-planificador \
        php -r "require '$SIGN_CONTROLLER'; echo Controller_Sign::hash_password(stream_get_contents(STDIN));" \
        2>/dev/null | tail -n 1)"
    unset clave clave2
    [[ "$hash" =~ ^[^[:space:]\']{20,}$ ]] || { fail "No se pudo calcular el hash con el código de la API."; return 1; }

    printf "START TRANSACTION;
INSERT INTO usuario (cedula, datos_completados, clave, tipo) VALUES ('%s', 0, '%s', 'admin sistema');
INSERT INTO trabajador (cedula) VALUES ('%s');
INSERT INTO admin_sistemas (cedula) VALUES ('%s');
COMMIT;\n" "$ci" "$hash" "$ci" "$ci" | sql_root \
        || { fail "No se pudo crear el administrador."; return 1; }

    ok "Administrador creado con la cédula $ci. Al entrar por primera vez, el sistema pide nombre y apellido."
}

# Después de instalar: si la base no tiene ningún admin sistema, ofrece crear uno.
ofrecer_admin_prueba() {
    local cantidad respuesta
    esperar_servicios || return 0
    cantidad="$(echo 'SELECT COUNT(*) FROM admin_sistemas;' | sql_root 2>/dev/null)" || return 0
    [[ "$cantidad" == "0" ]] || return 0
    echo
    echo 'La base no tiene ningún administrador de sistema.'
    read -r -p '¿Crear un administrador de prueba ahora? [S/n]: ' respuesta
    [[ "$respuesta" =~ ^[nN]$ ]] && { info 'Se puede crear después con la opción 11 del menú.'; return 0; }
    crear_admin_prueba || info 'Se puede reintentar con la opción 11 del menú.'
}

instalar_desde_paquete() {
    local archive temp version destination env_file bundle_dir port respuesta previo
    [[ "$EUID" -eq 0 ]] || { fail "Para instalar, abra el menú con sudo: sudo bash install.sh"; return 1; }

    read -r -p "Ruta del paquete .tar.gz (ENTER = buscar automáticamente en ../../): " archive
    archive="$(buscar_paquete "$archive")" || return 1
    archive="$(cd -- "$(dirname -- "$archive")" && pwd -P)/$(basename -- "$archive")"

    if [[ -f "$archive.sha256" ]]; then
        (cd -- "$(dirname -- "$archive")" && sha256sum -c "$(basename -- "$archive").sha256") \
            || { fail "El checksum SHA-256 no coincide; no se instalará el paquete."; return 1; }
    else
        info "No hay checksum .sha256 junto al paquete; se continúa sin esa comprobación."
    fi
    ruta_segura_tar "$archive" || return 1

    temp="$(mktemp -d)"
    if ! tar -xzf "$archive" -C "$temp"; then
        rm -rf "$temp"
        fail "No se pudo descomprimir el paquete."
        return 1
    fi
    bundle_dir="$temp/sigeru"
    if [[ ! -f "$bundle_dir/compose.yaml" || ! -f "$bundle_dir/images.tar" || ! -f "$bundle_dir/release-version.txt" ]]; then
        rm -rf "$temp"
        fail "El paquete no contiene compose.yaml, images.tar y release-version.txt."
        return 1
    fi
    version="$(<"$bundle_dir/release-version.txt")"
    [[ "$version" =~ ^[A-Za-z0-9._-]+$ ]] || { rm -rf "$temp"; fail "Versión inválida en el paquete."; return 1; }

    previo="$INSTALL_DIR"
    read -r -p "Directorio de instalación [$INSTALL_DIR]: " destination
    destination="${destination:-$INSTALL_DIR}"
    [[ "$destination" == /* && "$destination" != *$'\n'* ]] || { rm -rf "$temp"; fail "La ruta debe ser absoluta."; return 1; }

    if ! docker_disponible; then
        read -r -p '¿Intentar instalar Docker automáticamente (Debian/Ubuntu/Fedora)? [s/N]: ' respuesta
        if [[ "$respuesta" =~ ^[sS]$ ]]; then
            instalar_docker || { rm -rf "$temp"; return 1; }
            docker_disponible || { rm -rf "$temp"; return 1; }
        else
            rm -rf "$temp"
            return 1
        fi
    fi

    mkdir -p "$destination"
    env_file="$destination/.env"
    preparar_env "$env_file" "$previo" || { rm -rf "$temp"; return 1; }

    port="$(sed -n 's/^FRONT_PORT=//p' "$env_file" | tail -n 1)"
    if [[ -z "$port" ]]; then
        read -r -p 'Puerto HTTP del sitio [8080]: ' port
        port="${port:-8080}"
    fi
    [[ "$port" =~ ^[0-9]{1,5}$ ]] && (( 10#$port >= 1 && 10#$port <= 65535 )) \
        || { rm -rf "$temp"; fail "Puerto inválido: debe estar entre 1 y 65535."; return 1; }

    info "Cargando imágenes Docker del paquete..."
    if ! docker load --input "$bundle_dir/images.tar"; then
        rm -rf "$temp"
        fail "No se pudieron cargar las imágenes."
        return 1
    fi

    mkdir -p "$destination/backups/database" "$destination/backups/project"
    chmod 750 "$destination/backups" "$destination/backups/database" "$destination/backups/project"
    cp "$bundle_dir/compose.yaml" "$destination/compose.yaml"
    cp "$bundle_dir/install.sh" "$destination/install.sh"
    chmod 750 "$destination/install.sh"
    if [[ -d "$bundle_dir/migrations" ]]; then
        mkdir -p "$destination/migrations"
        cp -a "$bundle_dir/migrations/." "$destination/migrations/"
        chmod 750 "$destination/migrations"
    fi

    actualizar_env "$env_file" \
        APP_IMAGE "sigeru/api:$version" \
        FRONT_IMAGE "sigeru/front:$version" \
        DB_IMAGE "sigeru/db:$version" \
        DB_BACKUP_IMAGE "sigeru/database-backup:$version" \
        PROJECT_BACKUP_IMAGE "sigeru/project-backup:$version" \
        BACKUP_CHECK_IMAGE "sigeru/backup-check:$version" \
        FRONT_PORT "$port"
    guardar_copia_env "$env_file"

    printf '%s\n' "$destination" > "$CONFIG_FILE"
    chmod 600 "$CONFIG_FILE"
    INSTALL_DIR="$destination"
    rm -rf "$temp"

    info 'Iniciando servicios...'
    if compose_instalado up -d --no-build --remove-orphans; then
        ok "SiGeRU instalado/actualizado en $INSTALL_DIR"
        printf 'Sitio: http://<IP-de-esta-PC>:%s\n' "$port"
        printf 'La base existente y sus datos se conservan al actualizar. Secretos: %s/.env (permisos 600).\n' "$INSTALL_DIR"
        printf 'Copia de los secretos: %s. Guarde también una copia fuera del servidor.\n' "$ENV_BACKUP"
        ofrecer_admin_prueba
    else
        fail "No se pudo iniciar el stack. Consulte la opción Logs del menú."
        return 1
    fi
}

menu_estado() {
    docker_disponible && compose_instalado ps -a
}
menu_logs() {
    docker_disponible || return 1
    local servicio
    read -r -p 'Servicio (ENTER = todos): ' servicio
    if [[ -n "$servicio" ]]; then
        compose_instalado logs --tail 100 --timestamps "$servicio"
    else
        compose_instalado logs --tail 100 --timestamps
    fi
}

menu_contenedores() {
    docker_disponible || return 1
    docker ps -a --filter label=com.docker.compose.project=sigeru \
        --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}'
}

menu_shell() {
    docker_disponible || return 1
    local servicios servicio
    mapfile -t servicios < <(compose_instalado config --services)
    ((${#servicios[@]})) || { fail 'No se encontraron servicios Compose.'; return 1; }
    echo 'Seleccione el servicio; se abrirá una shell dentro de su contenedor:'
    select servicio in "${servicios[@]}"; do
        [[ -n "${servicio:-}" ]] || { fail 'Selección inválida.'; return 1; }
        compose_instalado exec "$servicio" sh -c 'command -v bash >/dev/null && exec bash || exec sh'
        return
    done
}

menu_imagenes_volumenes() {
    docker_disponible || return 1
    compose_instalado images
    echo
    docker volume ls --filter label=com.docker.compose.project=sigeru
    echo
    docker system df
}

menu_desinstalar() {
    local respuesta
    docker_disponible && compose_instalado down || return 1
    echo 'Los contenedores se detuvieron y retiraron. El volumen de base de datos se conserva.'
    read -r -p '¿Borrar también PERMANENTEMENTE todos los datos de MySQL? [s/N]: ' respuesta
    if [[ "$respuesta" =~ ^[sS]$ ]]; then
        compose_instalado down -v || return 1
        rm -f "$ENV_BACKUP"
        ok 'Stack y volumen persistente eliminados.'
    else
        ok 'Contenedores eliminados; datos conservados para reinstalar.'
    fi
}

menu_principal() {
    local opcion
    while true; do
        printf '\n============================================================\n'
        printf '  SiGeRU | INSTALADOR Y ADMINISTRACIÓN DE DOCKER\n'
        printf '  Instalación: %s\n' "$INSTALL_DIR"
        printf '============================================================\n'
        cat <<'MENU'
  1) Instalar / actualizar desde paquete de imágenes
  2) Ver estado de servicios
  3) Iniciar servicios
  4) Detener servicios
  5) Reiniciar servicios
  6) Ver logs
  7) Listar contenedores SiGeRU
  8) Entrar en un contenedor (shell)
  9) Listar imágenes, volúmenes y espacio Docker
 10) Desinstalar contenedores (conservar datos por defecto)
 11) Crear administrador de prueba (cédula y contraseña)
  0) Salir
MENU
        read -r -p 'Opción: ' opcion || return 0
        clear
        case "$opcion" in
            1) instalar_desde_paquete || true; pause_menu ;;
            2) docker_disponible && menu_estado || true; pause_menu ;;
            3) docker_disponible && compose_instalado start || true; pause_menu ;;
            4) docker_disponible && compose_instalado stop || true; pause_menu ;;
            5) docker_disponible && compose_instalado restart || true; pause_menu ;;
            6) menu_logs || true; pause_menu ;;
            7) menu_contenedores || true; pause_menu ;;
            8) menu_shell || true; pause_menu ;;
            9) menu_imagenes_volumenes || true; pause_menu ;;
           10) menu_desinstalar || true; pause_menu ;;
           11) crear_admin_prueba || true; pause_menu ;;
            0) return 0 ;;
            *) printf 'Opción no válida.\n';;
        esac
    done
}

case "${1:-}" in
    --help|-h) usage ;;
    '') menu_principal ;;
    *) usage >&2; fail "Argumento desconocido: $1" ;;
esac
