FROM fedora:43

COPY docker/backup/project-backup.sh /scripts/project-backup.sh
RUN dnf install -y coreutils findutils gzip tar tzdata \
    && dnf clean all \
    && rm -rf /var/cache/dnf \
    && chmod 0555 /scripts/project-backup.sh
ENTRYPOINT ["/bin/sh", "/scripts/project-backup.sh"]
