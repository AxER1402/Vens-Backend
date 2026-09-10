# =============================================================================
# Makefile — Comandos abreviados para el entorno Docker
# Centro Médico de Flebología — Backend Vens
# =============================================================================
# El Makefile permite ejecutar comandos complejos con aliases cortos.
#
# Uso:
#   make <comando>
#
# Ejemplos:
#   make up          ← Levantar el entorno completo
#   make shell       ← Abrir terminal en el contenedor PHP
#   make migrate     ← Ejecutar migraciones de la BD
#
# Requisito: tener 'make' instalado (viene por defecto en Mac con Xcode Tools)
#   Verificar: make --version
# =============================================================================

# Nombre del servicio PHP principal en docker-compose.yml
PHP_SERVICE = app

# Binario de Docker Compose (compatible con v1 y v2)
COMPOSE = docker-compose

# Compose de producción: imagen unificada (Frontend + Backend en un contenedor).
# El --env-file es obligatorio: sin él, Compose no puede resolver las variables
# que docker-compose.prod.yml interpola (contraseñas de MySQL y Redis).
COMPOSE_PROD = docker compose --env-file .env.prod -f docker-compose.prod.yml

# Colores para output legible en terminal
GREEN  = \033[0;32m
YELLOW = \033[1;33m
RED    = \033[0;31m
BLUE   = \033[0;34m
NC     = \033[0m # Sin color

# .PHONY indica que estos no son archivos reales, son comandos
.PHONY: help up down restart build shell logs ps clean migrate \
        migrate-fresh seed tinker test cache-clear key-generate \
        composer-install artisan queue-work logs-vite npm restart-vite \
        prod-build prod-up prod-down prod-logs prod-shell prod-ps prod-key \
        prod-reset prod-seed prod-db

# ── Ayuda ─────────────────────────────────────────────────────────────────────
# Ejecutar solo "make" muestra esta ayuda
help:
	@echo ""
	@echo "$(BLUE)╔══════════════════════════════════════════════════════════════╗$(NC)"
	@echo "$(BLUE)║     Centro Médico Flebología — Comandos Docker + Laravel    ║$(NC)"
	@echo "$(BLUE)╚══════════════════════════════════════════════════════════════╝$(NC)"
	@echo ""
	@echo "$(YELLOW)── DOCKER ─────────────────────────────────────────────────────$(NC)"
	@echo "  $(GREEN)make up$(NC)              Levantar todos los servicios (detached)"
	@echo "  $(GREEN)make down$(NC)            Detener y eliminar contenedores"
	@echo "  $(GREEN)make restart$(NC)         Reiniciar todos los servicios"
	@echo "  $(GREEN)make build$(NC)           Reconstruir imágenes Docker"
	@echo "  $(GREEN)make ps$(NC)              Ver estado de los contenedores"
	@echo "  $(GREEN)make logs$(NC)            Ver logs en tiempo real (todos)"
	@echo "  $(GREEN)make logs-app$(NC)        Ver logs de la aplicación (Nginx + PHP + Vite)"
	@echo "  $(GREEN)make logs-vite$(NC)       Ver solo los logs de Vite (frontend)"
	@echo "  $(GREEN)make logs-mysql$(NC)      Ver logs de MySQL"
	@echo "  $(GREEN)make npm cmd=<cmd>$(NC)   Ejecutar npm en el frontend"
	@echo "  $(GREEN)make restart-vite$(NC)    Reiniciar solo el servidor de Vite"
	@echo ""
	@echo "$(YELLOW)── LARAVEL / ARTISAN ───────────────────────────────────────────$(NC)"
	@echo "  $(GREEN)make shell$(NC)           Abrir terminal en el contenedor PHP"
	@echo "  $(GREEN)make artisan cmd=<cmd>$(NC)  Ejecutar comando Artisan"
	@echo "  $(GREEN)make key-generate$(NC)    Generar APP_KEY de Laravel"
	@echo "  $(GREEN)make migrate$(NC)         Ejecutar migraciones pendientes"
	@echo "  $(GREEN)make migrate-fresh$(NC)   Reset completo + re-ejecutar migraciones"
	@echo "  $(GREEN)make seed$(NC)            Ejecutar seeders (datos de prueba)"
	@echo "  $(GREEN)make migrate-seed$(NC)    Reset + migraciones + seeders"
	@echo "  $(GREEN)make tinker$(NC)          Abrir REPL interactivo de Laravel"
	@echo "  $(GREEN)make cache-clear$(NC)     Limpiar todos los cachés de Laravel"
	@echo "  $(GREEN)make storage-link$(NC)    Crear symlink para archivos públicos"
	@echo "  $(GREEN)make queue-work$(NC)      Iniciar worker de colas"
	@echo ""
	@echo "$(YELLOW)── COMPOSER ────────────────────────────────────────────────────$(NC)"
	@echo "  $(GREEN)make composer-install$(NC)  Instalar dependencias PHP"
	@echo "  $(GREEN)make composer-update$(NC)   Actualizar dependencias PHP"
	@echo ""
	@echo "$(YELLOW)── TESTS ───────────────────────────────────────────────────────$(NC)"
	@echo "  $(GREEN)make test$(NC)            Ejecutar todos los tests"
	@echo "  $(GREEN)make test-filter f=<NombreTest>$(NC)  Ejecutar test específico"
	@echo ""
	@echo "$(YELLOW)── UTILIDADES ──────────────────────────────────────────────────$(NC)"
	@echo "  $(GREEN)make clean$(NC)           Eliminar contenedores, volúmenes e imágenes"
	@echo "  $(GREEN)make install$(NC)         Configuración inicial completa (primera vez)"
	@echo ""

# ══════════════════════════════════════════════════════════════════════════════
# COMANDOS DOCKER
# ══════════════════════════════════════════════════════════════════════════════

# Levantar todos los servicios en background (-d = detached mode)
# Comando real: docker-compose up -d --remove-orphans
up:
	@echo "$(GREEN)▶ Levantando servicios Docker...$(NC)"
	$(COMPOSE) up -d --remove-orphans
	@echo ""
	@echo "$(GREEN)✔ Servicios activos:$(NC)"
	@echo "  🌐 Aplicación:    http://localhost:$${NGINX_PORT:-8000}"
	@echo "  🔌 API:           http://localhost:$${NGINX_PORT:-8000}/api/v1"
	@echo "  🗄  phpMyAdmin:   http://localhost:$${PMA_PORT:-8080}"
	@echo ""
	@echo "$(YELLOW)La pantalla y la API salen por el mismo puerto. El 5173 ya no se usa.$(NC)"
	@echo "$(YELLOW)El primer arranque instala vendor/ y node_modules: make logs-app$(NC)"
	@echo ""

# Detener y eliminar los contenedores (los volúmenes de datos se conservan)
# Comando real: docker-compose down
down:
	@echo "$(RED)■ Deteniendo servicios...$(NC)"
	$(COMPOSE) down
	@echo "$(GREEN)✔ Servicios detenidos$(NC)"

# Reiniciar todos los servicios
# Comando real: docker-compose restart
restart:
	@echo "$(YELLOW)↺ Reiniciando servicios...$(NC)"
	$(COMPOSE) restart
	@echo "$(GREEN)✔ Servicios reiniciados$(NC)"

# Reconstruir las imágenes Docker desde cero
# Usar cuando cambia el Dockerfile o la configuración de PHP
# Comando real: docker-compose build --no-cache
build:
	@echo "$(YELLOW)🔨 Construyendo imágenes Docker...$(NC)"
	$(COMPOSE) build --no-cache
	@echo "$(GREEN)✔ Imágenes construidas$(NC)"

# Ver estado de los contenedores
# Comando real: docker-compose ps
ps:
	$(COMPOSE) ps

# Ver logs en tiempo real de TODOS los servicios
# Ctrl+C para salir
# Comando real: docker-compose logs -f
logs:
	$(COMPOSE) logs -f

# Logs de la aplicación: Nginx, PHP-FPM y Vite salen mezclados, cada línea
# con el prefijo del proceso que la escribió.
logs-app:
	$(COMPOSE) logs -f $(PHP_SERVICE)

# Solo lo que escribe Vite, para cuando el problema es del frontend.
# Supervisor prefija cada línea con el nombre del proceso que la produjo.
logs-vite:
	$(COMPOSE) logs -f $(PHP_SERVICE) | grep --line-buffered -i "vite"

# Logs solo de MySQL
logs-mysql:
	$(COMPOSE) logs -f mysql

# ══════════════════════════════════════════════════════════════════════════════
# COMANDOS LARAVEL / ARTISAN
# ══════════════════════════════════════════════════════════════════════════════

# Abrir terminal bash interactiva dentro del contenedor PHP
# Desde ahí puedes ejecutar cualquier comando PHP, Artisan o Composer
# Comando real: docker-compose exec app bash
shell:
	@echo "$(BLUE)Entrando al contenedor PHP...$(NC)"
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) bash

# Ejecutar cualquier comando Artisan
# Uso: make artisan cmd="route:list"
# Uso: make artisan cmd="make:model Paciente -m"
# Comando real: docker-compose exec app php artisan <cmd>
artisan:
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) php artisan $(cmd)

# Ejecutar npm en el frontend, que ahora vive en /app dentro del mismo
# contenedor. Ejemplos:
#   make npm cmd="install axios"
#   make npm cmd="run lint"
#
# Después de instalar un paquete hay que reiniciar Vite para que lo tome:
#   make restart-vite
npm:
	$(COMPOSE) exec -u www-data -w /app $(PHP_SERVICE) npm $(cmd)

# Reiniciar solo Vite, sin tirar el contenedor entero ni perder la sesión de
# la base de datos. Útil tras instalar un paquete o tocar vite.config.js.
restart-vite:
	$(COMPOSE) exec $(PHP_SERVICE) supervisorctl restart vite

# Generar la clave de cifrado de Laravel (APP_KEY en .env)
# IMPORTANTE: Ejecutar esto después de copiar .env.example a .env
# Comando real: docker-compose exec app php artisan key:generate
key-generate:
	@echo "$(YELLOW)🔑 Generando APP_KEY...$(NC)"
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) php artisan key:generate
	@echo "$(GREEN)✔ APP_KEY generada en .env$(NC)"

# Ejecutar migraciones pendientes
# Las migraciones crean/modifican las tablas de la base de datos
# Comando real: docker-compose exec app php artisan migrate
migrate:
	@echo "$(YELLOW)🗃  Ejecutando migraciones...$(NC)"
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) php artisan migrate
	@echo "$(GREEN)✔ Migraciones completadas$(NC)"

# Reset completo de la BD y re-ejecutar TODAS las migraciones desde cero
# ⚠ PRECAUCIÓN: Elimina TODOS los datos de la base de datos
# Comando real: docker-compose exec app php artisan migrate:fresh
migrate-fresh:
	@echo "$(RED)⚠ ADVERTENCIA: Esto eliminará TODOS los datos de la BD$(NC)"
	@read -p "¿Continuar? [s/N]: " confirm && [ "$$confirm" = "s" ] || exit 1
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) php artisan migrate:fresh
	@echo "$(GREEN)✔ Base de datos resetada$(NC)"

# Ejecutar seeders (datos de prueba/semilla)
# Comando real: docker-compose exec app php artisan db:seed
seed:
	@echo "$(YELLOW)🌱 Ejecutando seeders...$(NC)"
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) php artisan db:seed
	@echo "$(GREEN)✔ Seeders ejecutados$(NC)"

# Reset + migraciones + seeders (setup completo de desarrollo)
migrate-seed:
	@echo "$(RED)⚠ Reset completo de BD con seeders$(NC)"
	@read -p "¿Continuar? [s/N]: " confirm && [ "$$confirm" = "s" ] || exit 1
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) php artisan migrate:fresh --seed
	@echo "$(GREEN)✔ BD configurada con datos de prueba$(NC)"

# Abrir Tinker: REPL interactivo de Laravel (como una consola PHP con acceso a tu app)
# Útil para probar consultas Eloquent, modelos, etc.
# Uso: Paciente::all()   o   App\Models\Cita::where('estado', 'pendiente')->get()
# Comando real: docker-compose exec app php artisan tinker
tinker:
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) php artisan tinker

# Limpiar todos los cachés de Laravel
# Útil cuando los cambios no se reflejan en la aplicación
# Comando real: ejecuta múltiples comandos artisan
cache-clear:
	@echo "$(YELLOW)🧹 Limpiando cachés...$(NC)"
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) php artisan cache:clear
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) php artisan config:clear
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) php artisan route:clear
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) php artisan view:clear
	@echo "$(GREEN)✔ Todos los cachés limpiados$(NC)"

# Crear el symlink de storage (necesario para servir archivos públicos)
# Solo se necesita ejecutar una vez al configurar el proyecto
# Comando real: docker-compose exec app php artisan storage:link
storage-link:
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) php artisan storage:link
	@echo "$(GREEN)✔ Storage symlink creado$(NC)"

# Iniciar el worker de colas en primer plano
# Las colas procesan emails, notificaciones, y otros trabajos en background
# Comando real: docker-compose exec app php artisan queue:work redis
queue-work:
	@echo "$(BLUE)▶ Iniciando queue worker (Ctrl+C para detener)...$(NC)"
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) php artisan queue:work redis --verbose

# ══════════════════════════════════════════════════════════════════════════════
# COMANDOS COMPOSER
# ══════════════════════════════════════════════════════════════════════════════

# Instalar dependencias PHP desde composer.json
# Necesario después de clonar el proyecto o al agregar nuevos paquetes
# Comando real: docker-compose exec app composer install
composer-install:
	@echo "$(YELLOW)📦 Instalando dependencias Composer...$(NC)"
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) composer install
	@echo "$(GREEN)✔ Dependencias instaladas$(NC)"

# Actualizar dependencias PHP
# Comando real: docker-compose exec app composer update
composer-update:
	@echo "$(YELLOW)📦 Actualizando dependencias Composer...$(NC)"
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) composer update
	@echo "$(GREEN)✔ Dependencias actualizadas$(NC)"

# ══════════════════════════════════════════════════════════════════════════════
# TESTS
# ══════════════════════════════════════════════════════════════════════════════

# Ejecutar toda la suite de tests
# Comando real: docker-compose exec app php artisan test
test:
	@echo "$(YELLOW)🧪 Ejecutando tests...$(NC)"
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) php artisan test

# Ejecutar un test específico por nombre
# Uso: make test-filter f=PacienteTest
test-filter:
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) php artisan test --filter=$(f)

# ══════════════════════════════════════════════════════════════════════════════
# UTILIDADES
# ══════════════════════════════════════════════════════════════════════════════

# Eliminar TODOS los recursos Docker del proyecto (contenedores + volúmenes + imágenes)
# ⚠ Usar solo cuando quieres empezar desde cero
clean:
	@echo "$(RED)⚠ Esto eliminará contenedores, volúmenes E IMÁGENES del proyecto$(NC)"
	@read -p "¿Continuar? [s/N]: " confirm && [ "$$confirm" = "s" ] || exit 1
	$(COMPOSE) down -v --rmi local
	@echo "$(GREEN)✔ Limpieza completa$(NC)"

# Configuración inicial completa (ejecutar solo la primera vez)
# Hace todo el setup de manera automática y ordenada
install:
	@echo "$(BLUE)╔══════════════════════════════════════╗$(NC)"
	@echo "$(BLUE)║  Configuración inicial del proyecto  ║$(NC)"
	@echo "$(BLUE)╚══════════════════════════════════════╝$(NC)"
	@echo ""
	@echo "$(YELLOW)[1/6] Copiando .env.example a .env...$(NC)"
	@test -f src/.env || cp src/.env.example src/.env
	@echo "$(YELLOW)[2/6] Construyendo imágenes Docker...$(NC)"
	$(COMPOSE) build
	@echo "$(YELLOW)[3/6] Levantando servicios...$(NC)"
	$(COMPOSE) up -d
	@echo "$(YELLOW)[4/6] Esperando que MySQL esté listo...$(NC)"
	@sleep 15
	@echo "$(YELLOW)[5/6] Generando APP_KEY...$(NC)"
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) php artisan key:generate
	@echo "$(YELLOW)[6/6] Ejecutando migraciones...$(NC)"
	$(COMPOSE) exec -u www-data $(PHP_SERVICE) php artisan migrate
	@echo ""
	@echo "$(GREEN)╔══════════════════════════════════════════╗$(NC)"
	@echo "$(GREEN)║  ✔ Proyecto configurado exitosamente!    ║$(NC)"
	@echo "$(GREEN)║                                          ║$(NC)"
	@echo "$(GREEN)║  🌐 API:        http://localhost:8000    ║$(NC)"
	@echo "$(GREEN)║  🗄  DB Admin:  http://localhost:8080    ║$(NC)"
	@echo "$(GREEN)╚══════════════════════════════════════════╝$(NC)"

# =============================================================================
# PRODUCCIÓN — Imagen unificada (Frontend + Backend en un solo contenedor)
# =============================================================================
# Estos targets NO tocan el entorno de desarrollo: usan otro archivo de
# Compose, otra red y otros volúmenes. Se pueden tener los dos a la vez.
#
# Antes de la primera vez:
#   cp .env.prod.example .env.prod   ← y editarlo con los valores reales
# =============================================================================

# Construir la imagen unificada.
# Tarda varios minutos la primera vez: compila las extensiones de PHP,
# instala las dependencias de Composer y compila la SPA de React.
prod-build:
	@test -f .env.prod || { echo "$(RED)✖ Falta .env.prod — cópielo de .env.prod.example$(NC)"; exit 1; }
	@echo "$(BLUE)Construyendo imagen de producción (Frontend + Backend)...$(NC)"
	$(COMPOSE_PROD) build
	@echo "$(GREEN)✔ Imagen construida$(NC)"

# Levantar producción
prod-up:
	@test -f .env.prod || { echo "$(RED)✖ Falta .env.prod — cópielo de .env.prod.example$(NC)"; exit 1; }
	$(COMPOSE_PROD) up -d
	@echo "$(GREEN)✔ Producción levantada$(NC)"
	@echo "$(YELLOW)Las migraciones y las cachés se aplican solas al arrancar.$(NC)"
	@echo "$(YELLOW)Siga el arranque con: make prod-logs$(NC)"

# Detener producción (los volúmenes con los datos se conservan)
prod-down:
	$(COMPOSE_PROD) down
	@echo "$(GREEN)✔ Producción detenida (los datos se conservan)$(NC)"

# Logs de la aplicación (Nginx, PHP-FPM y Laravel salen todos por aquí)
prod-logs:
	$(COMPOSE_PROD) logs -f app

# Estado de los tres contenedores
prod-ps:
	$(COMPOSE_PROD) ps

# Terminal dentro del contenedor de la aplicación
prod-shell:
	$(COMPOSE_PROD) exec app bash

# Generar una APP_KEY para pegar en .env.prod.
# Se ejecuta con la imagen suelta, sin levantar nada, y saltándose el
# entrypoint (--entrypoint php): la clave hace falta ANTES del primer
# arranque, porque el entrypoint se niega a seguir sin ella.
prod-key:
	@echo "$(YELLOW)Copie esta línea completa en APP_KEY de .env.prod:$(NC)"
	@docker run --rm --entrypoint php vens-app:latest artisan key:generate --show

# Consola de MySQL de producción.
# Es la única vía: docker-compose.prod.yml no publica el 3306, así que desde
# fuera del servidor no hay a dónde conectarse. La contraseña se lee de las
# variables del propio contenedor y nunca aparece en la línea de comandos.
prod-db:
	$(COMPOSE_PROD) exec mysql sh -c 'exec mysql -u root -p"$$MYSQL_ROOT_PASSWORD" "$$MYSQL_DATABASE"'

# Sembrar los catálogos de producción sin borrar nada.
# Hace falta explícitamente porque el entrypoint migra pero NO siembra: una base
# recién creada tiene las tablas vacías, y sin roles ni usuarios no hay forma de
# iniciar sesión. Los seeders de catálogo son idempotentes; PatientSeeder no,
# así que repetir esto duplica los cuatro pacientes de ejemplo.
prod-seed:
	@test -f .env.prod || { echo "$(RED)✖ Falta .env.prod — cópielo de .env.prod.example$(NC)"; exit 1; }
	@echo "$(YELLOW)🌱 Sembrando la base de producción...$(NC)"
	$(COMPOSE_PROD) exec -u www-data app php artisan db:seed --force
	@echo "$(GREEN)✔ Catálogos sembrados$(NC)"

# Devolver producción al estado recién instalado, para probar sin dejar rastro.
# ⚠ PRECAUCIÓN: Elimina TODOS los datos de PRODUCCIÓN
#
# No basta con vaciar la base: hay tres rastros que viven fuera de ella.
#   1. Los archivos subidos (mapeo venoso, avatares, logo del membrete) están en
#      el volumen vens_prod_storage y las cascadas de MySQL no los tocan.
#   2. Los ajustes cacheados y los contadores de throttle viven en Redis.
#   3. Sin --seed la base queda sin roles ni usuarios, o sea, sin login.
#
# Se limpia la caché con cache:clear y no con FLUSHALL de Redis: basta, y así no
# se tumban de paso las sesiones ni nada más que comparta la instancia.
prod-reset:
	@test -f .env.prod || { echo "$(RED)✖ Falta .env.prod — cópielo de .env.prod.example$(NC)"; exit 1; }
	@echo "$(RED)⚠ ADVERTENCIA: Esto eliminará TODOS los datos de PRODUCCIÓN$(NC)"
	@read -p "¿Continuar? [s/N]: " confirm && [ "$$confirm" = "s" ] || exit 1
	@echo "$(YELLOW)🗃  Vaciando la base y volviendo a sembrar...$(NC)"
	$(COMPOSE_PROD) exec -u www-data app php artisan migrate:fresh --seed --force
	@echo "$(YELLOW)🗑  Borrando los archivos subidos...$(NC)"
	$(COMPOSE_PROD) exec -u www-data app sh -c 'rm -rf /var/www/html/storage/app/public/mapeos-venosos /var/www/html/storage/app/public/avatares /var/www/html/storage/app/public/ajustes'
	@echo "$(YELLOW)🧹 Limpiando la caché...$(NC)"
	$(COMPOSE_PROD) exec -u www-data app php artisan cache:clear
	@echo "$(GREEN)✔ Producción de vuelta en su estado inicial$(NC)"
	@echo "$(YELLOW)Quedan los 4 usuarios y 4 pacientes de ejemplo del seeder.$(NC)"
