FROM php:8.2-fpm

RUN docker-php-ext-install pdo_mysql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json .
RUN composer install --no-interaction --no-dev --optimize-autoloader 2>/dev/null || echo "skip"