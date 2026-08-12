FROM php:8.4-fpm-alpine AS base

RUN sed -i 's|/sbin/nologin|/bin/sh|g' /etc/passwd

RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    libpng-dev

RUN docker-php-ext-install \
    intl \
    pdo_mysql \
    zip \
    opcache

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && apk del .build-deps

WORKDIR /var/www/html

FROM base AS dev

RUN apk add --no-cache git unzip openssh $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS linux-headers

RUN echo "xdebug.mode=debug,coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.log=/tmp/xdebug.log" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

USER www-data

EXPOSE 9000
CMD ["php-fpm"]

FROM base AS builder

RUN apk add --no-cache git unzip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

FROM base AS prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

COPY --from=builder /var/www/html /var/www/html


RUN mkdir -p /var/www/html/var && chown -R www-data:www-data /var/www/html/var

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
