# =============================================================================
# Dockerfile — PHP 8.3 FPM para Laravel (Centro Médico Flebología)
# =============================================================================
# Este archivo define la imagen Docker personalizada para PHP.
# Usamos PHP 8.3-FPM (FastCGI Process Manager) que se comunica con Nginx
# para procesar las peticiones PHP de la aplicación Laravel.
#
# Comandos relacionados:
#   docker build -f docker/php/Dockerfile -t vens-php .   # Construir manualmente
#   docker-compose build app                               # Construir via Compose
# =============================================================================

# ── Imagen base ──────────────────────────────────────────────────────────────
# Usamos la imagen oficial de PHP 8.3 con FPM (FastCGI Process Manager).
# FPM es necesario para trabajar con Nginx como servidor web.
FROM php:8.4-fpm

# ── Argumentos de construcción ────────────────────────────────────────────────
# UID y GID del usuario del host para evitar problemas de permisos
# en los archivos generados dentro del contenedor.
ARG UID=1000
ARG GID=1000

# ── Variables de entorno ──────────────────────────────────────────────────────
ENV DEBIAN_FRONTEND=noninteractive

# ── Dependencias del sistema ──────────────────────────────────────────────────
# Instalamos las librerías del sistema operativo necesarias para compilar
# las extensiones PHP y para que Laravel funcione correctamente.
#
# Paquetes clave:
#   - libpng-dev, libjpeg-dev, libwebp-dev : Para la extensión GD (imágenes)
#   - libzip-dev                             : Para manejar archivos ZIP
#   - libonig-dev                            : Para mbstring (multibyte strings)
#   - libxml2-dev                            : Para XML/SOAP
#   - curl, git, unzip                       : Herramientas generales
#   - supervisor                             : Para gestionar procesos (queues)
RUN apt-get update && apt-get install -y \
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
    supervisor \
    nano \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── Extensiones PHP ───────────────────────────────────────────────────────────
# Configuramos e instalamos las extensiones PHP requeridas por Laravel.
#
# Extensiones instaladas:
#   - pdo_mysql  : Conexión a MySQL (base de datos principal)
#   - mbstring   : Manejo de strings multibyte (requerido por Laravel)
#   - exif       : Lectura de metadatos EXIF en imágenes
#   - pcntl      : Control de procesos (para queues y workers)
#   - bcmath     : Aritmética de precisión arbitraria (pagos, cálculos médicos)
#   - soap       : Protocolo SOAP (posible integración con sistemas externos)
#   - intl       : Internacionalización (fechas, monedas)
#   - gd         : Procesamiento de imágenes (fotos de pacientes, documentos)
#   - zip        : Manejo de archivos comprimidos
#   - opcache    : Caché de bytecode PHP (mejora rendimiento en producción)
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
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
        xml

# ── Redis (extensión via PECL) ────────────────────────────────────────────────
# Redis no está incluido en PHP por defecto, se instala via PECL
# (PHP Extension Community Library).
# Lo usaremos para caché y manejo de colas de trabajo (jobs).
RUN pecl install redis \
    && docker-php-ext-enable redis

# ── Composer ──────────────────────────────────────────────────────────────────
# Composer es el gestor de dependencias de PHP (equivalente a npm en Node.js).
# Lo copiamos desde su imagen oficial para tener la versión más reciente.
#
# Uso posterior:
#   docker-compose exec app composer install        # Instalar dependencias
#   docker-compose exec app composer require <pkg>  # Agregar paquete
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ── Usuario no-root ───────────────────────────────────────────────────────────
# Por seguridad, creamos un usuario dedicado en lugar de usar root.
# Los archivos de Laravel serán propiedad de este usuario.
RUN groupmod -o -g ${GID} www-data \
    && usermod -o -u ${UID} -g www-data www-data

# ── Directorio de trabajo ─────────────────────────────────────────────────────
# Definimos /var/www/html como el directorio principal de la aplicación.
# Este directorio se mapeará al código fuente de Laravel via docker-compose.
WORKDIR /var/www/html

# ── Copiamos la configuración personalizada de PHP ────────────────────────────
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# ── Permisos ──────────────────────────────────────────────────────────────────
# Asignamos la propiedad del directorio al usuario www-data.
RUN chown -R www-data:www-data /var/www/html

# ── Usuario que ejecutará FPM ─────────────────────────────────────────────────
USER www-data

# ── Puerto expuesto ───────────────────────────────────────────────────────────
# PHP-FPM escucha en el puerto 9000 por defecto.
# Nginx se comunica con este puerto internamente.
EXPOSE 9000

# ── Comando de inicio ─────────────────────────────────────────────────────────
CMD ["php-fpm"]
