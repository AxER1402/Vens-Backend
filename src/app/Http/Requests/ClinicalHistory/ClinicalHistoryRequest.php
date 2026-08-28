<?php

namespace App\Http\Requests\ClinicalHistory;

use App\Models\ClinicalHistory;
use App\Models\ClinicalOption;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas compartidas por el registro y la edición de historias clínicas.
 *
 * Los campos clínicos son opcionales por diseño: la historia se llena a lo largo
 * de la consulta y puede guardarse como 'Borrador'. Al marcarla como 'Finalizada'
 * se exigen los datos mínimos que el formulario considera indispensables.
 */
abstract class ClinicalHistoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de los campos clínicos, comunes al registro y a la edición.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function reglasClinicas(): array
    {
        return [
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'fecha_consulta' => ['nullable', 'date', 'before_or_equal:today'],
            'estado_registro' => ['nullable', 'string', Rule::in(['Borrador', 'Finalizada'])],

            // ── Interrogatorio y síntomas ─────────────────────────────────────
            'consulta_por' => [
                'required_if:estado_registro,Finalizada',
                'nullable',
                'string',
                Rule::in(['Estética', 'Enfermedad']),
            ],
            'disminuyen_otros' => ['nullable', 'string', 'max:255'],

            // ── Antecedentes ──────────────────────────────────────────────────
            'familiar_varices' => ['nullable', 'boolean'],
            'alergias' => ['nullable', 'string', 'max:255'],
            'cirugias' => ['nullable', 'string', 'max:2000'],

            // ── Antecedentes ginecológicos ────────────────────────────────────
            'gestas' => ['nullable', 'integer', 'min:0', 'max:30'],
            'abortos' => ['nullable', 'integer', 'min:0', 'max:30'],
            'partos' => ['nullable', 'integer', 'min:0', 'max:30'],
            'cesareas' => ['nullable', 'integer', 'min:0', 'max:30'],
            'hijos_vivos' => ['nullable', 'integer', 'min:0', 'max:30'],
            'hijos_muertos' => ['nullable', 'integer', 'min:0', 'max:30'],
            'ultima_menstruacion' => ['nullable', 'date', 'before_or_equal:today'],
            'hormonas' => ['nullable', 'string', 'max:255'],
            'enfermedades_otros' => ['nullable', 'string', 'max:255'],

            // ── Examen físico ─────────────────────────────────────────────────
            'presion_arterial' => [
                'required_if:estado_registro,Finalizada',
                'nullable',
                'string',
                'max:10',
                'regex:/^\d{2,3}\/\d{2,3}$/',
            ],
            'frecuencia_cardiaca' => ['nullable', 'integer', 'min:20', 'max:250'],
            'frecuencia_respiratoria' => ['nullable', 'integer', 'min:5', 'max:80'],
            'temperatura' => ['nullable', 'numeric', 'min:30', 'max:45'],
            'peso' => ['nullable', 'numeric', 'min:0.5', 'max:500'],
            'perimetro_tobillo' => ['nullable', 'numeric', 'min:5', 'max:120'],
            'perimetro_pantorrilla' => ['nullable', 'numeric', 'min:5', 'max:120'],
            'ubicacion_patologia' => ['nullable', 'string', Rule::in(['MID', 'MII', 'BILATERAL'])],

            // ── Diagnóstico CEAP ──────────────────────────────────────────────
            // Clase clínica del CEAP (ej: C2a, C4b, C6): texto corto alfanumérico
            'ceap_c' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],

            // ── Plan de tratamiento y escleroterapia ──────────────────────────
            'esclero_concentracion' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'esclero_forma' => ['nullable', 'string', Rule::in(['Líquida', 'Espuma'])],
            'esclero_volumen' => ['nullable', 'numeric', 'min:0', 'max:999.99'],

            // Qué se recetó en cada indicación marcada, indexado por el valor
            // del catálogo: { "Venotónico": "Perivasc 950/50" }
            'indicaciones_detalle' => ['nullable', 'array'],
            'indicaciones_detalle.*' => ['nullable', 'string', 'max:255'],

            'indicaciones_otros' => ['nullable', 'string', 'max:255'],

            // ── Evolución y observaciones ─────────────────────────────────────
            'evolucion' => ['nullable', 'string', Rule::in(['Mejoría', 'Igual', 'Empeoramiento'])],
            'estado_general' => [
                'required_if:estado_registro,Finalizada',
                'nullable',
                'string',
                Rule::in([
                    'Respuesta satisfactoria',
                    'Requiere nuevas sesiones',
                    'Requiere cirugía',
                    'Sospecha de complicación',
                ]),
            ],
            'notas' => ['nullable', 'string', 'max:5000'],

            // ── Listas de selección múltiple ──────────────────────────────────
            'selecciones' => ['nullable', 'array'],
            'selecciones.*' => ['array'],
            'selecciones.*.*' => ['string', 'max:150'],
        ];
    }

    /**
     * Normalizar el payload del formulario: las cadenas vacías que envía React
     * se tratan como ausencia de dato, no como texto.
     */
    protected function prepareForValidation(): void
    {
        $normalizados = [];

        foreach ($this->all() as $campo => $valor) {
            $normalizados[$campo] = is_string($valor) && trim($valor) === '' ? null : $valor;
        }

        $this->merge($normalizados);
    }

    /**
     * Validaciones que dependen del catálogo clínico y del estado del registro.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validarSelecciones($validator);
            $this->validarDetalleIndicaciones($validator);
            $this->validarRequisitosDeCierre($validator);
        });
    }

    /**
     * Verificar que cada valor marcado pertenezca a su lista del catálogo.
     */
    protected function validarSelecciones(Validator $validator): void
    {
        $selecciones = $this->input('selecciones');

        if (! is_array($selecciones)) {
            return;
        }

        foreach ($selecciones as $categoria => $valores) {
            if (! in_array($categoria, ClinicalHistory::CATEGORIAS, true)) {
                $validator->errors()->add(
                    "selecciones.{$categoria}",
                    "'{$categoria}' no es una lista válida de la historia clínica."
                );

                continue;
            }

            if (! is_array($valores) || $valores === []) {
                continue;
            }

            $permitidos = ClinicalOption::byCategoria($categoria)->activas()->pluck('valor')->all();

            foreach ($valores as $valor) {
                if (! is_string($valor) || ! in_array($valor, $permitidos, true)) {
                    $validator->errors()->add(
                        "selecciones.{$categoria}",
                        "El valor seleccionado no está permitido en la lista '{$categoria}'."
                    );
                }
            }
        }
    }

    /**
     * El detalle de las indicaciones se indexa por el valor del catálogo, así que
     * cada clave debe ser una indicación existente.
     */
    protected function validarDetalleIndicaciones(Validator $validator): void
    {
        $detalle = $this->input('indicaciones_detalle');

        if (! is_array($detalle) || $detalle === []) {
            return;
        }

        $permitidos = ClinicalOption::byCategoria('indicaciones')->activas()->pluck('valor')->all();

        foreach (array_keys($detalle) as $indicacion) {
            if (! in_array($indicacion, $permitidos, true)) {
                $validator->errors()->add(
                    'indicaciones_detalle',
                    "'{$indicacion}' no es una indicación válida del catálogo clínico."
                );
            }
        }
    }

    /**
     * Exigir el diagnóstico CEAP cuando la historia se marca como Finalizada.
     */
    protected function validarRequisitosDeCierre(Validator $validator): void
    {
        if ($this->input('estado_registro') !== 'Finalizada') {
            return;
        }

        $ceap = data_get($this->input('selecciones'), 'ceap_diagnostico', []);

        if (! is_array($ceap) || $ceap === []) {
            $validator->errors()->add(
                'selecciones.ceap_diagnostico',
                'Debe registrar al menos un diagnóstico CEAP para finalizar la historia clínica.'
            );
        }
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'patient_id.required' => 'Debe seleccionar el paciente al que pertenece la historia clínica.',
            'patient_id.exists' => 'El paciente seleccionado no existe en la base de datos.',
            'appointment_id.exists' => 'La cita asociada no existe en la base de datos.',
            'fecha_consulta.before_or_equal' => 'La fecha de consulta no puede ser posterior al día de hoy.',
            'estado_registro.in' => 'El estado del registro debe ser: Borrador o Finalizada.',

            'consulta_por.required_if' => 'Indique el motivo de consulta para finalizar la historia clínica.',
            'consulta_por.in' => 'El motivo de consulta debe ser: Estética o Enfermedad.',

            'ultima_menstruacion.before_or_equal' => 'La fecha de última menstruación no puede ser futura.',
            'gestas.max' => 'El número de gestas no parece un valor válido.',

            'presion_arterial.required_if' => 'Registre la presión arterial para finalizar la historia clínica.',
            'presion_arterial.regex' => 'La presión arterial debe tener el formato sistólica/diastólica (ej: 120/80).',
            'frecuencia_cardiaca.integer' => 'La frecuencia cardiaca debe ser un número entero (lpm).',
            'frecuencia_cardiaca.min' => 'La frecuencia cardiaca registrada está fuera del rango clínico válido.',
            'frecuencia_cardiaca.max' => 'La frecuencia cardiaca registrada está fuera del rango clínico válido.',
            'frecuencia_respiratoria.integer' => 'La frecuencia respiratoria debe ser un número entero (rpm).',
            'frecuencia_respiratoria.min' => 'La frecuencia respiratoria registrada está fuera del rango clínico válido.',
            'frecuencia_respiratoria.max' => 'La frecuencia respiratoria registrada está fuera del rango clínico válido.',
            'temperatura.numeric' => 'La temperatura debe ser un valor numérico en grados centígrados.',
            'temperatura.min' => 'La temperatura registrada está fuera del rango clínico válido (30 °C a 45 °C).',
            'temperatura.max' => 'La temperatura registrada está fuera del rango clínico válido (30 °C a 45 °C).',
            'peso.numeric' => 'El peso debe ser un valor numérico.',
            'perimetro_tobillo.numeric' => 'El perímetro del tobillo debe ser un valor numérico en centímetros.',
            'perimetro_tobillo.min' => 'El perímetro del tobillo está fuera del rango válido (5 cm a 120 cm).',
            'perimetro_tobillo.max' => 'El perímetro del tobillo está fuera del rango válido (5 cm a 120 cm).',
            'perimetro_pantorrilla.numeric' => 'El perímetro de la pantorrilla debe ser un valor numérico en centímetros.',
            'perimetro_pantorrilla.min' => 'El perímetro de la pantorrilla está fuera del rango válido (5 cm a 120 cm).',
            'perimetro_pantorrilla.max' => 'El perímetro de la pantorrilla está fuera del rango válido (5 cm a 120 cm).',
            'ubicacion_patologia.in' => 'La ubicación de la patología debe ser: MID, MII o BILATERAL.',

            'ceap_c.regex' => 'La clase clínica C solo admite letras y números (ej: C2a).',
            'ceap_c.max' => 'La clase clínica C no puede superar los 10 caracteres.',

            'esclero_concentracion.numeric' => 'La concentración de la sustancia esclerosante debe ser numérica (%).',
            'esclero_concentracion.max' => 'La concentración no puede superar el 100 %.',
            'esclero_forma.in' => 'La forma de la sustancia esclerosante debe ser: Líquida o Espuma.',
            'esclero_volumen.numeric' => 'El volumen total debe ser un valor numérico (ml).',
            'indicaciones_detalle.array' => 'El formato del detalle de las indicaciones no es válido.',
            'indicaciones_detalle.*.max' => 'El detalle de cada indicación no puede superar los 255 caracteres.',

            'evolucion.in' => 'La evolución debe ser: Mejoría, Igual o Empeoramiento.',
            'estado_general.required_if' => 'Indique el estado general del paciente para finalizar la historia clínica.',
            'estado_general.in' => 'El estado general seleccionado no es válido.',

            'selecciones.array' => 'El formato de las listas de selección no es válido.',
        ];
    }
}
