#!/bin/sh
set -e

chown -R www-data:www-data /var/www/html/public/upload /var/www/html/config/jwt /var/www/html/var

if [ "$1" = 'php-fpm' ]; then
    if [ ! -f /var/www/html/config/jwt/private.pem ]; then
        echo "Génération des clés JWT..."
        su-exec www-data php bin/console lexik:jwt:generate-keypair --skip-if-exists
    fi

    echo "Exécution des migrations..."
    su-exec www-data php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

# Lancement de PHP-FPM (il gère lui-même la baisse de privilèges vers www-data)
exec "$@"