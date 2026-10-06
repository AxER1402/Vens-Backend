<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * El respaldo nocturno de la base de datos en Cloudflare R2.
 *
 * mariadb-dump → gzip → openssl (AES-256) → R2. El archivo cifrado se arma en
 * storage/app/respaldos, se sube y se borra del contenedor. Después se borran
 * de R2 los respaldos más viejos que config('respaldos.dias_retencion').
 *
 * Para restaurar uno, ver la sección «Respaldos» del README.
 */
class RespaldarBaseDeDatos extends Command
{
    protected $signature = 'respaldo:bd';

    protected $description = 'Exporta la base de datos cifrada y la sube a Cloudflare R2';

    public function handle(): int
    {
        $clave = config('respaldos.clave');

        if (! config('filesystems.disks.r2.bucket') || ! $clave) {
            $this->warn('Respaldo omitido: faltan R2_BUCKET o RESPALDO_CLAVE en el .env.');

            return self::SUCCESS;
        }

        $nombre = 'vens_'.now()->format('Y-m-d_Hi').'.sql.gz.enc';
        $carpeta = trim(config('respaldos.carpeta'), '/');
        $local = storage_path('app/respaldos/'.$nombre);

        if (! is_dir(dirname($local))) {
            mkdir(dirname($local), 0700, true);
        }

        try {
            $this->exportar($local, $clave);
            $this->info('Exportado: '.$nombre.' ('.$this->tamano(filesize($local)).')');

            $flujo = fopen($local, 'r');
            Storage::disk('r2')->writeStream($carpeta.'/'.$nombre, $flujo);
            if (is_resource($flujo)) {
                fclose($flujo);
            }
            $this->info('Subido a R2: '.$carpeta.'/'.$nombre);

            $this->borrarViejos($carpeta, $nombre);
        } catch (Throwable $e) {
            Log::error('Falló el respaldo de la base de datos', ['error' => $e->getMessage()]);
            $this->error('Falló el respaldo: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            @unlink($local);
        }

        return self::SUCCESS;
    }

    /**
     * Todo por tubería, sin dejar nunca el SQL en claro en el disco. pipefail
     * hace que el paso falle si falla cualquiera de los tres programas, no
     * solo el último. Las contraseñas van por variables de entorno para que no
     * aparezcan en la lista de procesos.
     */
    private function exportar(string $salida, string $clave): void
    {
        $bd = config('database.connections.mysql');

        $sinDatos = collect(config('respaldos.sin_datos'))
            ->map(fn ($tabla) => '--ignore-table-data='.escapeshellarg($bd['database'].'.'.$tabla))
            ->implode(' ');

        // --skip-ssl: la conexión no sale de la red interna de Docker, y el
        // certificado autofirmado de MySQL haría fallar al cliente de MariaDB.
        $comando = 'set -o pipefail; '
            .'mariadb-dump --skip-ssl --single-transaction --quick --no-tablespaces '
            .'--default-character-set=utf8mb4 '
            .'-h "$BD_HOST" -P "$BD_PUERTO" -u "$BD_USUARIO" '
            .$sinDatos.' "$BD_NOMBRE" '
            .'| gzip -9 '
            .'| openssl enc -aes-256-cbc -salt -pbkdf2 -iter 200000 -pass env:RESPALDO_CLAVE -out "$SALIDA"';

        Process::timeout(1800)
            ->env([
                'BD_HOST' => $bd['host'],
                'BD_PUERTO' => (string) $bd['port'],
                'BD_USUARIO' => $bd['username'],
                'BD_NOMBRE' => $bd['database'],
                'MYSQL_PWD' => $bd['password'],
                'RESPALDO_CLAVE' => $clave,
                'SALIDA' => $salida,
            ])
            ->run(['bash', '-c', $comando])
            ->throw();
    }

    private function borrarViejos(string $carpeta, string $actual): void
    {
        $dias = config('respaldos.dias_retencion');
        if ($dias <= 0) {
            return;
        }

        $disco = Storage::disk('r2');
        $limite = now()->subDays($dias)->getTimestamp();

        foreach ($disco->files($carpeta) as $archivo) {
            if (basename($archivo) === $actual || ! str_ends_with($archivo, '.sql.gz.enc')) {
                continue;
            }

            if ($disco->lastModified($archivo) < $limite) {
                $disco->delete($archivo);
                $this->line('Borrado por antigüedad: '.$archivo);
            }
        }
    }

    private function tamano(int $bytes): string
    {
        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : round($bytes / 1024, 1).' KB';
    }
}
