FROM php:8.5-cli-alpine

ARG APP_UID=1000
ARG APP_GID=1000

RUN apk add --no-cache $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS linux-headers \
    && addgroup -g "${APP_GID}" app \
    && adduser -D -h /home/app -u "${APP_UID}" -G app app \
    && mkdir -p /home/app/.composer \
    && chown -R app:app /home/app

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/conf.d/99-dev.ini /usr/local/etc/php/conf.d/99-dev.ini

WORKDIR /app

CMD ["php", "-a"]
