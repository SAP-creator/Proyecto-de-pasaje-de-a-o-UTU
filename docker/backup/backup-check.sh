#!/bin/sh
set -eu

while true; do
    checked=0

    for archive in /backups/database/*.sql.gz; do
        [ -f "${archive}" ] || continue
        checked=1
        if gzip -t "${archive}"; then
            echo "[$(date -u)] OK: ${archive}"
        else
            echo "[$(date -u)] ERROR: respaldo de base inválido: ${archive}" >&2
        fi
    done

    for archive in /backups/project/*.tar.gz; do
        [ -f "${archive}" ] || continue
        checked=1
        if tar -tzf "${archive}" >/dev/null; then
            echo "[$(date -u)] OK: ${archive}"
        else
            echo "[$(date -u)] ERROR: respaldo de archivos inválido: ${archive}" >&2
        fi
    done

    if [ "${checked}" -eq 0 ]; then
        echo "[$(date -u)] Aún no hay respaldos para verificar"
    fi

    sleep "${CHECK_INTERVAL_SECONDS}"
done