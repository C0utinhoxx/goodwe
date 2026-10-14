#!/bin/sh
# Entry point do container PowerTAG (Render/Koyeb injetam a porta via $PORT).
set -e

# Apache escuta na porta dinâmica do provedor
PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/g" /etc/apache2/sites-enabled/*.conf

# Migrações no boot (as envs só existem em runtime)
php artisan migrate --force || true

exec apache2-foreground
