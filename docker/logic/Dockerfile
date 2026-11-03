FROM php:8.2-cli

RUN apt-get update && apt-get install -y git unzip && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY shared/ /app/shared/
COPY logic/ /app/logic/

WORKDIR /app/logic
RUN composer install --no-interaction --optimize-autoloader

ENTRYPOINT ["php"]
CMD ["-r", "echo 'memeShift logic engine ready', PHP_EOL;"]
