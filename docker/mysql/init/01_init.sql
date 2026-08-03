-- =============================================================================
-- Script de inicialización de MySQL
-- Centro Médico de Flebología — Backend Vens
-- =============================================================================
-- Este script se ejecuta AUTOMÁTICAMENTE la primera vez que el contenedor
-- MySQL inicia (solo si el volumen de datos está vacío).
--
-- Ubicación en el contenedor: /docker-entrypoint-initdb.d/
-- MySQL ejecuta todos los archivos .sql en este directorio ordenados por nombre.
--
-- IMPORTANTE: Si el volumen ya existe (docker-compose down sin -v), este
-- script NO se vuelve a ejecutar. Para forzarlo: make clean && make up
-- =============================================================================

-- Aseguramos usar la base de datos correcta
USE vens_flebologia;

-- Configuración de charset y colación para soporte completo de español
-- utf8mb4 soporta todos los caracteres Unicode, incluyendo emojis
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- =============================================================================
-- VERIFICAR Y AJUSTAR PERMISOS DEL USUARIO
-- =============================================================================
-- El usuario vens_user ya fue creado por docker-compose via variables de entorno.
-- Aquí le otorgamos permisos completos sobre la base de datos de la aplicación.

GRANT ALL PRIVILEGES ON vens_flebologia.* TO 'vens_user'@'%';
FLUSH PRIVILEGES;

-- =============================================================================
-- NOTA: Las tablas del sistema (pacientes, citas, etc.) son creadas por las
-- migraciones de Laravel, NO por este script.
--
-- Para crear las tablas ejecutar:
--   make migrate
--   (o: docker-compose exec app php artisan migrate)
-- =============================================================================

-- Log de inicialización
SELECT 'Base de datos vens_flebologia inicializada correctamente' AS status;
