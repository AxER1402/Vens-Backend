# 🏥 Backend — Clínica Doctora Yojana Mendoza (Flebología)

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
9. [Sesión y Vencimiento](#sesión-y-vencimiento)
10. [Correo y Recuperación de Contraseña](#correo-y-recuperación-de-contraseña)
11. [Despliegue a Producción](#despliegue-a-producción)
12. [Solución de Problemas](#solución-de-problemas)
13. [Historial de Cambios](#historial-de-cambios)

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

### ¿Por qué 4 contenedores?

La aplicación —frontend y backend— vive en **un solo contenedor**. Alrededor
quedan tres servicios de infraestructura, que son imágenes oficiales sin
modificar y arrancan con el mismo comando.

```
┌──────────────────────────────────────────────────────────────────┐
│                  Docker Network: vens_network                    │
│                                                                  │
│                    Tu navegador  │  http://localhost:8000        │
│                                  ▼                               │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  1. vens_app — LA APLICACIÓN COMPLETA                      │ │
│  │  ┌──────────────────────────────────────────────────────┐  │ │
│  │  │  Supervisor (proceso principal)                      │  │ │
│  │  │    ├── Nginx    :80    reparte las peticiones        │  │ │
│  │  │    │     /api/*     → PHP-FPM                        │  │ │
│  │  │    │     /storage/* → archivos subidos               │  │ │
│  │  │    │     /img/*     → isotipo, plantilla de mapeo    │  │ │
│  │  │    │     resto      → Vite (proxy, con HMR)          │  │ │
│  │  │    ├── PHP-FPM  :9000  Laravel 13   (solo interno)   │  │ │
│  │  │    └── Vite     :5173  React 19     (solo interno)   │  │ │
│  │  └──────────────────────────────────────────────────────┘  │ │
│  └───────────┬──────────────────────┬─────────────────────────┘ │
│              │ SQL :3306            │ Redis :6379                │
│              ▼                      ▼                            │
│  ┌───────────────────┐  ┌──────────────────────┐                │
│  │  2. vens_mysql    │  │  3. vens_redis        │                │
│  │  (MySQL 8.0)      │  │  (Redis 7.2)          │                │
│  │  Base de datos    │  │  Caché + sesiones     │                │
│  └─────────┬─────────┘  └──────────────────────┘                │
│            │                                                     │
│            ▼                                                     │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │  4. vens_phpmyadmin — administrar la BD  :8080          │    │
│  └─────────────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────────────┘
```

**Todo se abre en `http://localhost:8000`.** La pantalla y la API comparten
dirección, así que no hay CORS y el frontend llama a la API con rutas
relativas. El puerto `5173` ya no se usa.

### Descripción de cada contenedor

| Contenedor | Rol | Acceso | ¿Para qué sirve? |
|---|---|---|---|
| **vens_app** | Aplicación | `localhost:8000` | Los tres procesos de la aplicación. Nginx recibe las peticiones; las de `/api` van a PHP-FPM (Laravel) y el resto al servidor de Vite, que sirve React con recarga en caliente. |
| **vens_mysql** | Base de Datos | `localhost:3306` | Almacena todos los datos: pacientes, citas, médicos, diagnósticos. Motor relacional SQL. |
| **vens_redis** | Caché y Colas | `localhost:6379` | Almacena datos temporales en memoria (muy rápido). Usado para caché de consultas frecuentes, sesiones y para procesar emails/notificaciones en segundo plano. |
| **vens_phpmyadmin** | Admin BD | `localhost:8080` | Interfaz visual para explorar y gestionar la base de datos MySQL sin usar la terminal. |

### ¿Por qué el frontend está aquí dentro?

Porque no hay que arrancar nada por separado: un `make up` levanta la
aplicación entera. El código de React sigue viviendo en su propio repositorio
(`Frontend-Vens/`) y `docker-compose.yml` lo monta como volumen en `/app`, así
que se edita igual que siempre y Vite recarga el navegador solo.

Nginx **no sirve archivos compilados en desarrollo**: hace de proxy hacia
Vite, incluido el WebSocket con el que Vite avisa de cada cambio. Por eso la
recarga en caliente funciona exactamente igual que cuando el frontend tenía su
propio contenedor.

En producción es al revés: la SPA se compila dentro de la imagen y Nginx sirve
los archivos estáticos, sin Vite. Ver [Despliegue a Producción](#despliegue-a-producción).

---

## 🚀 Configuración Inicial

El contenedor se configura solo en el primer arranque. No hay que instalar
dependencias a mano ni generar la clave por separado: el `entrypoint` de
`docker/dev/Dockerfile` se encarga.

### Requisito previo

Los **dos repositorios** tienen que estar clonados uno al lado del otro, porque
`docker-compose.yml` monta el frontend desde la carpeta de al lado:

```
Proyecto de Graduación 2/
├── Backend-Vens/     ← aquí se ejecutan todos los comandos
└── Frontend-Vens/    ← se monta en /app dentro del contenedor
```

### Arrancar

```bash
cd "Backend-Vens"

make build     # Construye la imagen (solo la primera vez, o si cambia el Dockerfile)
make up        # Levanta los 4 contenedores
make logs-app  # Sigue el primer arranque
```

El primer `make up` tarda varios minutos porque instala `vendor/` y
`node_modules/` dentro de sus volúmenes. Termina cuando en los logs aparece:

```
[vens] Listo. Arrancando Nginx, PHP-FPM y Vite.
[vens] Aplicación: http://localhost:8000
```

Los arranques siguientes son cuestión de segundos.

### Qué hace solo el contenedor al arrancar

| | Equivalente manual |
|---|---|
| Copia `src/.env.example` a `src/.env` si falta | `cp src/.env.example src/.env` |
| Instala las dependencias de PHP si falta `vendor/` | `make composer-install` |
| Genera `APP_KEY` si no hay una | `make key-generate` |
| Instala las dependencias del frontend si falta `node_modules/` | `npm install` |

Lo único que queda por hacer a mano la primera vez es preparar la base de datos:

```bash
make migrate       # Crear las tablas
make seed          # Datos de prueba (opcional)
make storage-link  # Enlace para los archivos subidos
```

### Verificación

```bash
make ps            # Los 4 contenedores levantados
```

| Dirección | Qué es |
|---|---|
| http://localhost:8000 | La aplicación (pantalla de inicio de sesión) |
| http://localhost:8000/api/v1 | La API |
| http://localhost:8000/up | Salud de Laravel |
| http://localhost:8080 | phpMyAdmin |

> **El puerto 5173 ya no se usa.** Vite sigue corriendo, pero solo dentro del
> contenedor: Nginx le pasa las peticiones desde el 8000. Si tenías el
> `:5173` guardado en el navegador, cámbialo por `:8000`.

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
| `make logs-app` | Logs de la aplicación (Nginx + PHP + Vite) | Diagnóstico |
| `make logs-vite` | Solo los logs de Vite | Diagnóstico del frontend |
| `make clean` | Eliminar todo (⚠ datos incluidos) | Reset completo |

### Comandos del Frontend

El frontend corre dentro de `vens_app`, en `/app`.

| Comando | Descripción |
|---|---|
| `make npm cmd="install axios"` | Instalar un paquete |
| `make npm cmd="run lint"` | Pasar el linter (oxlint) |
| `make restart-vite` | Reiniciar solo Vite, sin tocar el resto |

Tras instalar un paquete hay que reiniciar Vite para que lo detecte:

```bash
make npm cmd="install date-fns"
make restart-vite
```

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

# ── Comandos npm del frontend (vive en /app del mismo contenedor) ────────────
docker-compose exec -u www-data -w /app app npm install
docker-compose exec -u www-data -w /app app npm run lint

# ── Ver o reiniciar los procesos internos del contenedor ─────────────────────
docker-compose exec app supervisorctl status
docker-compose exec app supervisorctl restart vite
docker-compose exec app supervisorctl restart php-fpm
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
Proyecto de Graduación 2/
├── Frontend-Vens/              # ← Repositorio del frontend (React + Vite)
│   └── src/                    #   Se monta en /app dentro de vens_app
└── Backend-Vens/               # ← Aquí se ejecutan todos los comandos
├── docker/                     # Configuración Docker
│   ├── dev/                    # Imagen ÚNICA de desarrollo
│   │   ├── Dockerfile          #   PHP + Node + Nginx + Supervisor
│   │   ├── nginx.conf          #   /api → PHP, el resto → Vite (proxy)
│   │   ├── supervisord.conf    #   Los 3 procesos del contenedor
│   │   └── entrypoint.sh       #   Instala dependencias en el 1er arranque
│   ├── prod/                   # Imagen ÚNICA de producción
│   │   ├── Dockerfile          #   Compila la SPA y la mete en la imagen
│   │   ├── Dockerfile.dockerignore
│   │   ├── nginx.conf          #   /api → PHP, el resto → archivos estáticos
│   │   ├── php.ini             #   PHP en modo producción
│   │   ├── supervisord.conf
│   │   └── entrypoint.sh       #   Migra y cachea en cada arranque
│   ├── php/
│   │   └── php.ini             # Configuración PHP (compartida)
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
├── docker-compose.yml          # Desarrollo: 4 contenedores
├── docker-compose.prod.yml     # Producción: 3 contenedores
├── .env.example                # Plantilla de variables (SÍ commitear)
├── .env.prod.example           # Plantilla de producción (SÍ commitear)
├── .env.prod                   # Configuración real de producción (NO commitear)
├── Makefile                    # Comandos abreviados
└── README.md                   # Este archivo
```

> `docker/php/Dockerfile` y `docker/nginx/` desaparecieron: sus dos servicios
> (PHP-FPM y Nginx) ahora viven dentro de la imagen única de `docker/dev/`.

---

## 🌐 API Endpoints (Planificados)

```
POST   /api/auth/login           # Iniciar sesión
POST   /api/auth/logout          # Cerrar sesión
GET    /api/auth/user            # Usuario autenticado
POST   /api/v1/auth/forgot-password  # Solicitar enlace de recuperación
POST   /api/v1/auth/reset-password   # Restablecer con el token del correo

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

## 🔐 Sesión y Vencimiento

La sesión **no dura un plazo fijo desde el inicio**: dura mientras se use. El reloj se
reinicia con cada petición a la API, así que a quien está atendiendo pacientes no se le
cierra la pantalla a media factura, y la computadora que quedó abierta en recepción se
cierra sola pasada la hora sin que nadie tenga que acordarse.

| Variable | Valor | Qué significa |
|---|---|---|
| `SANCTUM_TOKEN_INACTIVITY` | `60` | Minutos que puede pasar el token **sin usarse** antes de dejar de autenticar. En `0` la sesión no vence. |

**Cómo se mide, y por qué no cuesta nada.** Sanctum ya escribía `last_used_at` en
`personal_access_tokens` con cada petición autenticada, con o sin esta función: la última
señal de vida del usuario ya estaba en la base. `App\Support\Sesion\VencimientoDeSesion`
solo la lee, desde el punto de enganche que el propio Sanctum ofrece
(`Sanctum::authenticateAccessTokensUsing`, registrado en `AppServiceProvider`). No hay
procesos vigilando, ni tareas programadas, ni una sola consulta añadida. El servidor no
observa el ratón ni el teclado —no puede—: solo cuentan las peticiones a la API.

**Qué recibe el frontend.** Además de `expires_in` / `expires_at` en `login` y en
`/auth/me`, **toda respuesta autenticada** trae dos cabeceras:

```
X-Session-Expires-In: 3600
X-Session-Expires-At: 2026-09-05T09:00:00-06:00
```

El cliente debe **reiniciar su cuenta atrás con cada respuesta**. Si se quedara con el
`expires_at` del inicio de sesión, cerraría la pantalla a la hora exacta aunque el usuario
llevara todo ese rato trabajando, que es justo lo que se quiso evitar. Las cabeceras están
declaradas en `exposed_headers` de `config/cors.php`; sin eso el navegador se las
escondería al JavaScript.

Cumplido el plazo, la API responde `401` con
`"Su sesión no es válida o ha expirado. Vuelva a iniciar sesión."`, la señal con la que el
frontend cierra la sesión y avisa.

Los tokens que murieron por inactividad se borran en el siguiente inicio de sesión de ese
mismo usuario, para que la tabla no crezca sin límite. Iniciar sesión en otro dispositivo
no cierra las sesiones vivas.

---

## 📧 Correo y Recuperación de Contraseña

El flujo de "olvidé mi contraseña" funciona en dos pasos y depende del envío de correo:

1. `POST /api/v1/auth/forgot-password` con `{ "email": "..." }` → envía un correo con un enlace al frontend.
2. `POST /api/v1/auth/reset-password` con `{ "token", "email", "password", "password_confirmation" }` → cambia la contraseña y revoca los tokens de sesión activos.

Por seguridad, el paso 1 **siempre** responde `200` con el mismo mensaje, exista o no la cuenta, para que el endpoint no sirva para averiguar qué correos están registrados. Las cuentas inactivas tampoco reciben el enlace.

### Variables de entorno relacionadas

```env
# URL del frontend: se usa para armar el enlace del correo
# Resultado: FRONTEND_URL/restablecer-contrasena?token=...&email=...
#
# Con el contenedor unificado, el frontend se sirve desde el mismo puerto que
# la API, así que es la misma dirección que APP_URL.
FRONTEND_URL=http://localhost:8000
```

### Probar el flujo

```bash
curl -X POST http://localhost:8000/api/v1/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"email":"correo-real@ejemplo.com"}'
```

> Si se cambia cualquier variable `MAIL_*`, hay que ejecutar `docker compose exec app php artisan config:clear` para que Laravel tome el valor nuevo.
>
> Alternativa sin envío real: con `MAIL_MAILER=log` el mensaje completo, con el enlace y el token, se escribe en `src/storage/logs/laravel.log` en lugar de enviarse.

### Con Gmail (correos reales)

Google **no acepta la contraseña normal** de la cuenta para SMTP desde 2022. Hay que generar una **Contraseña de aplicación** de 16 caracteres:

1. La cuenta debe tener la **Verificación en 2 pasos activada** (sin esto, la opción no aparece).
   → https://myaccount.google.com/security
2. Entrar a **Contraseñas de aplicaciones**: https://myaccount.google.com/apppasswords
3. Escribir un nombre (ej. `Vens Backend`) y pulsar **Crear**.
4. Google muestra una clave tipo `abcd efgh ijkl mnop` → **copiarla sin los espacios**.

Luego en `src/.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tucuenta@gmail.com
MAIL_PASSWORD=abcdefghijklmnop          # la contraseña de aplicación, sin espacios
MAIL_FROM_ADDRESS="tucuenta@gmail.com"  # debe coincidir con MAIL_USERNAME
MAIL_FROM_NAME="Clínica Doctora Yojana Mendoza"
```

Y aplicar los cambios:

```bash
docker compose exec app php artisan config:clear
```

**Puntos importantes:**

- `MAIL_FROM_ADDRESS` tiene que ser la misma cuenta de Gmail (o un alias verificado en ella). Si no coincide, Gmail reescribe el remitente y el correo puede caer en spam.
- En Laravel 11+ la variable es **`MAIL_SCHEME`**, no `MAIL_ENCRYPTION` (esta última ya no la lee nadie — ver `config/mail.php`). Con el puerto 587 no hace falta definirla: Laravel usa STARTTLS automáticamente. Si se prefiere el puerto 465, hay que añadir `MAIL_SCHEME=smtps`.
- Una cuenta gratuita de Gmail tiene un **límite de ~500 correos al día**. Para producción real conviene un servicio transaccional (SendGrid, Mailgun, Amazon SES).
- **Nunca subir la contraseña de aplicación al repositorio.** El archivo `src/.env` ya está en `.gitignore`; los valores de ejemplo van solo en `.env.example`.

### Otros proveedores

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.tu-proveedor.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="noreply@vens-flebologia.com"
```

> ⚠️ Nunca subir credenciales de correo al repositorio: `src/.env` está en `.gitignore` y los valores de ejemplo van solo en `.env.example`.

---

## 🚢 Despliegue a Producción

En desarrollo son **4 contenedores**. En producción son **3**: desaparece
phpMyAdmin, y la diferencia de fondo es que Vite ya no corre — `npm run build`
convierte el frontend en archivos estáticos que quedan dentro de la imagen y
los sirve el mismo Nginx que atiende la API.

### La imagen unificada

```
┌──────────────────────────────────────────────────────────────────┐
│              Docker Network: vens_prod_network                   │
│                                                                  │
│                        Navegador  │ HTTPS                        │
│                                   ▼                              │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  vens_prod_app — UN SOLO CONTENEDOR                        │ │
│  │  ┌──────────────────────────────────────────────────────┐  │ │
│  │  │  Supervisor (proceso principal)                      │  │ │
│  │  │    ├── Nginx  :80                                    │  │ │
│  │  │    │     /            → SPA de React compilada       │  │ │
│  │  │    │     /api/*       → PHP-FPM                      │  │ │
│  │  │    │     /storage/*   → archivos subidos             │  │ │
│  │  │    │     /img/*       → isotipo, plantilla de mapeo  │  │ │
│  │  │    └── PHP-FPM  127.0.0.1:9000  (Laravel 13)         │  │ │
│  │  └──────────────────────────────────────────────────────┘  │ │
│  └──────────────┬──────────────────────┬──────────────────────┘ │
│                 │ SQL :3306            │ Redis :6379             │
│                 ▼                      ▼                         │
│      ┌───────────────────┐  ┌──────────────────────┐            │
│      │  vens_prod_mysql  │  │  vens_prod_redis      │            │
│      │  (sin puerto      │  │  (sin puerto          │            │
│      │   publicado)      │  │   publicado)          │            │
│      └───────────────────┘  └──────────────────────┘            │
└──────────────────────────────────────────────────────────────────┘
```

La ventaja de tener el frontend y la API detrás del mismo Nginx no es solo
tener menos contenedores: al compartir dominio **desaparece el CORS**, hace
falta **un solo certificado TLS** y el frontend puede llamar a la API con una
ruta relativa (`/api/v1`), así que la misma imagen sirve en `localhost`, en un
dominio de pruebas y en el definitivo sin reconstruirse.

### Archivos que intervienen

| Archivo | Para qué |
|---|---|
| `docker/prod/Dockerfile` | Construye la imagen en 4 etapas: compila la SPA, prepara PHP con sus extensiones, instala Composer sin dependencias de desarrollo y arma la imagen final. |
| `docker/prod/Dockerfile.dockerignore` | Evita mandar `node_modules/`, `vendor/` y los `.env` locales al build. |
| `docker/prod/nginx.conf` | El reparto entre la SPA y Laravel dentro del contenedor. |
| `docker/prod/php.ini` | PHP en modo producción: sin mostrar errores y con OPcache sin revalidar archivos. |
| `docker/prod/supervisord.conf` | Mantiene vivos a Nginx y PHP-FPM (y, cuando haga falta, al worker de colas). |
| `docker/prod/entrypoint.sh` | En cada arranque: espera a MySQL, migra, enlaza `storage` y cachea configuración, rutas y vistas. |
| `docker-compose.prod.yml` | Los tres servicios de producción. |
| `.env.prod.example` | Plantilla de configuración; se copia a `.env.prod`, que **no se versiona**. |

> **El contexto de build es la carpeta padre.** La imagen necesita el código de
> los dos repositorios, así que se construye desde la carpeta que contiene
> tanto `Backend-Vens/` como `Frontend-Vens/`. Los targets del Makefile ya lo
> hacen; a mano sería
> `docker build -f Backend-Vens/docker/prod/Dockerfile -t vens-app .`

### Desplegar por primera vez

```bash
# 1. Configuración
cp .env.prod.example .env.prod

# 2. Construir la imagen (tarda varios minutos la primera vez)
make prod-build

# 3. Generar la clave de cifrado y pegarla en APP_KEY de .env.prod
make prod-key

# 4. Editar .env.prod: APP_URL con el dominio real y las contraseñas
#    de DB_PASSWORD, DB_ROOT_PASSWORD y REDIS_PASSWORD

# 5. Levantar
make prod-up

# 6. Seguir el arranque (migraciones, cachés, Nginx y PHP-FPM)
make prod-logs
```

Las migraciones se aplican solas en cada arranque, así que no hay un paso
manual equivalente a `make migrate`.

### Comandos

| Comando | Qué hace |
|---|---|
| `make prod-build` | Construye la imagen unificada |
| `make prod-up` | Levanta los tres contenedores |
| `make prod-down` | Los detiene (los datos se conservan) |
| `make prod-logs` | Logs de Nginx, PHP-FPM y Laravel juntos |
| `make prod-ps` | Estado de los contenedores |
| `make prod-shell` | Terminal dentro del contenedor de la aplicación |
| `make prod-key` | Genera una `APP_KEY` |
| `make prod-db` | Consola de MySQL (el 3306 no está publicado) |
| `make prod-seed` | Siembra los catálogos sin borrar nada |
| `make prod-reset` | ⚠ Vacía la base, resiembra y borra los archivos subidos |

`make prod-reset` existe para probar el despliegue y dejarlo después como
estaba. Hace falta un target propio porque `migrate:fresh` por su cuenta no
basta: los archivos subidos viven en el volumen `vens_prod_storage` y las
cascadas de MySQL no los tocan, los ajustes cacheados están en Redis, y sin
`--seed` la base queda sin roles ni usuarios, es decir, sin poder entrar.
Deja los cuatro usuarios y cuatro pacientes de ejemplo del seeder.

### Actualizar una versión desplegada

El código vive **dentro** de la imagen: no hay volúmenes de código como en
desarrollo, así que editar archivos en el servidor no cambia nada.

```bash
git pull                # en los dos repositorios
make prod-build
make prod-up            # recrea el contenedor con la imagen nueva
```

Lo único que sobrevive es el volumen `vens_prod_storage`, donde quedan los
archivos que sube el usuario (el logo del membrete).

> **Producción y desarrollo no se pisan.** El Compose de producción declara su
> propio nombre de proyecto (`vens-prod`), sus propios nombres de contenedor
> (`vens_prod_app`, `vens_prod_mysql`, `vens_prod_redis`), su propia red y sus
> propios volúmenes. Se pueden tener los dos entornos levantados a la vez —
> ojo solo con `APP_PORT`, que por defecto es 80 y hay que cambiarlo si esa
> máquina ya tiene algo escuchando ahí.

### Diferencias respecto de desarrollo

| | Desarrollo | Producción |
|---|---|---|
| Contenedores | 4 | 3 |
| Frontend | Vite dentro de `vens_app`, con recarga en caliente | Compilado dentro de la imagen |
| Nginx | Proxy hacia Vite | Sirve los archivos compilados |
| CORS | No hace falta (mismo puerto) | No hace falta (mismo dominio) |
| Código | Montado por volumen, se edita en vivo | Dentro de la imagen |
| phpMyAdmin | Sí, en `:8080` | No se despliega |
| MySQL y Redis | Con puerto publicado al host | Solo en la red interna |
| `APP_DEBUG` | `true` | `false` |
| Errores de PHP | Se muestran | Solo al log |

### Falta para un servidor público

El contenedor escucha en HTTP por el puerto 80. Para exponerlo a internet
todavía hay que ponerle delante **HTTPS**, con Caddy o Traefik (que gestionan
el certificado de Let's Encrypt solos) o con el Nginx del propio servidor más
Certbot. La configuración ya deja pasar `/.well-known/`, que es la ruta con la
que Let's Encrypt valida el dominio.

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

**Clínica Doctora Yojana Mendoza — Flebología**
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

**Clínica Doctora Yojana Mendoza — Flebología**
Proyecto de Graduación 2

---

*Documentación actualizada: 2026-07-12. Actualizar según evolucione el proyecto.*
