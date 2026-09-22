#!/bin/bash
set -e

PORT=${PORT:-10000}
echo "==> Configuring Apache to listen on port ${PORT}..."
sed -i "s/Listen [0-9]*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost \*:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Fallback .env if not mounted or set
if [ ! -f /var/www/html/.env ] && [ -f /var/www/html/.env.example ]; then
    echo "==> Creating .env from .env.example..."
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Ensure storage directories exist
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

# Fix permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Clear old bootstrap caches and discover packages
rm -f /var/www/html/bootstrap/cache/*.php
php artisan package:discover --ansi || true
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

echo "==> Starting Apache in foreground on port ${PORT}..."
exec apache2-foreground
