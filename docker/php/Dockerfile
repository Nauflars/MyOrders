FROM php:8.3-fpm-alpine

# Install system dependencies (minimal for US1 - Welcome Page)
RUN apk add --no-cache git curl zip unzip bash

# Install PHP extensions (only essentials for MySQL + Symfony)
RUN docker-php-ext-install pdo pdo_mysql opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Create Symfony var directories with proper permissions
RUN mkdir -p var/cache var/log \
    && chmod -R 777 var \
    && chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
