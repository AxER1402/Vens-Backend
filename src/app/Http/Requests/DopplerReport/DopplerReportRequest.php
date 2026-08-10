<?php

namespace App\Http\Requests\DopplerReport;

use App\Models\ClinicalHistory;
use App\Models\DopplerReport;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas compartidas por el registro y la edición del reporte de Ecodöppler.
 *
 * Los hallazgos son opcionales por diseño: el estudio se llena mientras se
 * realiza la ecografía y puede guardarse como 'Borrador'. Al marcarlo como
 * 'Finalizada' se exige la conclusión, que es lo que se adjunta a la consulta.
 */
abstract class DopplerReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de los hallazgos del estudio, comunes al registro y a la edición.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function reglasEstudio(): array
    {
        $reglas = [
            'clinical_history_id' => ['nullable', 'integer', 'exists:clinical_histories,id'],
            'fecha_estudio' => ['nullable', 'date', 'before_or_equal:today'],
            'estado_registro' => ['nullable', 'string', Rule::in(['Borrador', 'Finalizada'])],
            'conclusion' => ['nullable', 'string', 'max:2000'],
        ];

        // Los mismos hallazgos se informan en ambos miembros inferiores
        foreach (DopplerReport::LADOS as $lado) {
            $reglas["{$lado}_profundo"] = ['nullable', 'string', 'max:1000'];
            $reglas["{$lado}_perforantes"] = ['nullable', 'string', 'max:255'];
            $reglas["{$lado}_trombosis"] = ['nullable', 'string', 'max:255'];

            foreach (DopplerReport::VASOS as $vaso) {
                $reglas["{$lado}_{$vaso}"] = ['nullable', 'string', 'max:255'];
                // El diámetro se mide en milímetros (ej: 4.1)
                $reglas["{$lado}_{$vaso}_diam"] = ['nullable', 'numeric', 'min:0', 'max:99.99'];
            }
        }

        return $reglas;
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
     * Validaciones que dependen del expediente y del estado del registro.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validarConsultaDelPaciente($validator);
            $this->validarRequisitosDeCierre($validator);
        });
    }

    /**
     * Verificar que la consulta asociada pertenezca al mismo paciente: el estudio
     * no puede adjuntarse al expediente de otra persona.
     */
    protected function validarConsultaDelPaciente(Validator $validator): void
    {
        $clinicalHistoryId = $this->input('clinical_history_id');
        $patientId = $this->pacienteDelEstudio();

        if (! $clinicalHistoryId || ! $patientId || $validator->errors()->has('clinical_history_id')) {
            return;
        }

        $consulta = ClinicalHistory::find($clinicalHistoryId);

        if ($consulta && (int) $consulta->patient_id !== (int) $patientId) {
            $validator->errors()->add(
                'clinical_history_id',
                'La consulta asociada pertenece a otro paciente.'
            );
        }
    }

    /**
     * Exigir la conclusión cuando el estudio se marca como Finalizada, porque es
     * la interpretación que se adjunta a la consulta del expediente.
     */
    protected function validarRequisitosDeCierre(Validator $validator): void
    {
        if ($this->estadoRegistroResultante() !== 'Finalizada') {
            return;
        }

        $conclusion = $this->conclusionResultante();

        if (! is_string($conclusion) || trim($conclusion) === '') {
            $validator->errors()->add(
                'conclusion',
                'Registre la conclusión del estudio para finalizar el reporte.'
            );
        }
    }

    /**
     * Conclusión con la que quedará el registro después de esta petición.
     */
    protected function conclusionResultante(): ?string
    {
        return $this->input('conclusion');
    }

    /**
     * Estado con el que quedará el registro después de esta petición.
     */
    abstract protected function estadoRegistroResultante(): string;

    /**
     * Paciente al que pertenece el estudio de esta petición.
     */
    abstract protected function pacienteDelEstudio(): ?int;

    /**
     * Custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mensajes = [
            'patient_id.required' => 'Debe seleccionar el paciente al que pertenece el estudio.',
            'patient_id.exists' => 'El paciente seleccionado no existe en la base de datos.',
            'clinical_history_id.exists' => 'La consulta asociada no existe en la base de datos.',
            'fecha_estudio.date' => 'La fecha del estudio no tiene un formato válido.',
            'fecha_estudio.before_or_equal' => 'La fecha del estudio no puede ser posterior al día de hoy.',
            'estado_registro.in' => 'El estado del registro debe ser: Borrador o Finalizada.',
            'conclusion.max' => 'La conclusión del estudio es demasiado extensa.',
        ];

        // Un diámetro mal digitado es el error más frecuente al informar el estudio
        foreach (DopplerReport::LADOS as $lado) {
            foreach (DopplerReport::VASOS as $vaso) {
                $campo = "{$lado}_{$vaso}_diam";
                $mensajes["{$campo}.numeric"] = 'El diámetro debe ser un valor numérico en milímetros (ej: 4.1).';
                $mensajes["{$campo}.min"] = 'El diámetro no puede ser negativo.';
                $mensajes["{$campo}.max"] = 'El diámetro registrado está fuera del rango válido (0 a 99.99 mm).';
            }
        }

        return $mensajes;
    }
}
