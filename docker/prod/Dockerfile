# syntax=docker/dockerfile:1.7
# =============================================================================
# Dockerfile — Imagen ÚNICA de producción (Frontend + Backend)
# Centro Médico de Flebología — Vens
# =============================================================================
# Esta imagen contiene la aplicación completa en un solo contenedor:
#
#   Nginx (puerto 80)
#     ├── /            → SPA de React ya compilada (Frontend-Vens)
#     ├── /api/*       → Laravel vía PHP-FPM (Backend-Vens)
#     ├── /storage/*   → archivos subidos (logo del membrete, adjuntos)
#     └── /img/*       → imágenes del public/ de Laravel (isotipo, plantilla)
#   PHP-FPM (127.0.0.1:9000, no expuesto)
#   Supervisor          → mantiene vivos a los dos procesos anteriores
#
# IMPORTANTE — El contexto de build es la carpeta PADRE, la que contiene
# tanto Backend-Vens/ como Frontend-Vens/, porque la imagen necesita el
# código de los dos repositorios. Por eso todas las rutas COPY empiezan
# con "Backend-Vens/" o "Frontend-Vens/".
#
# Construir:
#   docker compose -f docker-compose.prod.yml build
#
# O manualmente, desde la carpeta padre:
#   docker build -f Backend-Vens/docker/prod/Dockerfile -t vens-app .
# =============================================================================


# =============================================================================
# ETAPA 1 — Compilar la SPA de React
# =============================================================================
# Vite congela las variables VITE_* dentro del JavaScript en el momento del
# build, no cuando el contenedor arranca. Por eso la URL de la API se pasa
# aquí como argumento y no como variable de entorno del contenedor.
#
# El valor por defecto es una ruta RELATIVA (/api/v1): como el frontend y el
# backend ahora salen por el mismo Nginx y el mismo dominio, el navegador
# resuelve /api/v1 contra el host que esté sirviendo la página. Así la misma
# imagen funciona en localhost, en un dominio de pruebas y en el definitivo
# sin reconstruirse, y además desaparece el CORS.
# =============================================================================
FROM node:22-alpine AS spa

ARG VITE_API_BASE_URL=/api/v1
ENV VITE_API_BASE_URL=${VITE_API_BASE_URL}

WORKDIR /spa

# Primero solo los manifiestos de dependencias: mientras package.json no
# cambie, Docker reutiliza la capa del npm ci y el build es mucho más rápido.
COPY Frontend-Vens/package.json Frontend-Vens/package-lock.json ./
RUN npm ci

COPY Frontend-Vens/ ./

# El .env del frontend queda fuera de la imagen (ver Dockerfile.dockerignore)
# a propósito: si estuviera, competiría con el ARG de arriba y el bundle
# podría terminar apuntando a http://localhost:8000 en producción.
RUN npm run build


# =============================================================================
# ETAPA 2 — Base de PHP con las extensiones que Laravel necesita
# =============================================================================
# Se define una sola vez y la reutilizan la etapa de Composer y la final.
# Así las extensiones se compilan una única vez y la versión de PHP con la
# que se resuelven las dependencias es exactamente la misma con la que se
# ejecutan (evita sorpresas de "platform requirements").
# =============================================================================
FROM php:8.4-fpm AS php-base

ENV DEBIAN_FRONTEND=noninteractive

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
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
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
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*


# =============================================================================
# ETAPA 3 — Dependencias de PHP (Composer)
# =============================================================================
FROM php-base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Igual que con npm: primero los manifiestos, para aprovechar la caché.
# --no-scripts porque los scripts de Laravel (package:discover) necesitan el
# código completo, que todavía no está copiado.
COPY Backend-Vens/src/composer.json Backend-Vens/src/composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

COPY Backend-Vens/src/ ./

# El autoloader optimizado (classmap authoritative) evita que PHP busque
# clases en disco en cada petición. Es una de las mejoras de rendimiento
# más grandes y gratuitas que hay en producción.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && composer run-script post-autoload-dump --no-dev || true


# =============================================================================
# ETAPA 4 — Imagen final: Nginx + PHP-FPM + SPA + Laravel
# =============================================================================
FROM php-base AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# ── Laravel, ya con su vendor/ y su autoloader optimizado ────────────────────
COPY --from=vendor --chown=www-data:www-data /app /var/www/html

# ── SPA compilada ────────────────────────────────────────────────────────────
COPY --from=spa --chown=www-data:www-data /spa/dist /var/www/spa

# ── Configuración ────────────────────────────────────────────────────────────
COPY Backend-Vens/docker/prod/php.ini        /usr/local/etc/php/conf.d/zz-vens.ini
COPY Backend-Vens/docker/prod/nginx.conf     /etc/nginx/conf.d/default.conf
COPY Backend-Vens/docker/prod/supervisord.conf /etc/supervisor/conf.d/vens.conf
COPY Backend-Vens/docker/prod/entrypoint.sh  /usr/local/bin/entrypoint

# Nginx trae un server block por defecto en el puerto 80 que chocaría con el
# nuestro; en Debian vive en sites-enabled, así que se quita.
RUN rm -f /etc/nginx/sites-enabled/default \
    && chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/framework/cache/data \
                storage/framework/sessions \
                storage/framework/views \
                storage/app/public \
                storage/logs \
                bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

# El healthcheck usa /up, la ruta de salud que Laravel registra en
# bootstrap/app.php. Que responda significa que Nginx, PHP-FPM y el
# framework están los tres en pie, no solo el contenedor.
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf", "-n"]
