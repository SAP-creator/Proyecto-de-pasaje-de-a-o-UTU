FROM fedora:43

RUN dnf install -y httpd php php-fpm php-mysqlnd php-mbstring \
    && dnf clean all \
    && rm -rf /var/cache/dnf \
    && install -d /run/php-fpm

COPY docker/apache/000-default.conf /etc/httpd/conf.d/sigeru.conf
COPY docker/php/www.conf /etc/php-fpm.d/www.conf
COPY docker/php/container-entrypoint.sh /usr/local/bin/container-entrypoint
COPY api/ /var/www/html/GIT/api/

RUN chmod 0555 /usr/local/bin/container-entrypoint

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/container-entrypoint"]