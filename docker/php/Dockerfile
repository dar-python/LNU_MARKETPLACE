FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash git curl icu-dev oniguruma-dev libzip-dev \
    freetype-dev libjpeg-turbo-dev libpng-dev \
    mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql intl mbstring zip opcache gd

# Ensure www-data user/group exist (some alpine images already have it)
RUN addgroup -g 82 -S www-data || true \
 && adduser  -u 82 -D -S -G www-data www-data || true

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY ./docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]
