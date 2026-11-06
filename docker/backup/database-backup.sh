#!/usr/bin/env bash
set -Eeuo pipefail

BACKUP_TIME=${BACKUP_TIME:-02:30}
SLEEP_SECONDS=60
case "$BACKUP_TIME" in
    [0-2][0-9]:[0-5][0-9]) ;;
    *) echo "BACKUP_TIME debe tener formato HH:MM" >&2; exit 1 ;;
esac
hour=${BACKUP_TIME%:*}
if [ "$hour" -gt 23 ]; then
    echo "BACKUP_TIME debe estar entre 00:00 y 23:59" >&2
    exit 1
fi

mkdir -p /backups/database
last_run_file=/backups/database/.last-run-date

while true; do
    today="$(date +%Y%m%d)"
    now="$(date +%H:%M)"
    last_run="$(cat "$last_run_file" 2>/dev/null || true)"

    if [ -z "$last_run" ] || { [ "$last_run" != "$today" ] && { [ "$now" \> "$BACKUP_TIME" ] || [ "$now" = "$BACKUP_TIME" ]; }; }; then
        timestamp="$(date +%Y%m%dT%H%M%S)"
        output="/backups/database/${MYSQL_DATABASE}-${timestamp}.sql.gz"
        temporary="${output}.tmp"

        echo "[$(date)] Creando respaldo completo de la base ${MYSQL_DATABASE}"
        if MYSQL_PWD="${MYSQL_PASSWORD}" mysqldump \
            --host="${DB_HOST}" \
            --user="${MYSQL_USER}" \
            --single-transaction \
            --routines \
            --triggers \
            "${MYSQL_DATABASE}" | gzip > "${temporary}"; then
            mv "${temporary}" "${output}"
            printf '%s\n' "$today" > "$last_run_file.tmp"
            mv "$last_run_file.tmp" "$last_run_file"
            echo "[$(date)] Respaldo completo creado: ${output}"
        else
            rm -f "${temporary}" "$last_run_file.tmp"
            echo "[$(date)] ERROR: no se pudo respaldar la base" >&2
        fi

        find /backups/database -type f -name '*.sql.gz' -mtime "+${BACKUP_RETENTION_DAYS}" -delete
    fi

    sleep "$SLEEP_SECONDS"
done