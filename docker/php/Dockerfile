FROM php:8.4-fpm

# 1. Dépendances système
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Extensions PHP standards
RUN docker-php-ext-install \
    intl \
    pdo_mysql \
    zip \
    opcache

# 3. Xdebug et APCu (via PECL)
RUN pecl install xdebug apcu \
    && docker-php-ext-enable xdebug apcu

# 4. Configuration minimale de Xdebug pour le développement
RUN echo "xdebug.mode=debug,coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# 5. Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 9000
