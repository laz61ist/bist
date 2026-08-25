# BIST Kokpit — production imaji
#
# Tek konteyner: Apache + mod_php. En az parca, en az hareketli aksam.
# php-fpm + ayri nginx daha "dogru" gorunur ama bu uygulama tek instance
# calisacak (sqlite yatay olceklemeyi yasakliyor, bkz. #7), dolayisiyla
# ek katman kazanc getirmez.

# --- 1. asama: bagimliliklar ---
FROM composer:2 AS bagimliliklar
WORKDIR /derleme
COPY composer.json composer.lock ./
# --no-dev: uretimde tek require-dev PHPUnit, imaja girmesin
# --no-scripts: paket script'leri derleme aninda calismasin (#14 ile ayni gerekce)
RUN composer install \
      --no-dev \
      --no-scripts \
      --no-interaction \
      --prefer-dist \
      --optimize-autoloader \
      --classmap-authoritative

# --- 2. asama: calisma ---
FROM php:8.3-apache

# pdo_sqlite ve mbstring: CI'da kurulan ayni ikili
RUN docker-php-ext-install -j"$(nproc)" pdo_sqlite \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Uretim php.ini — hata ayrintisi kullaniciya gitmez, log'a gider (#8)
RUN { \
      echo 'display_errors = Off'; \
      echo 'display_startup_errors = Off'; \
      echo 'log_errors = On'; \
      echo 'error_log = /dev/stderr'; \
      echo 'expose_php = Off'; \
      echo 'post_max_size = 1M'; \
      echo 'upload_max_filesize = 1M'; \
      echo 'memory_limit = 128M'; \
    } > /usr/local/etc/php/conf.d/bist.ini

# Docroot public/ — vendor/, var/, .git/ web'den erisilemez
ENV APACHE_DOCUMENT_ROOT=/uygulama/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
      /etc/apache2/sites-available/*.conf \
      /etc/apache2/apache2.conf \
    && printf '%s\n' \
      '<Directory /uygulama/public>' \
      '  AllowOverride None' \
      '  Require all granted' \
      '  FallbackResource /index.php' \
      '</Directory>' \
      'ServerTokens Prod' \
      'ServerSignature Off' \
      > /etc/apache2/conf-available/bist.conf \
    && a2enconf bist

WORKDIR /uygulama
COPY --from=bagimliliklar /derleme/vendor ./vendor
COPY composer.json composer.lock ./
COPY public/ ./public/
COPY src/ ./src/

# Kalici veri buraya baglanir. Volume BAGLANMAZSA veri her yeniden
# baslatmada kaybolur (#7) — README'de yaziyor.
RUN mkdir -p /data && chown -R www-data:www-data /data
VOLUME ["/data"]

ENV BIST_ENV=production \
    BIST_DB_PATH=/data/bist.sqlite

EXPOSE 80

# Saglik denetimi DB'ye gercekten dokunur; bozuksa 503 doner (#9)
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD php -r 'exit(@file_get_contents("http://127.0.0.1/saglik") !== false ? 0 : 1);'
