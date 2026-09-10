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
     * @param  array<int, string>  $enteros  Etiquetas que ocupan la fila entera
     * @return array<string, mixed>|null
     */
    public static function seccionCampos(string $titulo, array $campos, array $enteros = []): ?array
    {
        $conDato = array_filter($campos, fn ($valor) => $valor !== Formato::VACIO);

        if ($conDato === []) {
            return null;
        }

        return [
            'tipo' => 'campos',
            'titulo' => $titulo,
            'campos' => $campos,
            'enteros' => $enteros,
        ];
    }

    /**
     * Repartir los campos de una sección en las filas que se van a pintar.
     *
     * La rejilla es de dos pares etiqueta/valor por fila. Un campo nombrado en
     * `$enteros` se lleva la fila entera: es para los valores que son una frase
     * —«Eje venoso profundo permeable y compresible»— que a media fila parte en
     * tres líneas mientras la mitad derecha de la fila queda en blanco.
     *
     * Vive aquí y no en la plantilla porque el PDF y el Word tienen que repartir
     * igual; si cada generador lo hiciera por su cuenta, los dos documentos del
     * mismo informe acabarían maquetados distinto.
     *
     * @param  array<string, string>  $campos
     * @param  array<int, string>  $enteros  Etiquetas que ocupan la fila entera
     * @return array<int, array{entera: bool, campos: array<string, string>}>
     */
    public static function filasDeCampos(array $campos, array $enteros = []): array
    {
        $filas = [];
        $pareja = [];

        foreach ($campos as $etiqueta => $valor) {
            if (in_array($etiqueta, $enteros, true)) {
                if ($pareja !== []) {
                    $filas[] = ['entera' => false, 'campos' => $pareja];
                    $pareja = [];
                }

                $filas[] = ['entera' => true, 'campos' => [$etiqueta => $valor]];

                continue;
            }

            $pareja[$etiqueta] = $valor;

            if (count($pareja) === 2) {
                $filas[] = ['entera' => false, 'campos' => $pareja];
                $pareja = [];
            }
        }

        if ($pareja !== []) {
            $filas[] = ['entera' => false, 'campos' => $pareja];
        }

        return $filas;
    }
}
