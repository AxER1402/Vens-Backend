<?php

namespace App\Http\Requests\Ajuste;

use App\Support\Agenda\Horario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * El horario de atención y la duración de una cita.
 *
 * Va aparte del formulario de la clínica porque no es un puñado de textos
 * sueltos: son siete días con dos horas cada uno, y las horas tienen que
 * guardar orden entre ellas.
 */
class UpdateAgendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reglas = [
            'duracion_cita' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'horario' => ['sometimes', 'array'],
        ];

        foreach (Horario::DIAS as $dia) {
            $reglas["horario.{$dia}"] = ['sometimes', 'array'];
            $reglas["horario.{$dia}.activo"] = ['required_with:horario.'.$dia, 'boolean'];
            $reglas["horario.{$dia}.abre"] = ['nullable', 'date_format:H:i'];
            $reglas["horario.{$dia}.cierra"] = ['nullable', 'date_format:H:i'];
        }

        return $reglas;
    }

    /**
     * Que la hora de cierre sea posterior a la de apertura.
     *
     * No se puede expresar con `after:`, que compara contra un campo fijo y
     * aquí hay uno distinto por cada día.
     */
    public function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $v) {
            $horario = $this->input('horario', []);

            foreach (Horario::DIAS as $dia) {
                $tramo = $horario[$dia] ?? null;

                if (! is_array($tramo) || empty($tramo['activo'])) {
                    continue;
                }

                $abre = $tramo['abre'] ?? null;
                $cierra = $tramo['cierra'] ?? null;

                if ($abre && $cierra && $cierra <= $abre) {
                    $v->errors()->add(
                        "horario.{$dia}.cierra",
                        'La hora de cierre debe ser posterior a la de apertura.'
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'duracion_cita.integer' => 'La duración de la cita se indica en minutos enteros.',
            'duracion_cita.min' => 'Una cita no puede durar menos de 5 minutos.',
            'duracion_cita.max' => 'Una cita no puede durar más de 8 horas.',
            'horario.*.abre.date_format' => 'La hora de apertura se escribe como HH:MM.',
            'horario.*.cierra.date_format' => 'La hora de cierre se escribe como HH:MM.',
        ];
    }
}
