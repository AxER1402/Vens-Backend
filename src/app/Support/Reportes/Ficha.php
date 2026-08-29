<?php

namespace App\Support\Reportes;

use App\Models\Patient;
use App\Models\User;

/**
 * Piezas que comparten los tres reportes: la ficha del paciente, el pie de
 * firma y la construcción de una sección de campos.
 */
class Ficha
{
    /**
     * Datos del paciente que encabezan cualquier informe.
     *
     * @return array<string, string>
     */
    public static function paciente(?Patient $paciente): array
    {
        if ($paciente === null) {
            return ['Paciente' => Formato::VACIO];
        }

        return array_filter([
            'Paciente' => Formato::valor($paciente->nombre),
            'Edad' => Formato::hayDato($paciente->edad) ? Formato::entero($paciente->edad).' años' : null,
            'Teléfono' => Formato::hayDato($paciente->telefono) ? Formato::valor($paciente->telefono) : null,
            'Residencia' => Formato::hayDato($paciente->lugar_residencia) ? Formato::valor($paciente->lugar_residencia) : null,
            'Estado civil' => Formato::hayDato($paciente->estado_civil) ? Formato::valor($paciente->estado_civil) : null,
        ]);
    }

    /**
     * Pie de firma.
     *
     * Prevalece el médico configurado en config/reportes.php —el que firma los
     * informes del centro—; si no está configurado se firma con el usuario que
     * registró el expediente, que es el dato que sí consta en la base.
     *
     * @return array<string, string>
     */
    public static function firma(?User $registro): array
    {
        $nombre = config('reportes.medico.nombre') ?: $registro?->name;

        return array_filter([
            'nombre' => Formato::valor($nombre),
            'colegiado' => config('reportes.medico.colegiado') ?: null,
            'registrado_por' => $registro?->name,
            'emitido' => Formato::fechaLarga(now()),
        ]);
    }

    /**
     * Sección de pares etiqueta/valor.
     *
     * Devuelve null si ningún campo tiene dato: un informe con epígrafes vacíos
     * se lee como un formulario a medio llenar. Los campos sin dato individuales
     * sí se conservan, porque dentro de una sección con contenido el hueco es
     * información ("no se registró").
     *
     * @param  array<string, string>  $campos
     * @return array<string, mixed>|null
     */
    public static function seccionCampos(string $titulo, array $campos): ?array
    {
        $conDato = array_filter($campos, fn ($valor) => $valor !== Formato::VACIO);

        if ($conDato === []) {
            return null;
        }

        return [
            'tipo' => 'campos',
            'titulo' => $titulo,
            'campos' => $campos,
        ];
    }
}
