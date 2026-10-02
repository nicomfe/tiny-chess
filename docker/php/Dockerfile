FROM php:8.3-fpm-alpine

RUN docker-php-ext-install pdo_mysql

COPY php.ini /usr/local/etc/php/conf.d/app.ini
COPY www.conf /usr/local/etc/php-fpm.d/zz-app.conf

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
