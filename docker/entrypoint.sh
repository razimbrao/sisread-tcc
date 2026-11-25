#!/bin/sh
set -e

cd /app

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY não definida. Gere com 'php artisan key:generate --show' e grave como segredo." >&2
    exit 1
fi

mkdir -p /data/backups "$XDG_DATA_HOME" "$XDG_CONFIG_HOME"

if [ ! -f "$DB_DATABASE" ]; then
    echo "Criando banco vazio em $DB_DATABASE"
    touch "$DB_DATABASE"
fi

sqlite3 "$DB_DATABASE" "PRAGMA journal_mode=WAL;" >/dev/null

php artisan migrate --force
php artisan db:seed --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan queue:restart

exec supervisord -c /etc/supervisor/sisread.conf
