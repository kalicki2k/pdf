FROM php:8.5-cli-alpine

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

CMD ["php", "-a"]
