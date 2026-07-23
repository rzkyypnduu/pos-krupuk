#!/bin/sh
set -e

if [ "$1" = "php-fpm" ] || [ "$1" = "php" ]; then
    echo "Waiting for MySQL..."
    max_tries=30
    try=0
    until php -r "new PDO('mysql:host=mysql;port=3306;dbname=pos_krupuk','poskrupuk','poskrupuk');" 2>/dev/null || [ $try -ge $max_tries ]; do
        try=$((try + 1))
        sleep 2
    done

    if [ $try -ge $max_tries ]; then
        echo "MySQL not reachable after 60s, proceeding anyway..."
    else
        echo "MySQL connected. Running migrations..."
    fi

    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
