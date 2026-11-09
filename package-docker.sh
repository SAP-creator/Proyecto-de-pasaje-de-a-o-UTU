#!/usr/bin/env bash
# Construye un paquete Docker autocontenido para transferir a otra máquina Linux.
set -Eeuo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
VERSION="${1:-$(date -u +%Y.%m.%d-%H%M)}"
OUTPUT_DIR="${2:-$ROOT_DIR/dist}"
IMAGE="sigeru/api:${VERSION}"
FRONT_IMAGE="sigeru/front:${VERSION}"
DB_IMAGE="sigeru/db:${VERSION}"
DB_BACKUP_IMAGE="sigeru/database-backup:${VERSION}"
PROJECT_BACKUP_IMAGE="sigeru/project-backup:${VERSION}"
BACKUP_CHECK_IMAGE="sigeru/backup-check:${VERSION}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

fail() { printf '[ERROR] %s\n' "$*" >&2; exit 1; }

[[ "$VERSION" =~ ^[A-Za-z0-9._-]+$ ]] || fail "Versión inválida: use letras, números, punto, guion o guion bajo."
mkdir -p "$OUTPUT_DIR" || fail "No se pudo crear el directorio de salida: $OUTPUT_DIR"
OUTPUT_DIR="$(cd -- "$OUTPUT_DIR" && pwd -P)"
[[ -w "$OUTPUT_DIR" ]] || fail "No se puede escribir en $OUTPUT_DIR. Pase una carpeta de salida propia como segundo argumento."
command -v docker >/dev/null 2>&1 || fail "Docker no está instalado en esta PC de construcción."
docker info >/dev/null 2>&1 || fail "El servicio Docker no está activo o el usuario no tiene permisos."
docker compose version >/dev/null 2>&1 || fail "Falta Docker Compose v2 (comando: docker compose)."

mkdir -p "$STAGE/sigeru"
BUILD_ENV="$STAGE/build.env"
cat > "$BUILD_ENV" <<ENV
APP_IMAGE=$IMAGE
FRONT_IMAGE=$FRONT_IMAGE
DB_IMAGE=$DB_IMAGE
DB_BACKUP_IMAGE=$DB_BACKUP_IMAGE
PROJECT_BACKUP_IMAGE=$PROJECT_BACKUP_IMAGE
BACKUP_CHECK_IMAGE=$BACKUP_CHECK_IMAGE
FRONT_PORT=8080
MYSQL_DATABASE=DB
MYSQL_USER=app_user
MYSQL_PASSWORD=hola1234
MYSQL_ROOT_PASSWORD=hola123
TOKEN_SIGNING_SECRET=auisgdiugalhsd_iaysufdghl
PASSWORD_HASH_SECRET=w192037tuighakjñ_daksgdiugwo
ENV

printf '== Descargando imágenes base ==\n'
docker pull fedora:43
docker pull mysql:8.4
printf '== Construyendo todas las imágenes de SiGeRU (%s) ==\n' "$VERSION"
docker compose --project-directory "$ROOT_DIR" --file "$ROOT_DIR/compose.yaml" --env-file "$BUILD_ENV" \
    build --pull front api-planificador db database-backup project-backup backup-check

images="$IMAGE $FRONT_IMAGE $DB_IMAGE $DB_BACKUP_IMAGE $PROJECT_BACKUP_IMAGE $BACKUP_CHECK_IMAGE"
for image in $images; do
    docker image inspect "$image" >/dev/null || fail "No se encontró la imagen $image."
done

# Solo viajan imágenes y archivos de despliegue. El código y los Dockerfiles no se distribuyen.
cp "$ROOT_DIR/compose.release.yaml" "$STAGE/sigeru/compose.yaml"
cp "$ROOT_DIR/install.sh" "$STAGE/sigeru/install.sh"
if compgen -G "$ROOT_DIR/api/migrations/*.sql" >/dev/null; then
    mkdir -p "$STAGE/sigeru/migrations"
    cp "$ROOT_DIR/api/migrations/"*.sql "$STAGE/sigeru/migrations/"
fi
printf '%s\n' "$VERSION" > "$STAGE/sigeru/release-version.txt"

printf '== Exportando imágenes ==\n'
# Las etiquetas de imagen son nombres controlados generados por Compose.
# shellcheck disable=SC2086
docker save --output "$STAGE/sigeru/images.tar" $images

ARCHIVE="$OUTPUT_DIR/sigeru-docker-${VERSION}.tar.gz"
tar -C "$STAGE" -czf "$ARCHIVE" sigeru
(cd "$OUTPUT_DIR" && sha256sum "$(basename "$ARCHIVE")" > "$(basename "$ARCHIVE").sha256")
printf '\nPaquete listo: %s\nTamaño: %s\n' "$ARCHIVE" "$(du -h "$ARCHIVE" | cut -f1)"
printf 'Checksum: %s\n' "$ARCHIVE.sha256"
printf 'Transfiera ese .tar.gz a la otra PC, descomprímalo y ejecute: sudo bash sigeru/install.sh\n'
