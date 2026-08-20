#!/bin/sh
set -e

chown -R www-data:www-data /var/www/html/public/upload /var/www/html/config/jwt /var/www/html/var

# clés JWT
if [ ! -f /var/www/html/config/jwt/private.pem ]; then
    echo "Génération des clés JWT..."
    su-exec www-data php bin/console lexik:jwt:generate-keypair --skip-if-exists
fi

# migrations BDD
echo "Exécution des migrations..."
su-exec www-data php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# change les provilèges de php-fpm de root vers www-data
exec su-exec www-data "$@"