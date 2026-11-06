#!/usr/bin/env bash
# Opciones Docker avanzadas, separadas del instalador simple de SiGeRU.
set -euo pipefail

comprobar_docker() {
    if ! command -v docker >/dev/null 2>&1; then
        echo 'Docker no está instalado.' >&2
        return 1
    fi
    if ! docker info >/dev/null 2>&1; then
        echo 'No se puede conectar a Docker. Prueba con sudo.' >&2
        return 1
    fi
}

ver_recursos() {
    comprobar_docker || return 1
    docker stats --no-stream --format 'table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.NetIO}}'
}

limpiar_docker() {
    local respuesta
    comprobar_docker || return 1
    docker system df
    read -r -p '¿Eliminar contenedores detenidos, imágenes sin uso y caché? No borra volúmenes. [s/N]: ' respuesta
    [[ "$respuesta" == [sS] ]] || return 0
    docker container prune -f
    docker image prune -af
    docker builder prune -f
}

instalar_docker() {
    if [[ "$EUID" -ne 0 ]]; then
        echo 'Ejecuta este script con sudo para instalar Docker.' >&2
        return 1
    fi

    if command -v docker >/dev/null 2>&1; then
        echo "Docker ya está instalado: $(docker --version)"
    elif command -v apt-get >/dev/null 2>&1; then
        apt-get update
        apt-get install -y docker.io docker-compose-v2 || apt-get install -y docker.io docker-compose-plugin
    elif command -v dnf >/dev/null 2>&1; then
        dnf install -y docker docker-compose-plugin
    else
        echo 'Distribución no soportada para instalación automática.' >&2
        return 1
    fi

    systemctl enable --now docker
    docker compose version
}

menu() {
    local opcion
    while true; do
        echo
        echo 'SiGeRU - Opciones Docker avanzadas'
        echo '  1) Ver consumo de recursos'
        echo '  2) Limpiar recursos Docker sin uso'
        echo '  3) Instalar Docker y habilitarlo al arranque'
        echo '  0) Salir'
        read -r -p 'Opción: ' opcion || return 0
        case "$opcion" in
            1) ver_recursos || true ;;
            2) limpiar_docker || true ;;
            3) instalar_docker || true ;;
            0) return 0 ;;
            *) echo 'Opción no válida.' ;;
        esac
        read -r -p 'ENTER para continuar...' _ || true
    done
}

menu
