###############################################################################
# PowerTAG — Dockerfile otimizado para deploy no Render
#
# POR QUE NÃO COMPILAMOS MAIS gRPC/protobuf DO FONTE:
#   * `pecl install grpc` compila o core C++ do gRPC do zero (~15 a 30 min),
#     estourando o tempo limite de build do Render.
#   * O cliente google/cloud-firestore suporta nativamente o transporte REST
#     e o Google API Core escolhe automaticamente `rest` quando a extensão
#     ext-grpc NÃO está carregada (verificado em produção: leitura, escrita,
#     merge, query com streaming e delete funcionando).
#   * O protobuf também é dispensado: sem ext-protobuf, o pacote composer
#     google/protobuf (pure PHP) é usado automaticamente.
# Resultado: build de poucos minutos (e cacheado pelo Render) + imagem menor,
# pois os compiladores (gcc/autoconf) só existem na etapa "build".
###############################################################################

###############################################################################
# Etapa 1 — Frontend (Vite + Tailwind CSS)
# Assets compilados em /app/public/build e copiados para a imagem final.
###############################################################################
FROM node:22-alpine AS frontend

WORKDIR /app

# Cache de camada: manifests primeiro (mudam pouco)
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund

COPY . .

# Falha explícita se o Vite não gerar o manifesto. Sem esta verificação, uma
# build quebrada do Vite produzia uma imagem com public/build vazio, o
# deploy passava e o site subia "sem estilo nenhum" — falha silenciosa.
RUN npm run build \
    && if [ ! -f public/build/manifest.json ]; then \
        echo "ERRO: public/build/manifest.json nao foi gerado pelo Vite."; \
        ls -la public/build 2>/dev/null || echo "public/build nao existe"; \
        exit 1; \
    fi \
    && echo "Frontend OK: $(find public/build -type f | wc -l) arquivos em public/build"

###############################################################################
# Etapa 2 — Backend: extensões PHP + dependências Composer
# PHP 8.4+: o Laravel 13 / Symfony 8 exige PHP >= 8.4 (ver composer.lock)
###############################################################################
FROM php:8.4-apache AS build

ENV DEBIAN_FRONTEND=noninteractive

# Extensões nativas "leves" (compilam em segundos/1-2 min no total).
# Nenhum pecl/grpc, nenhum C++ pesado.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        zip \
        libzip-dev \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libicu-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        intl \
        zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer copiado da imagem oficial (não instala outro PHP)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

# --ignore-platform-req=ext-grpc: o google/cloud-firestore exige ext-grpc no
# composer.json, mas funciona 100% via transporte REST (ver etapa 1 de notas).
RUN composer install --no-dev --no-interaction --prefer-dist \
        --optimize-autoloader --ignore-platform-req=ext-grpc \
    && (php artisan storage:link --force 2>/dev/null || true)

###############################################################################
# Etapa 3 — Imagem final de produção (sem gcc, sem headers de build)
# Mesma tag da etapa "build" para manter extension_dir e libs idênticos.
###############################################################################
FROM php:8.4-apache

ENV DEBIAN_FRONTEND=noninteractive

# Bibliotecas de tempo de execução das extensões PHP (mbstring/intl/zip).
# Instalamos as variantes -dev apenas para não adivinhar a versão da lib em
# cada release do Debian usada pela imagem base — nenhum compilador entra.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libonig-dev \
        libicu-dev \
        libzip-dev \
    && a2enmod rewrite headers \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Apache: DocumentRoot apontando para /public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Extensões PHP compiladas na etapa "build" (mesma tag base => mesmo caminho
# de extension_dir e mesmas libs dinâmicas). O enable é explícito e validado,
# para falhar o build cedo caso alguma extensão não carregue.
COPY --from=build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
RUN docker-php-ext-enable pdo_mysql mbstring exif pcntl bcmath intl zip \
    && for ext in pdo_mysql mbstring exif pcntl bcmath intl zip; do \
           php -m | grep -qi "^${ext}$" \
               || { echo "ERRO: extensão PHP '${ext}' nao carregou na imagem final"; exit 1; }; \
       done

# Código da aplicação com vendor já instalado (sem node_modules, sem .env)
COPY --from=build /var/www/html /var/www/html

# Assets do frontend compilados.
# A origem é /app/public/build (WORKDIR da etapa "frontend" é /app, não
# /var/www/html). apontar o COPY para um caminho inexistente quebraria o
# build da imagem inteira.
COPY --from=frontend /app/public/build /var/www/html/public/build

# Confere que os assets realmente chegaram na imagem final — o @vite do
# Laravel depende deste manifesto para emitir as tags <link>/<script>, e
# sem ele a página subiria sem nenhum estilo, silenciosamente.
RUN if [ ! -f public/build/manifest.json ]; then \
        echo "ERRO: public/build/manifest.json ausente na imagem final."; \
        ls -la public/build 2>/dev/null || echo "public/build nao existe"; \
        exit 1; \
    fi \
    && echo "Assets na imagem final: $(find public/build -type f | wc -l) arquivos"

# EXPLÍCITO de propósito: fixa o cwd do container na raiz da aplicação,
# para qualquer `php artisan` executado via docker exec (debug) rodar no lugar
# certo. Não dá para contar com o WORKDIR herdado da imagem base.
WORKDIR /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/laravel-entrypoint

# Permissões: Apache (www-data) precisa escrever em storage e bootstrap/cache.
# O credentials.json do Firebase é gerado em runtime via FIREBASE_CREDENTIALS_JSON,
# então storage/app/firebase precisa ser gravável.
# O entrypoint repete isso a cada start (o Render pode remontar volumes).
RUN chmod +x /usr/local/bin/laravel-entrypoint \
    && for dir in \
        storage/app/firebase \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
       ; do install -d -o www-data -g www-data -m 0775 "$dir"; done \
    && touch storage/logs/laravel.log \
    && chown www-data:www-data storage/logs/laravel.log \
    && chmod 664 storage/logs/laravel.log \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R u+rwX,g+rwX storage bootstrap/cache

# php.ini de produção. O ponto crucial é log_errors + error_log=/dev/stderr:
# o Render captura o stderr, então a exceção real do Laravel aparece no log do
# deploy em vez de virar um 500 sem explicação. display_errors fica Off para
# não vazar stack trace/segredos na resposta HTTP.
RUN printf '%s\n' \
        'memory_limit = 256M' \
        'max_execution_time = 30' \
        'post_max_size = 20M' \
        'upload_max_filesize = 20M' \
        'date.timezone = UTC' \
        'expose_php = Off' \
        'display_errors = Off' \
        'log_errors = On' \
        'error_log = /dev/stderr' \
        'opcache.enable = 1' \
        'opcache.validate_timestamps = 0' \
        'opcache.max_accelerated_files = 20000' \
        'opcache.memory_consumption = 128' \
        'realpath_cache_size = 4096K' \
        'realpath_cache_ttl = 600' \
    > /usr/local/etc/php/conf.d/99-powertag.ini

# NÃO rodamos config:cache no build porque as envs (ex.: FIREBASE_CREDENTIALS_JSON)
# só existem em runtime — cache no build congelaria valores vazios. O
# .dockerignore também exclui bootstrap/cache/*.php, então a imagem nunca
# embarca config cacheado. As migrações rodam no preDeployCommand do Render.
EXPOSE 80

CMD ["laravel-entrypoint"]
