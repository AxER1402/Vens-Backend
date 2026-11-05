<?php

namespace App\Support\Pacientes;

use App\Models\Patient;
use Illuminate\Support\Str;

/**
 * Detección de pacientes duplicados.
 *
 * Un paciente se considera el mismo cuando coinciden su nombre y su teléfono.
 * El nombre se compara sin distinguir mayúsculas, tildes ni espacios de más:
 * «María  José López» y «maria jose lopez» son la misma persona escrita por
 * dos personas distintas en el mostrador. El teléfono ya se guarda solo con
 * dígitos (ver Telefono), así que basta compararlo tal cual.
 *
 * El nombre solo no alcanza —en una clínica de pueblo se repiten— y el
 * teléfono solo tampoco: una familia comparte el mismo número. Juntos, en
 * cambio, casi nunca coinciden por casualidad.
 */
class Duplicados
{
    /**
     * Forma comparable de un nombre: sin tildes, en minúsculas y con un solo
     * espacio entre palabras.
     */
    public static function clave(?string $nombre): string
    {
        $ascii = Str::ascii((string) $nombre);

        return trim(preg_replace('/\s+/', ' ', mb_strtolower($ascii)));
    }

    /**
     * El paciente ya registrado con el mismo nombre y teléfono, o null.
     *
     * Busca también entre los desactivados: volver a registrar a alguien que
     * se dio de baja crea un segundo expediente en lugar de reactivar el suyo.
     *
     * @param  int|null  $excepto  Id del paciente que se está editando.
     */
    public static function buscar(?string $nombre, ?string $telefono, ?int $excepto = null): ?Patient
    {
        $clave = self::clave($nombre);

        if ($clave === '' || $telefono === null || $telefono === '') {
            return null;
        }

        return Patient::query()
            ->where('telefono', $telefono)
            ->when($excepto !== null, fn ($q) => $q->whereKeyNot($excepto))
            ->orderBy('id')
            ->get(['id', 'nombre', 'telefono', 'activo'])
            ->first(fn (Patient $p) => self::clave($p->nombre) === $clave);
    }

    /**
     * Mensaje para quien intenta registrar al paciente otra vez.
     */
    public static function mensaje(Patient $existente): string
    {
        $base = "Ya existe un paciente con el mismo nombre y teléfono: {$existente->nombre} (#{$existente->id}).";

        return $existente->activo
            ? $base.' Búsquelo en la lista de pacientes en lugar de registrarlo de nuevo.'
            : $base.' Está desactivado: puede reactivarlo desde la lista de pacientes.';
    }
}
