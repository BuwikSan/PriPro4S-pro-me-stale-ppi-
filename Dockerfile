FROM php:8.2-apache

# Install all packages in one layer to reduce size
RUN apt-get update && apt-get install -y \
    python3 \
    python3-numpy \
    python3-sympy \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Apache config
RUN a2enmod rewrite
RUN echo 'short_open_tag = Off' >> /usr/local/etc/php/php.ini

WORKDIR /var/www/html

EXPOSE 80
