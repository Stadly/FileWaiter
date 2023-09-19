FROM php:8.2-fpm-alpine

# Update all packages
RUN apk update && apk upgrade

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install xdebug
RUN apk add \
    $PHPIZE_DEPS \
    linux-headers
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug

# Install git
RUN apk add git

# Copy php.ini
COPY php.ini /usr/local/etc/php/conf.d/
