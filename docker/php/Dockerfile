FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache bash clamav curl ffmpeg icu-libs libjpeg-turbo libpng libwebp libzip nginx supervisor \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libjpeg-turbo-dev libpng-dev libwebp-dev libzip-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" bcmath exif gd intl opcache pcntl pdo_mysql zip \
    && apk del .build-deps

COPY --from=composer:2.10 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
CMD ["php-fpm", "-F"]
