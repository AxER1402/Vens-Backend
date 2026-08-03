# 🏥 Backend — Centro Médico Vens (Flebología)

Backend API REST desarrollado con **Laravel 13** y contenedorizado con **Docker**.
Sistema de gestión para un centro médico especializado en Flebología.

> ✅ **Estado actual:** Entorno completamente funcional y verificado. `HTTP 200` en `http://localhost:8000`

---

## 📋 Tabla de Contenidos

1. [Requisitos](#requisitos)
2. [Stack Tecnológico](#stack-tecnológico)
3. [Arquitectura y Contenedores](#arquitectura-y-contenedores)
4. [Configuración Inicial](#configuración-inicial)
5. [Comandos Documentados](#comandos-documentados)
6. [Módulos del Sistema](#módulos-del-sistema)
7. [Estructura del Proyecto](#estructura-del-proyecto)
8. [API Endpoints](#api-endpoints)
9. [Solución de Problemas](#solución-de-problemas)
10. [Historial de Cambios](#historial-de-cambios)

---

## ✅ Requisitos

Antes de comenzar, asegúrate de tener instalado:

| Herramienta | Versión mínima | Verificar |
|---|---|---|
| **Docker Desktop** | 4.x | `docker --version` |
| **Docker Compose** | 2.x | `docker-compose --version` |
| **Make** | Cualquiera | `make --version` |

> **Nota:** NO necesitas tener PHP, Composer ni MySQL instalados localmente.
> Todo se ejecuta dentro de los contenedores Docker.

---

## 🛠 Stack Tecnológico

| Componente | Tecnología | Versión | Puerto |
|---|---|---|---|
| **Framework PHP** | Laravel | **13.19.0** | — |
| **Lenguaje** | PHP | **8.4-FPM** | 9000 (interno) |
| **Servidor Web** | Nginx | 1.25 Alpine | **8000** → 80 |
| **Base de Datos** | MySQL | 8.0 | **3306** |
| **Caché / Colas** | Redis | 7.2 Alpine | **6379** |
| **Admin BD** | phpMyAdmin | Latest | **8080** |
| **Autenticación** | Laravel Sanctum | (pendiente instalar) | — |

---

## 🏗 Arquitectura y Contenedores

### ¿Por qué 5 contenedores?

Cada contenedor tiene una **única responsabilidad** (principio de separación de concerns).
Esto permite escalar, actualizar o reiniciar cada componente de forma independiente.

```
┌──────────────────────────────────────────────────────────────────┐
│                  Docker Network: vens_network                    │
│                                                                  │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │  ENTRADA: Tu navegador / Frontend React                  │    │
│  └────────────────────────┬────────────────────────────────┘    │
│                           │ HTTP :8000                           │
│                           ▼                                      │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │  1. vens_nginx  (Nginx 1.25)                             │    │
│  │     Servidor web — recibe peticiones, sirve assets       │    │
│  └────────────────────────┬────────────────────────────────┘    │
│                           │ FastCGI :9000                        │
│                           ▼                                      │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │  2. vens_app  (PHP 8.4 FPM + Laravel 13)                 │    │
│  │     Lógica de negocio — procesa rutas, modelos, API      │    │
│  └───────────┬──────────────────────┬───────────────────────┘   │
│              │ SQL :3306            │ Redis :6379                │
│              ▼                      ▼                            │
│  ┌───────────────────┐  ┌──────────────────────┐               │
│  │  3. vens_mysql    │  │  4. vens_redis        │               │
│  │  (MySQL 8.0)      │  │  (Redis 7.2)          │               │
│  │  Base de datos    │  │  Caché + Colas        │               │
│  │  principal        │  │  de trabajos          │               │
│  └───────────────────┘  └──────────────────────┘               │
│              │                                                   │
│              ▼                                                   │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │  5. vens_phpmyadmin  (phpMyAdmin)                        │    │
│  │     Interfaz web para administrar MySQL :8080            │    │
│  └─────────────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────────────┘
```

### Descripción de cada contenedor

| Contenedor | Rol | Acceso | ¿Para qué sirve? |
|---|---|---|---|
| **vens_nginx** | Servidor Web | `localhost:8000` | Recibe todas las peticiones HTTP del exterior. Si el archivo es estático (imagen, CSS) lo sirve directamente. Si es PHP, lo reenvía a `vens_app`. |
| **vens_app** | Aplicación PHP | Solo interno | Ejecuta el código Laravel. Procesa las rutas de la API, valida datos, ejecuta lógica de negocio, consulta la BD. |
| **vens_mysql** | Base de Datos | `localhost:3306` | Almacena todos los datos: pacientes, citas, médicos, diagnósticos. Motor relacional SQL. |
| **vens_redis** | Caché y Colas | `localhost:6379` | Almacena datos temporales en memoria (muy rápido). Usado para caché de consultas frecuentes y para procesar emails/notificaciones en segundo plano. |
| **vens_phpmyadmin** | Admin BD | `localhost:8080` | Interfaz visual para explorar y gestionar la base de datos MySQL sin necesidad de usar la terminal. |

---

## 🚀 Configuración Inicial

Sigue estos pasos **en orden** para configurar el entorno por primera vez:

### Paso 1 — Clonar o acceder al proyecto

```bash
# Si usas Git, clonar el repositorio:
git clone <url-del-repositorio> .

# O simplemente navegar al directorio del proyecto:
cd "Backend-Vens"
```

**¿Qué hace?** Accede al directorio donde están todos los archivos de configuración.

---

### Paso 2 — Instalar Laravel dentro del contenedor

```bash
# Construir las imágenes Docker primero
docker-compose build

# Levantar SOLO el servicio PHP temporalmente
docker-compose run --rm app composer create-project laravel/laravel .
```

**¿Qué hace?**
- `docker-compose build`: Lee el `Dockerfile` y construye la imagen PHP personalizada con todas las extensiones necesarias (pdo_mysql, gd, redis, etc.)
- `composer create-project laravel/laravel .`: Descarga e instala Laravel 11 en el directorio `./src/` usando Composer dentro del contenedor

---

### Paso 3 — Copiar variables de entorno

```bash
# Copiar la plantilla al archivo real
cp .env.example src/.env
```

**¿Qué hace?** Crea el archivo `.env` con todas las variables de configuración preconfiguradas para Docker. Este archivo contiene credenciales de base de datos, configuración de Redis, etc.

> ⚠️ **Importante:** El archivo `.env` nunca se sube al repositorio (está en `.gitignore`). Cada desarrollador debe tener su propio `.env`.

---

### Paso 4 — Levantar todos los servicios

```bash
# Opción A: Con Make (recomendado)
make up

# Opción B: Comando directo
docker-compose up -d
```

**¿Qué hace?**
- `up`: Inicia los contenedores
- `-d`: Modo "detached" (background), la terminal queda libre

Docker iniciará 5 contenedores:
1. `vens_app` — PHP 8.3 FPM con Laravel
2. `vens_nginx` — Servidor web
3. `vens_mysql` — Base de datos
4. `vens_redis` — Caché y colas
5. `vens_phpmyadmin` — Administrador de BD

```bash
# Verificar que todos los contenedores están corriendo:
docker-compose ps
# o:
make ps
```

---

### Paso 5 — Generar la clave de la aplicación

```bash
# Opción A: Con Make
make key-generate

# Opción B: Comando directo
docker-compose exec app php artisan key:generate
```

**¿Qué hace?** Genera una clave aleatoria de 32 caracteres en base64 y la escribe en `APP_KEY` del archivo `.env`. Esta clave cifra las sesiones, cookies y datos sensibles de Laravel.

> ⚠️ **Nunca compartir esta clave.** Si se expone, cambiarla inmediatamente con este mismo comando.

---

### Paso 6 — Ejecutar las migraciones

```bash
# Opción A: Con Make
make migrate

# Opción B: Comando directo
docker-compose exec app php artisan migrate
```

**¿Qué hace?** Lee todos los archivos en `src/database/migrations/` y ejecuta los que aún no se han aplicado. Esto crea todas las tablas de la base de datos (users, personal_access_tokens, etc.) en MySQL.

```bash
# Ver el estado de las migraciones:
docker-compose exec app php artisan migrate:status
```

---

### Paso 7 — Crear symlink de storage

```bash
# Opción A: Con Make
make storage-link

# Opción B: Comando directo
docker-compose exec app php artisan storage:link
```

**¿Qué hace?** Crea un enlace simbólico desde `public/storage` hacia `storage/app/public`. Esto permite acceder a los archivos subidos (fotos de pacientes, documentos clínicos) via URL pública.

---

### ✅ Verificación Final

Si todo salió bien, deberías ver:

| URL | Descripción |
|---|---|
| `http://localhost:8000` | API Laravel (página de bienvenida) |
| `http://localhost:8080` | phpMyAdmin (administrador de BD) |

```bash
# Verificar que Laravel responde:
curl http://localhost:8000

# Verificar conexión a la BD:
docker-compose exec app php artisan tinker
# Dentro de Tinker:
# >>> DB::connection()->getPdo()
```

---

## 📖 Comandos Documentados

### Comandos Docker

| Comando | Descripción | Uso |
|---|---|---|
| `make up` | Levantar todos los servicios | Inicio del día de trabajo |
| `make down` | Detener los servicios | Fin del día de trabajo |
| `make restart` | Reiniciar servicios | Después de cambios en config |
| `make build` | Reconstruir imágenes | Después de cambiar Dockerfile |
| `make ps` | Ver estado de contenedores | Diagnóstico |
| `make logs` | Logs en tiempo real | Diagnóstico |
| `make logs-app` | Logs solo de PHP/Laravel | Diagnóstico de errores PHP |
| `make clean` | Eliminar todo (⚠ datos incluidos) | Reset completo |

### Comandos Laravel

| Comando | Descripción | Comando Artisan Real |
|---|---|---|
| `make shell` | Abrir terminal en contenedor | `docker-compose exec app bash` |
| `make key-generate` | Generar APP_KEY | `php artisan key:generate` |
| `make migrate` | Ejecutar migraciones | `php artisan migrate` |
| `make migrate-fresh` | Reset + migraciones | `php artisan migrate:fresh` |
| `make seed` | Datos de prueba | `php artisan db:seed` |
| `make migrate-seed` | Reset + datos de prueba | `php artisan migrate:fresh --seed` |
| `make tinker` | REPL interactivo | `php artisan tinker` |
| `make cache-clear` | Limpiar cachés | `php artisan cache:clear` + más |
| `make storage-link` | Symlink de archivos | `php artisan storage:link` |
| `make queue-work` | Procesar colas | `php artisan queue:work redis` |
| `make test` | Ejecutar tests | `php artisan test` |

### Comandos Avanzados (sin Makefile)

```bash
# ── Crear un nuevo modelo con migración ──────────────────────────────────────
# -m = crear migración, -c = crear controlador, -r = resourceful controller
docker-compose exec app php artisan make:model Paciente -mcr

# ── Crear un controlador API ─────────────────────────────────────────────────
docker-compose exec app php artisan make:controller Api/PacienteController --api

# ── Crear un Request de validación ───────────────────────────────────────────
docker-compose exec app php artisan make:request StorePacienteRequest

# ── Crear un Resource (transformador de respuesta API) ───────────────────────
docker-compose exec app php artisan make:resource PacienteResource

# ── Crear un Seeder ──────────────────────────────────────────────────────────
docker-compose exec app php artisan make:seeder PacienteSeeder

# ── Ver todas las rutas registradas ─────────────────────────────────────────
docker-compose exec app php artisan route:list

# ── Instalar un paquete PHP ──────────────────────────────────────────────────
docker-compose exec app composer require nombre/paquete

# ── Conectarse a MySQL desde terminal ────────────────────────────────────────
docker-compose exec mysql mysql -u vens_user -pvens_password_2024 vens_flebologia

# ── Conectarse a Redis CLI ───────────────────────────────────────────────────
docker-compose exec redis redis-cli -a redis_vens_2024

# ── Ver logs de un contenedor específico ─────────────────────────────────────
docker-compose logs -f --tail=100 app

# ── Copiar archivo del contenedor a tu máquina ───────────────────────────────
docker cp vens_app:/var/www/html/storage/logs/laravel.log ./laravel.log
```

---

## 🏥 Módulos del Sistema

### Módulos Clínicos Planificados

```
src/
└── app/
    ├── Models/
    │   ├── Paciente.php         # Pacientes del centro médico
    │   ├── Medico.php           # Especialistas en flebología
    │   ├── Cita.php             # Agendamiento de citas
    │   ├── Consulta.php         # Registro de consultas médicas
    │   ├── Diagnostico.php      # Diagnósticos (várices, trombosis, etc.)
    │   ├── Tratamiento.php      # Procedimientos médicos
    │   └── User.php             # Usuarios del sistema (roles)
    │
    └── Http/Controllers/Api/
        ├── AuthController.php   # Login, logout, refresh token
        ├── PacienteController.php
        ├── MedicoController.php
        ├── CitaController.php
        ├── ConsultaController.php
        ├── DiagnosticoController.php
        └── TratamientoController.php
```

### Roles de Usuario

| Rol | Permisos |
|---|---|
| **Administrador** | Acceso total al sistema |
| **Médico** | Ver/crear consultas, diagnósticos, tratamientos |
| **Enfermera** | Ver pacientes, registrar signos vitales |
| **Recepcionista** | Gestionar citas y datos de pacientes |

---

## 📁 Estructura del Proyecto

```
Backend-Vens/
├── docker/                     # Configuración Docker
│   ├── php/
│   │   ├── Dockerfile          # Imagen PHP 8.3 personalizada
│   │   └── php.ini             # Configuración PHP
│   ├── nginx/
│   │   └── default.conf        # Configuración del servidor web
│   └── mysql/
│       └── init/
│           └── 01_init.sql     # Script SQL inicial
├── src/                        # ← Código fuente de Laravel (creado con composer)
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/    # Lógica de cada endpoint
│   │   │   ├── Middleware/     # Filtros de peticiones
│   │   │   └── Requests/       # Validaciones
│   │   ├── Models/             # Modelos Eloquent (tablas de BD)
│   │   └── Services/           # Lógica de negocio
│   ├── database/
│   │   ├── migrations/         # Definición de tablas
│   │   ├── factories/          # Generadores de datos falsos
│   │   └── seeders/            # Datos iniciales/de prueba
│   ├── routes/
│   │   ├── api.php             # Rutas de la API REST
│   │   └── web.php             # Rutas web (si aplica)
│   ├── config/                 # Configuración de Laravel
│   ├── storage/                # Archivos, logs, caché
│   └── .env                    # Variables de entorno (NO commitear)
├── docker-compose.yml          # Orquestación de servicios
├── .env.example                # Plantilla de variables (SÍ commitear)
├── Makefile                    # Comandos abreviados
└── README.md                   # Este archivo
```

---

## 🌐 API Endpoints (Planificados)

```
POST   /api/auth/login           # Iniciar sesión
POST   /api/auth/logout          # Cerrar sesión
GET    /api/auth/user            # Usuario autenticado

GET    /api/pacientes            # Listar pacientes
POST   /api/pacientes            # Crear paciente
GET    /api/pacientes/{id}       # Ver paciente
PUT    /api/pacientes/{id}       # Actualizar paciente
DELETE /api/pacientes/{id}       # Eliminar paciente

GET    /api/citas                # Listar citas
POST   /api/citas                # Agendar cita
PUT    /api/citas/{id}           # Actualizar cita
DELETE /api/citas/{id}           # Cancelar cita

GET    /api/consultas            # Listar consultas
POST   /api/consultas            # Registrar consulta

GET    /api/medicos              # Listar médicos
GET    /api/reportes/estadisticas # Estadísticas del centro
```

---

## 🔧 Solución de Problemas

### Error: Puerto ya en uso

```bash
# Ver qué proceso usa el puerto 8000:
lsof -i :8000

# Cambiar el puerto en .env.example antes de levantar:
NGINX_PORT=8001
PMA_PORT=8081
```

### Error: Permiso denegado en storage/

```bash
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### Error: Could not connect to MySQL

```bash
# Verificar que MySQL está saludable:
docker-compose ps mysql

# Ver logs de MySQL:
make logs-mysql

# Esperar a que MySQL termine de inicializar (puede tardar 30-60 segundos)
docker-compose exec app php artisan migrate
```

### Error: Class not found después de agregar clase

```bash
# Regenerar el autoloader de Composer:
docker-compose exec app composer dump-autoload
```

### Limpiar todo y empezar desde cero

```bash
# Detener contenedores y eliminar volúmenes (¡BORRA LOS DATOS!)
make clean

# Volver a configurar desde el Paso 4
make up
make key-generate
make migrate
```

---

## 👥 Equipo de Desarrollo

**Centro Médico Vens — Flebología**
Proyecto de Graduación 2

---

*Documentación generada para el entorno de desarrollo. Actualizar según evolucione el proyecto.*

---

## 📝 Historial de Cambios

Registro completo de todos los cambios y correcciones aplicadas durante la configuración inicial del entorno.

---

### v1.0.0 — Configuración inicial (2026-07-12)

#### 🆕 Archivos creados

| Archivo | Descripción |
|---|---|
| `docker/php/Dockerfile` | Imagen personalizada PHP con extensiones Laravel |
| `docker/php/php.ini` | Configuración PHP optimizada para sistema médico |
| `docker/nginx/default.conf` | Configuración del servidor web Nginx para Laravel |
| `docker/mysql/init/01_init.sql` | Script SQL de inicialización automática de MySQL |
| `docker-compose.yml` | Orquestación de los 5 servicios Docker |
| `.env.example` | Plantilla de variables de entorno completa |
| `Makefile` | 20+ comandos abreviados con documentación |
| `README.md` | Documentación completa del proyecto |

---

#### 🐛 Problemas encontrados y corregidos

**Fix #1 — Faltaba `libicu-dev` en el Dockerfile**
- **Error:** `configure: error: Package requirements (icu-uc >= 50.1) were not met`
- **Causa:** La extensión PHP `intl` (internacionalización) depende de las librerías ICU del sistema. No estaban listadas en el `apt-get install`.
- **Solución:** Agregar `libicu-dev` al bloque de instalación de dependencias del sistema en el `Dockerfile`.
- **Archivo modificado:** `docker/php/Dockerfile`
```dockerfile
# Antes — faltaba esta línea:
+ libicu-dev \
```

---

**Fix #2 — Directorio no vacío al instalar Laravel**
- **Error:** `Project directory "/var/www/html/." is not empty.`
- **Causa:** El volumen nombrado `vendor_data` montado en `/var/www/html` hacía que Docker Compose reportara el directorio como no vacío antes de que existiera código.
- **Solución:** Instalar Laravel usando la imagen oficial `composer:latest` directamente sobre la carpeta `src/` del host (sin pasar por el contenedor con volúmenes montados).
```bash
# Comando usado para instalar Laravel correctamente:
docker run --rm \
  -v "$(pwd)/src:/app" \
  -u "$(id -u):$(id -g)" \
  composer:latest \
  create-project laravel/laravel . --prefer-dist
```

---

**Fix #3 — Incompatibilidad de PHP: Laravel 13 requiere PHP 8.4+**
- **Error:** `symfony/clock v8.1.0 requires php >=8.4.1 — your php version (8.3.32) does not satisfy that requirement.`
- **Causa:** Composer instaló Laravel 13 (versión más reciente), que depende de Symfony 8.1.x, el cual requiere PHP 8.4.1 mínimo. El Dockerfile original usaba PHP 8.3.
- **Solución:** Actualizar la imagen base del Dockerfile de `php:8.3-fpm` a `php:8.4-fpm`.
- **Archivo modificado:** `docker/php/Dockerfile`
```dockerfile
# Antes:
FROM php:8.3-fpm
# Después:
FROM php:8.4-fpm
```

---

**Fix #4 — `mbstring.internal_encoding` deprecado en PHP 8.2+**
- **Error/Warning:** `PHP Startup: Use of mbstring.internal_encoding is deprecated`
- **Causa:** La directiva `mbstring.internal_encoding` fue deprecada en PHP 8.2 y removida en PHP 8.4.
- **Solución:** Eliminar `mbstring.internal_encoding = UTF-8` del `php.ini`.
- **Archivo modificado:** `docker/php/php.ini`

---

**Fix #5 — `session.sid_length` deprecado en PHP 8.4**
- **Error/Warning:** `PHP Startup: session.sid_length INI setting is deprecated`
- **Causa:** Esta directiva fue deprecada en PHP 8.4. PHP ahora gestiona automáticamente la longitud del session ID.
- **Solución:** Eliminar `session.sid_length = 48` del `php.ini`.
- **Archivo modificado:** `docker/php/php.ini`

---

**Fix #6 — Atributo `version` obsoleto en docker-compose.yml**
- **Warning:** `the attribute 'version' is obsolete, it will be ignored`
- **Causa:** Docker Compose v2 detecta automáticamente el formato del archivo. El campo `version: "3.8"` ya no es necesario.
- **Solución:** Eliminar la línea `version: "3.8"` del `docker-compose.yml`.
- **Archivo modificado:** `docker-compose.yml`

---

#### ✅ Estado final verificado

```bash
# Resultado de verificación:
HTTP Status: 200   ← Laravel respondiendo en http://localhost:8000

# Contenedores activos:
vens_app          PHP 8.4-FPM + Laravel 13.19.0   Up
vens_nginx        Nginx 1.25-alpine                Up  → :8000
vens_mysql        MySQL 8.0                        Up (healthy) → :3306
vens_redis        Redis 7.2-alpine                 Up  → :6379
vens_phpmyadmin   phpMyAdmin                       Up  → :8080

# Migraciones ejecutadas en MySQL:
✔ create_users_table
✔ create_cache_table
✔ create_jobs_table
```

---

#### ⏭️ Próximos pasos

- [ ] Instalar Laravel Sanctum (`composer require laravel/sanctum`)
- [ ] Crear modelos: `Paciente`, `Medico`, `Cita`, `Consulta`, `Diagnostico`, `Tratamiento`
- [ ] Crear migraciones para cada módulo clínico
- [ ] Implementar sistema de roles (Admin, Médico, Enfermera, Recepcionista)
- [ ] Crear endpoints API REST y documentarlos con Swagger/L5-Swagger
- [ ] Configurar seeders con datos de prueba
- [ ] Conectar con el frontend

---

## 👥 Equipo de Desarrollo

**Centro Médico Vens — Flebología**
Proyecto de Graduación 2

---

*Documentación actualizada: 2026-07-12. Actualizar según evolucione el proyecto.*
