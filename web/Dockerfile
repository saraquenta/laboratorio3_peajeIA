# Imagen del panel web (Laravel) para Render, Railway, Fly.io o cualquier VPS con Docker.
# PHP 8.3 + Apache. Las extensiones pdo_sqlite, mbstring, curl, fileinfo y openssl ya vienen en la imagen oficial.
FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev libicu-dev \
    && docker-php-ext-install pdo_pgsql pdo_mysql zip intl bcmath \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Limites de subida para frames y fotos de placas
RUN printf "upload_max_filesize=10M\npost_max_size=12M\nmemory_limit=256M\nexpose_php=Off\n" > /usr/local/etc/php/conf.d/peaje.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && sed -ri 's#/var/www/html#/var/www/html/public#g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n  AllowOverride All\n  Require all granted\n</Directory>\n' > /etc/apache2/conf-available/peaje.conf \
    && a2enconf peaje \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod +x docker-entrypoint.sh

ENV PORT=8000
EXPOSE 8000
ENTRYPOINT ["./docker-entrypoint.sh"]
