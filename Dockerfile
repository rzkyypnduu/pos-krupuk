FROM node:22-alpine AS build
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci
COPY resources/ resources/
RUN npm run build

FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libzip-dev \
    unzip \
    curl \
    git \
    oniguruma-dev \
    && docker-php-ext-install pdo_mysql mbstring gd zip pcntl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

COPY . .
COPY --from=build /app/public/build/ public/build/

RUN composer install --no-dev --optimize-autoloader

RUN php artisan storage:link || true

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
