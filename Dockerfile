FROM composer:2 AS dependencies
WORKDIR /src
COPY composer.json ./
RUN composer install --no-dev --prefer-dist --no-interaction --ignore-platform-req=ext-pdo_mysql --ignore-platform-req=ext-mbstring --ignore-platform-req=ext-zip
FROM php:8.2-apache
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libzip-dev && docker-php-ext-install pdo_mysql mbstring zip && a2enmod rewrite && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/pk
COPY . .
COPY --from=dependencies /src/vendor ./vendor
RUN sed -ri 's!/var/www/html!/var/www/pk/public!g' /etc/apache2/sites-available/000-default.conf && printf '<Directory /var/www/pk/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/pk.conf && a2enconf pk && mkdir -p storage/files storage/logs storage/conversions && chown -R www-data:www-data storage && printf 'upload_max_filesize=20M\npost_max_size=22M\nexpose_php=Off\n' > /usr/local/etc/php/conf.d/pk.ini
EXPOSE 80
