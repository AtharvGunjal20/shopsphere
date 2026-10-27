FROM php:8.3-apache

RUN apt-get update && apt-get install -y libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring curl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

COPY . .

EXPOSE 80
