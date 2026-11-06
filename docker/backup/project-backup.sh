#!/bin/sh
set -eu

mkdir -p /backups/project

while true; do
    timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
    output="/backups/project/project-${timestamp}.tar.gz"
    temporary="${output}.tmp"

    echo "[$(date -u)] Creando respaldo de los archivos del proyecto"
    if tar -czf "${temporary}" \
        --exclude='./.git' \
        --exclude='./backups' \
        --exclude='./.env' \
        --exclude='./.env.*' \
        -C /source .; then
        mv "${temporary}" "${output}"
        echo "[$(date -u)] Respaldo creado: ${output}"
    else
        rm -f "${temporary}"
        echo "[$(date -u)] ERROR: no se pudo respaldar el proyecto" >&2
    fi

    find /backups/project -type f -name '*.tar.gz' -mtime "+${BACKUP_RETENTION_DAYS}" -delete
    sleep "${BACKUP_INTERVAL_SECONDS}"
done