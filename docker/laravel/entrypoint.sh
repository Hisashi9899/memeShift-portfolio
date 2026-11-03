#!/bin/sh

# storage ディレクトリを復元（volume で隠れるため）
mkdir -p /var/www/html/storage/logs \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/app/public

# 権限修正（失敗しても続行）
chown -R www-data:www-data /var/www/html/storage 2>/dev/null || true
chown -R www-data:www-data /var/www/html/bootstrap/cache 2>/dev/null || true

# storage symlink
if [ ! -L /var/www/html/public/storage ]; then
    php /var/www/html/artisan storage:link --no-interaction 2>/dev/null || true
fi

# vendor の状態を正しく反映
php /var/www/html/artisan package:discover --no-interaction 2>/dev/null || true

# php-fpm を起動
exec php-fpm
