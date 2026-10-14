#!/bin/sh
###############################################################################
# Entry point do container PowerTAG (Render/Koyeb injetam a porta via $PORT).
#
# Escopo: SOMENTE preparar o container para o Apache servir a aplicação.
#   1) Aponta o Apache para a porta do provedor
#   2) Garante storage/ e bootstrap/cache/ graváveis pelo www-data
#   3) Valida a APP_KEY e a DB_CONNECTION (falha cedo e com mensagem clara)
#   4) Sobe o Apache
#
# NÃO executamos migrações aqui: elas rodam no preDeployCommand nativo do
# Render (`php artisan migrate --force`), que roda uma única vez antes de
# publicar o container em vez de uma vez por réplica a cada boot.
###############################################################################
set -eu

APP_DIR=/var/www/html

# Nome prefixado de propósito: "log" colidiria com o binário /usr/bin/log
# de sistemas BSD/macOS, sequestrando a função em depurações locais.
entry_log() {
    printf '[entrypoint] %s %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$*" >&2
}

die() {
    entry_log "FATAL: $*"
    exit 1
}

cd "$APP_DIR"

###############################################################################
# 1) Apache escuta na porta dinâmica do provedor
###############################################################################
PORT="${PORT:-80}"
entry_log "Configurando Apache para a porta ${PORT}."
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/g" /etc/apache2/sites-enabled/*.conf

###############################################################################
# 2) Diretórios de runtime + permissões de escrita para o www-data
#
#    Feito a CADA start, não só no build: o Render pode remontar volumes e
#    restaurar as permissões a partir da imagem.
###############################################################################
entry_log "Garantindo diretórios de runtime e permissões do www-data."

for dir in \
    storage/app/firebase \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
do
    mkdir -p "${APP_DIR:?}/${dir}"
done

# O arquivo de log precisa existir antes do primeiro request: se o diretório
# estiver ok o Laravel cria, mas um toque aqui evita o 500 do primeiro acesso.
# Nasce root-owned (o entrypoint roda como root), por isso o chown logo abaixo.
touch "${APP_DIR}/storage/logs/laravel.log"

chown -R www-data:www-data "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
chmod -R u+rwX,g+rwX "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
chmod 664 "${APP_DIR}/storage/logs/laravel.log"

###############################################################################
# 3) Validação do APP_KEY
#
#    O .env é ignorado pelo .dockerignore, então a chave vem 100% do Render.
#    Sem APP_KEY, EncryptCookies/StartSession lançam MissingAppKeyException em
#    TODA requisição web — e o / redireciona pela sessão, ou seja, 500 total.
#    Falhar aqui é melhor: a mensagem aparece no log do deploy.
###############################################################################
if [ -z "${APP_KEY:-}" ]; then
    die "APP_KEY não definida. No Render: Environment > Add 'APP_KEY' com o valor gerado por 'php artisan key:generate --show'."
fi

if ! php -r '
    $key = getenv("APP_KEY");
    $raw = str_starts_with($key, "base64:") ? base64_decode(substr($key, 7), true) : $key;
    exit(is_string($raw) && strlen($raw) === 32 ? 0 : 1);
'; then
    die "APP_KEY inválida: esperado 'base64:' + 32 bytes. Gere com 'php artisan key:generate --show'."
fi

###############################################################################
# 3.1) Validação da DB_CONNECTION
#
#    config/database.php usa env('DB_CONNECTION', 'sqlite'). Sem a variável,
#    o Laravel cai em sqlite e tenta abrir database/database.sqlite — arquivo
#    que não existe na imagem (é ignorado pelo database/.gitignore). O
#    resultado é "Database file at path ... does not exist" em TODA rota web,
#    enquanto /up segue em 200 — um 500 confuso e difícil de diagnosticar.
###############################################################################
if [ -z "${DB_CONNECTION:-}" ]; then
    die "DB_CONNECTION não definida. Sem ela o Laravel assume sqlite e tenta abrir /var/www/html/database/database.sqlite, que não existe na imagem. No Render: Environment > Add 'DB_CONNECTION=mysql'."
fi

if [ "${DB_CONNECTION}" = "sqlite" ]; then
    die "DB_CONNECTION=sqlite não é suportado nesta imagem: não há database/database.sqlite. Use 'DB_CONNECTION=mysql'."
fi

###############################################################################
# 4) Diagnóstico do boot e subida do Apache
###############################################################################
entry_log "Boot OK -> PHP $(php -r 'echo PHP_VERSION;') | APP_ENV=${APP_ENV:-<vazio>} | APP_DEBUG=${APP_DEBUG:-<vazio>} | SESSION_DRIVER=${SESSION_DRIVER:-<vazio>} | CACHE_STORE=${CACHE_STORE:-<vazio>} | DB_CONNECTION=${DB_CONNECTION:-<vazio>} | DB_HOST=${DB_HOST:-<vazio>}"
entry_log "Migrações são responsabilidade do preDeployCommand; subindo o Apache na porta ${PORT}."

exec apache2-foreground