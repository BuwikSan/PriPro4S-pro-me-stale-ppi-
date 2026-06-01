FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

RUN a2enmod rewrite

RUN echo 'short_open_tag = Off' >> /usr/local/etc/php/php.ini

WORKDIR /var/www/html

EXPOSE 80
