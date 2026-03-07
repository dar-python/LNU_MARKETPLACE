FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    bash \
    curl \
    default-mysql-client \
    dos2unix \
    git \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libonig-dev \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql intl mbstring exif pcntl bcmath gd zip opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY docker/php/entrypoint.sh /usr/local/bin/laravel-entrypoint
RUN dos2unix /usr/local/bin/laravel-entrypoint \
    && chmod +x /usr/local/bin/laravel-entrypoint

ENTRYPOINT ["laravel-entrypoint"]

EXPOSE 9000

CMD ["php-fpm"]
