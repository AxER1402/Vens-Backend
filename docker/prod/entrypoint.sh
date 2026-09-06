#!/bin/sh
# =============================================================================
# entrypoint — Lo que ocurre cada vez que arranca el contenedor
# Centro Médico de Flebología — Vens (producción)
# =============================================================================
# Orden:
#   1. Preparar los directorios que Laravel necesita escribir.
#   2. Esperar a que MySQL acepte conexiones.
#   3. Migrar la base de datos.
#   4. Enlazar storage y cachear configuración, rutas y vistas.
#   5. Ceder el control a Supervisor (Nginx + PHP-FPM).
#
# Si cualquiera de los pasos falla, el contenedor muere en vez de quedarse
# sirviendo a medias: es preferible un despliegue que no arranca a uno que
# arranca roto y lo descubre el usuario.
# =============================================================================
set -e

cd /var/www/html

# Las tareas de Artisan se ejecutan como www-data, no como root: si se
# ejecutaran como root, los archivos de caché quedarían con un dueño que
# PHP-FPM (que sí corre como www-data) no podría sobrescribir después.
artisan() {
    su -s /bin/sh www-data -c "php artisan $*"
}

echo "[vens] Preparando directorios de escritura..."
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/app/public \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# ── 1. Comprobaciones de configuración ───────────────────────────────────────
if [ -z "${APP_KEY}" ]; then
    echo "[vens] ERROR: APP_KEY está vacío."
    echo "[vens] Genere una con:  docker compose -f docker-compose.prod.yml run --rm app php artisan key:generate --show"
    echo "[vens] y póngala en el archivo .env.prod."
    exit 1
fi

if [ "${APP_DEBUG}" = "true" ]; then
    echo "[vens] AVISO: APP_DEBUG=true en producción. Los errores mostrarán rutas"
    echo "[vens] del servidor y datos de conexión. Debería ser false."
fi

# ── 2. Esperar a la base de datos ────────────────────────────────────────────
# depends_on con healthcheck ya lo cubre en docker-compose, pero esto hace
# que la imagen funcione igual fuera de Compose (Swarm, Kubernetes, un
# docker run suelto) donde ese orden no está garantizado.
DB_HOST="${DB_HOST:-mysql}"
DB_PORT="${DB_PORT:-3306}"

echo "[vens] Esperando a MySQL en ${DB_HOST}:${DB_PORT}..."
intentos=0
until php -r '$c=@fsockopen(getenv("DB_HOST"), (int) getenv("DB_PORT"), $e, $s, 2); exit($c ? 0 : 1);'; do
    intentos=$((intentos + 1))
    if [ "$intentos" -ge 60 ]; then
        echo "[vens] ERROR: MySQL no respondió tras 2 minutos."
        exit 1
    fi
    sleep 2
done
echo "[vens] MySQL responde."

# ── 3. Migraciones ───────────────────────────────────────────────────────────
# --force porque Artisan pide confirmación interactiva al migrar en
# producción, y aquí no hay nadie para confirmarla.
#
# Se puede desactivar con RUN_MIGRATIONS=false, que es lo que conviene el día
# que haya más de una réplica del contenedor: entonces las migraciones se
# lanzan una sola vez, a mano, antes de desplegar.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[vens] Aplicando migraciones..."
    artisan migrate --force
else
    echo "[vens] RUN_MIGRATIONS=false, se omiten las migraciones."
fi

# ── 4. Enlace de storage y cachés ────────────────────────────────────────────
# public/storage → storage/app/public. Nginx sirve /storage por su cuenta con
# un alias, pero el enlace sigue haciendo falta porque los generadores de PDF
# y Word localizan el logo del membrete con public_path('storage/...').
echo "[vens] Enlazando storage..."
artisan storage:link --force

# Las cachés se regeneran en cada arranque, no se copian en la imagen: así
# recogen las variables de entorno reales de este contenedor. config:cache
# debe ir antes que las demás porque las otras dependen de la configuración.
echo "[vens] Cacheando configuración, rutas y vistas..."
artisan config:cache
artisan route:cache
artisan view:cache

echo "[vens] Listo. Arrancando Nginx y PHP-FPM."

exec "$@"
