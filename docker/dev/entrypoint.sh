#!/bin/sh
# =============================================================================
# entrypoint — Arranque del contenedor único de DESARROLLO
# Centro Médico de Flebología — Vens
# =============================================================================
# El código entra por volúmenes, no dentro de la imagen. Eso significa que las
# dependencias (vendor/ y node_modules/) viven en volúmenes con nombre que la
# primera vez están vacíos, así que aquí se instalan si faltan.
#
# La primera vez esto tarda varios minutos. Las siguientes, segundos: los
# volúmenes ya están poblados y solo se comprueba que existan.
# =============================================================================
set -e

como_www() {
    su -s /bin/sh www-data -c "$1"
}

echo "[vens] ──────────────────────────────────────────────"

# ── Laravel: .env ────────────────────────────────────────────────────────────
if [ ! -f /var/www/html/.env ]; then
    echo "[vens] No hay src/.env, se copia de src/.env.example"
    cp /var/www/html/.env.example /var/www/html/.env
    chown www-data:www-data /var/www/html/.env
fi

# ── Laravel: directorios de escritura ────────────────────────────────────────
mkdir -p \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/app/public \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# ── Laravel: dependencias ────────────────────────────────────────────────────
# vendor/ es un volumen con nombre (por rendimiento en Mac), así que la
# primera vez llega vacío aunque el composer.json esté ahí.
chown www-data:www-data /var/www/html/vendor
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "[vens] Instalando dependencias de PHP (composer install)..."
    echo "[vens] La primera vez tarda unos minutos."
    como_www "cd /var/www/html && composer install --no-interaction --prefer-dist"
fi

# ── Laravel: APP_KEY ─────────────────────────────────────────────────────────
if ! grep -q "^APP_KEY=base64:" /var/www/html/.env 2>/dev/null; then
    echo "[vens] Generando APP_KEY..."
    como_www "cd /var/www/html && php artisan key:generate"
fi

# ── Frontend: dependencias ───────────────────────────────────────────────────
# node_modules/ también es un volumen con nombre: mantenerlo fuera del
# directorio montado desde el Mac es lo que evita que Vite vaya lentísimo.
chown www-data:www-data /app/node_modules
if [ ! -d /app/node_modules/vite ]; then
    echo "[vens] Instalando dependencias del frontend (npm install)..."
    echo "[vens] La primera vez tarda unos minutos."
    como_www "cd /app && npm install --no-audit --no-fund"
fi

echo "[vens] Listo. Arrancando Nginx, PHP-FPM y Vite."
echo "[vens] Aplicación: http://localhost:${NGINX_PORT:-8000}"
echo "[vens] ──────────────────────────────────────────────"

exec "$@"
