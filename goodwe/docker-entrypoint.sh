#!/bin/sh
###############################################################################
# Entry point do container PowerTAG (Render/Koyeb injetam a porta via $PORT).
#
# Escopo: SOMENTE preparar o container para o Apache servir a aplicação.
#   1) Aponta o Apache para a porta do provedor
#   2) Garante storage/ e bootstrap/cache/ graváveis pelo www-data
#   3) Valida a APP_KEY e a DB_CONNECTION (falha cedo e com mensagem clara)
#   3.1) Limpa o cache de configuração para ler as envs de runtime
#   4) Roda as migrações com retry (não há preDeployCommand no plano gratuito)
#   5) Sobe o Apache
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
# 3.2) Limpeza do cache de configuração
#
#    Rede de segurança: se algum dia uma imagem embarcar com
#    bootstrap/cache/config.php, esse arquivo congela os valores de env
#    lidos no build (ex.: FIREBASE_CREDENTIALS_JSON) e o Laravel ignoraria
#    as variáveis injetadas em runtime.
#
#    Hoje isso não ocorre — o Dockerfile não roda `config:cache` e o
#    .dockerignore exclui `bootstrap/cache/*.php` — então o comando é um
#    no-op. Mantemos porque é barato e protege contra regressões futuras.
#
#    Fica ANTES das migrações: um config cache obsoleto faria o migrate
#    rodar contra o banco errado. Não é fatal — não conseguir apagar um
#    cache inexistente jamais deve impedir o container de subir.
###############################################################################
entry_log "Limpando cache de configuração."
if ! php artisan config:clear --no-interaction; then
    entry_log "AVISO: config:clear retornou erro; seguindo com a configuração atual."
fi

###############################################################################
# 4) Migrações
#
#    No plano gratuito do Render não há preDeployCommand nem shell, então o
#    migrate roda no boot do container.
#
#    Decisões deliberadas:
#    * SEM `--isolated`: essa flag usa o cache store para travar o comando
#      (CacheCommandMutex). Com CACHE_STORE=database ela escreve na tabela
#      `cache_locks`, que é criada pela própria migração 0001_01_01_000001
#      — em um banco novo o comando quebra com "Table 'cache_locks' doesn't
#      exist" antes de qualquer migração rodar. Não reintroduzir.
#    * Retry com backoff: o banco pode levar alguns segundos para aceitar
#      conexões logo após o container subir.
#    * NÃO é fatal. Se esgotar as tentativas, registramos um ERROR alto e
#      seguimos para o Apache. Um exit 1 aqui colocaria o Render em loop de
#      restart, que é pior do que servir 500 com o erro visível no log.
#      A diferença para o antigo `|| true` é justamente não ser silencioso.
###############################################################################
MIGRATE_ATTEMPTS="${MIGRATE_ATTEMPTS:-10}"

migrated=0
attempt=1
while [ "$attempt" -le "$MIGRATE_ATTEMPTS" ]; do
    entry_log "Executando migrações (tentativa ${attempt}/${MIGRATE_ATTEMPTS})."

    if php artisan migrate --force --no-interaction; then
        entry_log "Migrações concluídas."
        migrated=1
        break
    fi

    if [ "$attempt" -eq "$MIGRATE_ATTEMPTS" ]; then
        break
    fi

    # Backoff: 3s, 6s, 9s... até o teto de 30s.
    wait=$((attempt * 3))
    [ "$wait" -gt 30 ] && wait=30

    entry_log "Migração falhou; aguardando ${wait}s para o banco ficar disponível."
    attempt=$((attempt + 1))
    sleep "$wait"
done

if [ "$migrated" -eq 0 ]; then
    entry_log "ERRO: migrações não concluídas após ${MIGRATE_ATTEMPTS} tentativas. O container subiu mesmo assim; rotas que usam a sessão vão retornar 500 até o banco estar acessível. Verifique DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD e se as tabelas (sessions, users, cache) existem."
fi

###############################################################################
# 4.1) Cache de configuração, rotas, views e eventos
#
#    Compilado em RUNTIME (não no build) porque as envs do provedor — em
#    especial FIREBASE_CREDENTIALS_JSON — só existem quando o container sobe.
#    config:cache congelaria valores vazios se rodasse durante o build.
#
#    Benefício: cada request deixa de reler/parsear config e registrar ~100
#    rotas. É o ganho de performance mais amplo e não altera nenhum fluxo.
#
#    Cada comando é resiliente: a falha de um não impede os demais nem o boot
#    do Apache (o Laravel volta a compilar views/fluxos sob demanda).
###############################################################################
entry_log "Compilando caches do Laravel."

for cache_command in config:cache event:cache route:cache view:cache; do
    if php artisan "$cache_command" --no-interaction; then
        entry_log "Cache OK: ${cache_command}."
    else
        entry_log "AVISO: falha ao executar '${cache_command}'; seguindo sem esse cache."
    fi
done

# Os comandos artisan rodaram como root e podem ter criado arquivos em
# bootstrap/cache e storage. Sem isto, o worker do Apache (www-data) perde
# o direito de escrita e o erro reaparece em runtime.
chown -R www-data:www-data "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
chmod -R u+rwX,g+rwX "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"

###############################################################################
# 5) Diagnóstico do boot e subida do Apache
###############################################################################
entry_log "Boot OK -> PHP $(php -r 'echo PHP_VERSION;') | APP_ENV=${APP_ENV:-<vazio>} | APP_DEBUG=${APP_DEBUG:-<vazio>} | SESSION_DRIVER=${SESSION_DRIVER:-<vazio>} | CACHE_STORE=${CACHE_STORE:-<vazio>} | DB_CONNECTION=${DB_CONNECTION:-<vazio>} | DB_HOST=${DB_HOST:-<vazio>}"
entry_log "Subindo o Apache na porta ${PORT}."

exec apache2-foreground