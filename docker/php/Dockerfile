ARG PHP_VERSION=8.5

FROM php:${PHP_VERSION}-cli-alpine

WORKDIR /var/www/html

RUN apk add --no-cache \
    bash \
    curl \
    freetype-dev \
    git \
    gmp-dev \
    icu-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    linux-headers \
    nodejs \
    npm \
    oniguruma-dev \
    unzip \
    zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        bcmath \
        exif \
        gd \
        gmp \
        intl \
        pcntl \
        pdo_mysql \
        sockets \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN git config --global --add safe.directory /var/www/html

EXPOSE 8000
