FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libzip-dev zip unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql mysqli \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY php.ini /usr/local/etc/php/conf.d/cartaz-custom.ini
COPY src/public /var/www/html/public
COPY src /var/www/html

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html
