FROM fedora:43

COPY docker/backup/backup-check.sh /scripts/backup-check.sh
RUN dnf install -y coreutils findutils gzip tar \
	&& dnf clean all \
	&& rm -rf /var/cache/dnf \
	&& chmod 0555 /scripts/backup-check.sh
ENTRYPOINT ["/bin/sh", "/scripts/backup-check.sh"]
