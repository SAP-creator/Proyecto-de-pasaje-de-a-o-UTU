#!/bin/sh
set -eu

while true; do
    checked=0

    database_archives=$(find /backups/database -type f -name '*.sql.gz' -print)
    if [ -n "$database_archives" ]; then
        checked=1
        printf '%s\n' "$database_archives" | while IFS= read -r archive; do
            if gzip -t "${archive}"; then
                echo "[$(date -u)] OK: ${archive}"
            else
                echo "[$(date -u)] ERROR: respaldo de base inválido: ${archive}" >&2
            fi
        done
    fi

    project_archives=$(find /backups/project -type f -name '*.tar.gz' -print)
    if [ -n "$project_archives" ]; then
        checked=1
        printf '%s\n' "$project_archives" | while IFS= read -r archive; do
            if tar -tzf "${archive}" >/dev/null; then
                echo "[$(date -u)] OK: ${archive}"
            else
                echo "[$(date -u)] ERROR: respaldo de archivos inválido: ${archive}" >&2
            fi
        done
    fi

    if [ "${checked}" -eq 0 ]; then
        echo "[$(date -u)] Aún no hay respaldos para verificar"
    fi

    sleep "${CHECK_INTERVAL_SECONDS}"
done