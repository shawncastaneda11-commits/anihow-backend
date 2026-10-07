FROM dunglas/frankenphp:1-php8.4

WORKDIR /app

RUN install-php-extensions \
    pdo_mysql \
    intl \
    zip \
    gd \
    bcmath \
    pcntl \
    opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts

COPY . .

RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    && composer dump-autoload --optimize \
    && php artisan package:discover --ansi --no-interaction

ENTRYPOINT ["sh", "-c"]
CMD ["php artisan storage:link --force || true; php artisan migrate --force && php artisan serve --host 0.0.0.0 --port ${PORT:-8000}"]
