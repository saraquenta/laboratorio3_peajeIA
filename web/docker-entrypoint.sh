#!/bin/sh
# Arranque del contenedor web: puerto de la plataforma -> storage:link -> migraciones (con reintentos mientras
# la BD despierta) -> seeders SOLO si la BD esta vacia -> caches -> Apache.
set -e
PORT="${PORT:-8000}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ ! -f database/database.sqlite ]; then
  touch database/database.sqlite && chown www-data:www-data database/database.sqlite
fi

php artisan storage:link >/dev/null 2>&1 || true
i=0
until php artisan migrate --force; do
  i=$((i + 1))
  if [ "$i" -ge 10 ]; then echo "La base de datos no responde"; exit 1; fi
  echo "Esperando a la base de datos ($i/10)..."; sleep 6
done
php artisan peaje:sembrar-si-vacio
php artisan optimize
chown -R www-data:www-data storage bootstrap/cache database
echo "Peaje IA escuchando en el puerto ${PORT}"
exec apache2-foreground
