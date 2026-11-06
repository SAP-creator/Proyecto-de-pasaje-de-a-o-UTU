FROM mysql:8.4

COPY docker/backup/database-backup.sh /scripts/database-backup.sh
RUN chmod 0555 /scripts/database-backup.sh
ENTRYPOINT ["bash", "/scripts/database-backup.sh"]
