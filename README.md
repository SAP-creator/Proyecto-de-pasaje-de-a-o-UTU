# Aplicación en Docker Compose

El proyecto levanta exactamente seis contenedores:

| Servicio | Función |
| --- | --- |
| `front` | Nginx sirve `front/` y reenvía `/GIT/api/` al backend. |
| `backend` | Apache + PHP 8.3 ejecuta la API y su módulo `mysqli`. |
| `db` | MySQL 8.4 guarda los datos en un volumen persistente. |
| `database-backup` | Genera volcados comprimidos diarios de MySQL. |
| `project-backup` | Archiva diariamente los archivos del proyecto, sin `.env` ni `.git`. |
| `backup-check` | Comprueba la integridad gzip/tar de los respaldos generados. |

## Inicio

1. Copiar `.env.example` como `.env` y sustituir las contraseñas de ejemplo.
2. Desde esta carpeta, ejecutar `docker compose up -d --build`.
3. Abrir `http://localhost:8080` (o el puerto configurado en `FRONT_PORT`).

La API conserva sus rutas actuales bajo `/GIT/api/`. MySQL y el backend no publican puertos directamente al host.

## Datos y respaldos

- MySQL conserva los datos en el volumen `mysql_data`.
- El esquema `api/base de datos (por ahora).sql` se importa únicamente al inicializar un volumen MySQL vacío.
- Los respaldos se guardan en `backups/database/` y `backups/project/` dentro del host.
- El intervalo predeterminado es diario y se retienen 14 días. Se puede ajustar mediante `BACKUP_INTERVAL_SECONDS`, `BACKUP_RETENTION_DAYS` y `CHECK_INTERVAL_SECONDS` en `.env`.
- Consultar los registros con `docker compose logs database-backup project-backup backup-check`.

Los respaldos locales en el mismo disco no sustituyen una copia externa. Para despliegues reales, sincronizar `backups/` a otro dispositivo o almacenamiento remoto y proteger `.env`.

> Cambiar las credenciales de MySQL en `.env` después de que se haya inicializado `mysql_data` no modifica las cuentas ya creadas. No borrar el volumen para aplicar ese cambio sin tener en cuenta que `docker compose down -v` elimina permanentemente la base de datos.

La carpeta `front/` estaba vacía; `front/index.html` es solo una pantalla de puesta en marcha para verificar Nginx. La interfaz real aún debe añadirse ahí.