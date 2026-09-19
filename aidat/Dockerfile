# Aidat Yönetim — Apache + mod_php, tek konteyner
FROM php:8.3-apache
RUN set -eux; \
    apt-get update; apt-get install -y --no-install-recommends libsqlite3-dev libzip-dev; \
    docker-php-ext-install -j"$(nproc)" pdo_sqlite pdo_mysql zip; \
    apt-get purge -y --auto-remove libsqlite3-dev libzip-dev; rm -rf /var/lib/apt/lists/*; \
    a2enmod rewrite headers
RUN { echo 'display_errors = Off'; echo 'log_errors = On'; echo 'error_log = /dev/stderr'; echo 'expose_php = Off'; echo 'upload_max_filesize = 6M'; echo 'post_max_size = 8M'; echo 'session.cookie_httponly = 1'; } > /usr/local/etc/php/conf.d/aidat.ini
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
COPY . /var/www/html
RUN mkdir -p storage/logs storage/uploads storage/database storage/backups storage/cache && chown -R www-data:www-data storage
ENV APP_ENV=production DB_DRIVER=sqlite DB_SQLITE_PATH=/var/www/html/storage/database/aidat.sqlite
HEALTHCHECK --interval=30s --timeout=5s CMD curl -fs http://127.0.0.1/saglik || exit 1
COPY docker-entrypoint.sh /usr/local/bin/aidat-entrypoint
RUN chmod +x /usr/local/bin/aidat-entrypoint
ENTRYPOINT ["aidat-entrypoint"]
CMD ["apache2-foreground"]
