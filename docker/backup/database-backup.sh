#!/usr/bin/env bash
set -Eeuo pipefail

mkdir -p /backups/database

while true; do
    timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
    output="/backups/database/${MYSQL_DATABASE}-${timestamp}.sql.gz"
    temporary="${output}.tmp"

    echo "[$(date -u)] Creando respaldo de la base ${MYSQL_DATABASE}"
    if MYSQL_PWD="${MYSQL_PASSWORD}" mysqldump \
        --host="${DB_HOST}" \
        --user="${MYSQL_USER}" \
        --single-transaction \
        --routines \
        --triggers \
        "${MYSQL_DATABASE}" | gzip > "${temporary}"; then
        mv "${temporary}" "${output}"
        echo "[$(date -u)] Respaldo creado: ${output}"
    else
        rm -f "${temporary}"
        echo "[$(date -u)] ERROR: no se pudo respaldar la base" >&2
    fi

    find /backups/database -type f -name '*.sql.gz' -mtime "+${BACKUP_RETENTION_DAYS}" -delete
    sleep "${BACKUP_INTERVAL_SECONDS}"
done