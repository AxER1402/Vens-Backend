<?php

namespace App\Support\Ajustes;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Los ajustes que la clínica puede cambiar desde la pantalla.
 *
 * El nombre del centro, el NIT o el porcentaje de IVA vivían en config/ y en
 * el .env, así que corregir un dato del membrete obligaba a editar un archivo
 * y volver a desplegar. Aquí se guardan en la base y se aplican sobre la
 * configuración al arrancar la aplicación.
 *
 * Lo importante es que se aplican *encima* de la configuración existente, no
 * en su lugar: los dieciséis sitios que ya leían config('reportes.centro...')
 * o config('facturacion...') siguen funcionando sin tocarlos, y el .env se
 * queda como el valor de fábrica de una instalación nueva.
 */
class Ajustes
{
    private const CACHE = 'ajustes.guardados';

    /**
     * Qué se puede ajustar y qué configuración sobrescribe cada cosa.
     *
     * Esta lista es la única fuente: de aquí salen las reglas de validación,
     * lo que se siembra al instalar y lo que se aplica al arrancar. Añadir un
     * ajuste es añadir una fila.
     *
     * `editable` en false es para lo que no se escribe a mano en el
     * formulario: el logo llega subiendo un archivo, no tecleando una ruta.
     *
     * @var array<string, array{config: string, tipo: string, editable?: bool, max?: int}>
     */
    public const CAMPOS = [
        // ── Membrete de los informes clínicos ──
        'clinica.nombre' => ['config' => 'reportes.centro.nombre', 'tipo' => 'texto', 'max' => 150],
        'clinica.especialidad' => ['config' => 'reportes.centro.especialidad', 'tipo' => 'texto', 'max' => 100],
        'clinica.direccion' => ['config' => 'reportes.centro.direccion', 'tipo' => 'texto', 'max' => 200],
        'clinica.telefono' => ['config' => 'reportes.centro.telefono', 'tipo' => 'telefono'],
        'clinica.correo' => ['config' => 'reportes.centro.correo', 'tipo' => 'correo', 'max' => 150],
        'clinica.logo' => ['config' => 'reportes.centro.logo', 'tipo' => 'texto', 'editable' => false],

        // ── Datos fiscales de los recibos ──
        'facturacion.emisor' => ['config' => 'facturacion.emisor.nombre', 'tipo' => 'texto', 'max' => 150],
        'facturacion.nit' => ['config' => 'facturacion.emisor.nit', 'tipo' => 'texto', 'max' => 20],
        'facturacion.direccion' => ['config' => 'facturacion.emisor.direccion', 'tipo' => 'texto', 'max' => 200],
        'facturacion.serie' => ['config' => 'facturacion.serie', 'tipo' => 'texto', 'max' => 10],
        'facturacion.moneda' => ['config' => 'facturacion.moneda', 'tipo' => 'texto', 'max' => 3],
        'facturacion.iva' => ['config' => 'facturacion.iva_porcentaje', 'tipo' => 'porcentaje'],

        // ── Quién firma los informes ──
        'medico.nombre' => ['config' => 'reportes.medico.nombre', 'tipo' => 'texto', 'max' => 150],
        'medico.colegiado' => ['config' => 'reportes.medico.colegiado', 'tipo' => 'texto', 'max' => 30],

        // ── Agenda ──
        // Sin `config`: no pisan nada de config/, son ajustes propios que no
        // existían antes. Y `editable` en false porque no los escribe el
        // formulario de la clínica: el horario es una estructura de siete
        // días y tiene su propia pantalla y su propia validación.
        'agenda.horario' => ['tipo' => 'horario', 'editable' => false, 'default' => []],
        'agenda.duracion_cita' => ['tipo' => 'entero', 'editable' => false, 'default' => 30],
    ];

    /**
     * Las claves que el formulario puede escribir.
     *
     * @return array<int, string>
     */
    public static function editables(): array
    {
        return array_keys(array_filter(
            self::CAMPOS,
            fn (array $campo) => $campo['editable'] ?? true
        ));
    }

    /**
     * El valor efectivo de cada ajuste: lo guardado si existe, y si no lo que
     * traiga la configuración.
     *
     * @return array<string, mixed>
     */
    public static function todos(): array
    {
        $guardados = self::guardados();

        $valores = [];
        foreach (self::CAMPOS as $clave => $campo) {
            $valores[$clave] = array_key_exists($clave, $guardados)
                ? $guardados[$clave]
                : self::deFabrica($campo);
        }

        return $valores;
    }

    /**
     * Un ajuste suelto, sin traerse los catorce.
     */
    public static function obtener(string $clave): mixed
    {
        $guardados = self::guardados();

        if (array_key_exists($clave, $guardados)) {
            return $guardados[$clave];
        }

        return isset(self::CAMPOS[$clave]) ? self::deFabrica(self::CAMPOS[$clave]) : null;
    }

    /**
     * @param  array{config?: string, default?: mixed}  $campo
     */
    private static function deFabrica(array $campo): mixed
    {
        return isset($campo['config']) ? config($campo['config']) : ($campo['default'] ?? null);
    }

    /**
     * Guardar un puñado de ajustes.
     *
     * @param  array<string, mixed>  $valores
     */
    public static function guardar(array $valores): void
    {
        foreach ($valores as $clave => $valor) {
            if (! array_key_exists($clave, self::CAMPOS)) {
                continue;
            }

            Setting::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
        }

        self::olvidar();

        // La configuración de esta misma petición también se pone al día: sin
        // esto, la respuesta al guardar devolvería los valores viejos y quien
        // acaba de cambiar el NIT vería que no cambió nada.
        self::aplicarAConfig();
    }

    /**
     * Volcar los ajustes guardados sobre la configuración de la aplicación.
     *
     * La llama AppServiceProvider al arrancar.
     */
    public static function aplicarAConfig(): void
    {
        $guardados = self::guardados();

        if ($guardados === []) {
            return;
        }

        $nuevos = [];
        foreach (self::CAMPOS as $clave => $campo) {
            if (! isset($campo['config'])) {
                continue;
            }

            if (! array_key_exists($clave, $guardados) || $guardados[$clave] === null) {
                continue;
            }

            $nuevos[$campo['config']] = $campo['tipo'] === 'porcentaje'
                ? (float) $guardados[$clave]
                : $guardados[$clave];
        }

        if ($nuevos !== []) {
            config($nuevos);
        }
    }

    public static function olvidar(): void
    {
        Cache::forget(self::CACHE);
    }

    /**
     * Lo que hay en la base, cacheado.
     *
     * Se lee en cada petición para aplicarlo a la configuración, así que no
     * puede costar una consulta cada vez. Y se envuelve en un try porque esto
     * corre también antes de que la tabla exista: durante `migrate` sobre una
     * base vacía, o en cualquier comando de consola de una instalación recién
     * clonada. Ahí lo correcto es no haber ajustado nada, no reventar.
     *
     * @return array<string, mixed>
     */
    private static function guardados(): array
    {
        try {
            return Cache::rememberForever(self::CACHE, function () {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                return Setting::pluck('valor', 'clave')->all();
            });
        } catch (Throwable) {
            return [];
        }
    }
}
