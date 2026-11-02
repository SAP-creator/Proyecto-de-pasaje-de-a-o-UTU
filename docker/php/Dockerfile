FROM php:8.3-apache

RUN docker-php-ext-install mysqli \
    && a2enmod rewrite

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY api/ /var/www/html/GIT/api/

EXPOSE 80