#!/bin/sh
set -eu

BACKUP_TIME=${BACKUP_TIME:-02:30}
BACKUP_RETENTION_WEEKS=${BACKUP_RETENTION_WEEKS:-4}
SLEEP_SECONDS=60

case "$BACKUP_TIME" in
    [0-2][0-9]:[0-5][0-9]) ;;
    *) echo "BACKUP_TIME debe tener formato HH:MM" >&2; exit 1 ;;
esac
hour=${BACKUP_TIME%:*}
minute=${BACKUP_TIME#*:}
if [ "$hour" -gt 23 ]; then
    echo "BACKUP_TIME debe estar entre 00:00 y 23:59" >&2
    exit 1
fi
case "$BACKUP_RETENTION_WEEKS" in
    ''|*[!0-9]*) echo "BACKUP_RETENTION_WEEKS debe ser un entero positivo" >&2; exit 1 ;;
esac
if [ "$BACKUP_RETENTION_WEEKS" -lt 1 ]; then
    echo "BACKUP_RETENTION_WEEKS debe ser al menos 1" >&2
    exit 1
fi

mkdir -p /backups/project
last_run_file=/backups/project/.last-run-date

crear_respaldo() {
    current_date=$(date +%Y%m%d)
    week=$(date +%G-W%V)
    weekday=$(date +%u)
    week_dir="/backups/project/$week"
    snapshot="$week_dir/.snapshot.snar"
    archive_type=incremental

    mkdir -p "$week_dir"
    if [ "$weekday" -eq 7 ] || [ ! -f "$snapshot" ]; then
        archive_type=full
    fi

    final_archive="$week_dir/${archive_type}-${current_date}.tar.gz"
    temporary_archive="${final_archive}.tmp"
    temporary_snapshot="${snapshot}.tmp"
    rm -f "$temporary_archive" "$temporary_snapshot"
    if [ "$archive_type" = incremental ]; then
        cp "$snapshot" "$temporary_snapshot"
    fi

    echo "[$(date)] Creando respaldo $archive_type de archivos para la semana $week"
    if tar --listed-incremental="$temporary_snapshot" -czf "$temporary_archive" \
        --exclude='./.git' \
        --exclude='./backups' \
        --exclude='./.env' \
        --exclude='./.env.*' \
        --exclude='*.tmp' \
        -C /source .; then
        mv "$temporary_archive" "$final_archive"
        mv "$temporary_snapshot" "$snapshot"
        printf '%s\n' "$current_date" > "$last_run_file.tmp"
        mv "$last_run_file.tmp" "$last_run_file"
        echo "[$(date)] Respaldo creado: $final_archive"
        return 0
    fi

    rm -f "$temporary_archive" "$temporary_snapshot" "$last_run_file.tmp"
    echo "[$(date)] ERROR: no se pudo crear el respaldo de archivos" >&2
    return 1
}

limpiar_semanas_antiguas() {
    cutoff_week=$(date -d "$BACKUP_RETENTION_WEEKS weeks ago" +%G-W%V)
    for directory in /backups/project/20??-W??; do
        [ -d "$directory" ] || continue
        week=${directory##*/}
        if [ "$week" \< "$cutoff_week" ]; then
            rm -rf "$directory"
            echo "[$(date)] Eliminada semana antigua: $week"
        fi
    done
}

while true; do
    today=$(date +%Y%m%d)
    now=$(date +%H:%M)
    last_run=$(cat "$last_run_file" 2>/dev/null || true)

    # Ejecuta una primera copia al iniciar el servicio. Luego hace como máximo
    # una copia al día, a la hora configurada; los reinicios no duplican el día.
    if [ -z "$last_run" ] || { [ "$last_run" != "$today" ] && [ "$now" \> "$BACKUP_TIME" -o "$now" = "$BACKUP_TIME" ]; }; then
        if crear_respaldo; then
            limpiar_semanas_antiguas
        fi
    fi

    sleep "$SLEEP_SECONDS"
done
