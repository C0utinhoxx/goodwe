# Imagem base: PHP 8.3 com Apache (compatível com Laravel 13 / PHP ^8.3)
FROM php:8.3-apache

# Evita prompts interativos do apt
ENV DEBIAN_FRONTEND=noninteractive

# 1. Dependências do sistema + extensões PHP que o Laravel/Firestore exigem
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        zip \
        libzip-dev \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libicu-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        intl \
        zip \
    && pecl install grpc protobuf \
    && docker-php-ext-enable grpc protobuf \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Apache: rewrite para o Laravel + DocumentRoot apontando para /public
RUN a2enmod rewrite headers
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 3. Composer (copiado da imagem oficial, sem instalar PHP extra)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 4. Código da aplicação
WORKDIR /var/www/html
COPY . .

# 5. Dependências PHP de produção (sem dev) + frontend build se existir package.json
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && if [ -f package.json ]; then \
           (npm ci --ignore-scripts 2>/dev/null || npm install --ignore-scripts) \
           && (npm run build 2>/dev/null || true); \
       fi \
    && php artisan storage:link --force 2>/dev/null || true

# 6. Permissões: Apache (www-data) precisa escrever em storage e bootstrap/cache.
#    O credentials.json do Firebase é gerado em runtime via FIREBASE_CREDENTIALS_JSON,
#    então o diretório storage/app/firebase precisa ser gravável.
RUN mkdir -p storage/app/firebase storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# 7. Entrypoint: Render/Koyeb injetam a porta via $PORT.
#    NÃO rodamos config:cache aqui porque as envs (ex.: FIREBASE_CREDENTIALS_JSON)
#    só existem em runtime — cache no build congelaria valores vazios.
COPY --chmod=755 <<'ENTRYPOINT' /usr/local/bin/laravel-entrypoint
#!/bin/sh
set -e
PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/g" /etc/apache2/sites-enabled/*.conf
php artisan migrate --force || true
exec apache2-foreground
ENTRYPOINT

EXPOSE 80

CMD ["laravel-entrypoint"]
