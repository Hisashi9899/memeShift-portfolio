FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring zip gd xml

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY shared/ /var/www/shared/

WORKDIR /var/www/html

COPY backend/ .

RUN rm -f bootstrap/cache/*.php \
    && mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views storage/app/public

RUN composer install --no-interaction --optimize-autoloader --no-dev 2>/dev/null || true

RUN rm -f bootstrap/cache/*.php

# ★ 最重要：相対パスを絶対パスに変更
COPY docker/laravel/www.conf /usr/local/etc/php-fpm.d/www.conf

COPY docker/laravel/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
