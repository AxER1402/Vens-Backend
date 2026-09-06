# =============================================================================
# Dockerfile — Imagen ÚNICA de DESARROLLO (Frontend + Backend)
# Centro Médico de Flebología — Vens
# =============================================================================
# Un solo contenedor con los tres procesos que hacen falta para trabajar:
#
#   Supervisor (proceso principal)
#     ├── Nginx    :80   ── reparte entre la SPA y la API
#     ├── PHP-FPM  :9000 ── Laravel
#     └── Vite     :5173 ── servidor de desarrollo de React (solo interno)
#
# La diferencia con docker/prod/Dockerfile es importante: aquí la SPA NO se
# compila dentro de la imagen. Nginx hace de proxy hacia el servidor de Vite,
# que sigue vigilando los archivos montados por volumen. Así se conserva la
# recarga en caliente: guardas un .jsx y el navegador se actualiza solo, igual
# que cuando el frontend tenía su propio contenedor.
#
# El código NO se copia a la imagen: entra por los volúmenes que declara
# docker-compose.yml. Por eso esta imagen casi nunca hay que reconstruirla.
# =============================================================================

FROM php:8.4-fpm

ARG UID=1000
ARG GID=1000

ENV DEBIAN_FRONTEND=noninteractive

# ── Dependencias del sistema ──────────────────────────────────────────────────
# Igual que antes, más nginx y supervisor, que ahora viven aquí dentro.
RUN apt-get update && apt-get install -y --no-install-recommends \
        build-essential \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        libssl-dev \
        libicu-dev \
        zip \
        unzip \
        git \
        curl \
        nano \
        procps \
        nginx \
        supervisor \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── Extensiones de PHP ────────────────────────────────────────────────────────
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        soap \
        intl \
        gd \
        zip \
        opcache \
        xml \
    && pecl install redis \
    && docker-php-ext-enable redis

# ── Node.js 22 ────────────────────────────────────────────────────────────────
# Se copia desde la imagen oficial de Node en lugar de instalarlo con apt:
# es la misma base Debian (bookworm), así que los binarios son compatibles, y
# se consigue exactamente la versión 22 que usa el frontend en vez de la que
# tenga el repositorio de Debian.
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && node --version \
    && npm --version

# ── Composer ──────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Usuario ───────────────────────────────────────────────────────────────────
# www-data toma el UID/GID de su usuario del host para que los archivos que
# genere el contenedor (vendor/, node_modules/, caché de Laravel) no aparezcan
# como de otro dueño al editarlos desde el Mac.
RUN groupmod -o -g ${GID} www-data \
    && usermod -o -u ${UID} -g www-data www-data \
    && mkdir -p /home/www-data \
    && chown -R www-data:www-data /home/www-data
ENV HOME=/home/www-data
ENV NPM_CONFIG_CACHE=/home/www-data/.npm

# ── Configuración ─────────────────────────────────────────────────────────────
COPY php/php.ini                 /usr/local/etc/php/conf.d/custom.ini
COPY dev/nginx.conf              /etc/nginx/conf.d/default.conf
COPY dev/supervisord.conf        /etc/supervisor/conf.d/vens.conf
COPY dev/entrypoint.sh           /usr/local/bin/entrypoint

RUN rm -f /etc/nginx/sites-enabled/default \
    && chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

EXPOSE 80

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf", "-n"]
