FROM php:8.4.27-fpm-alpine

RUN docker-php-ext-install pdo_mysql

COPY --from=composer:2.10.3 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
