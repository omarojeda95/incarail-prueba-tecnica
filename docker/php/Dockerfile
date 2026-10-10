# PHP-FPM 8.5 image for the Laravel API. The same image runs the queue worker
# and the scheduler (see CONTAINER_ROLE in compose.yml).
FROM php:8.5-fpm-alpine

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN install-php-extensions pdo_pgsql redis pcntl bcmath intl zip opcache \
    && apk add --no-cache postgresql-client su-exec

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

WORKDIR /var/www/backend

# Dependencies first so they are cached between code changes.
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY backend/ ./
RUN composer dump-autoload --optimize \
    && rm -f .env \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
