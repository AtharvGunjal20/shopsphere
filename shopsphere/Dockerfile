FROM php:8.3-apache

RUN apt-get update && apt-get install -y libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring curl \
    && a2enmod rewrite \
    && sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:10000>/' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '\n<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' >> /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

RUN printf 'log_errors=On\nerror_log=/proc/self/fd/2\ndisplay_errors=Off\n' > /usr/local/etc/php/conf.d/zz-render-logging.ini

WORKDIR /var/www/html

COPY . .

EXPOSE 10000
