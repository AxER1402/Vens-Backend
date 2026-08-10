<?php

namespace App\Http\Requests\DopplerReport;

class StoreDopplerReportRequest extends DopplerReportRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
        ], $this->reglasEstudio());
    }

    /**
     * Al registrar, el estudio se fecha el día actual si no se envía y se guarda
     * finalizado salvo que el formulario pida explícitamente un borrador.
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (! $this->filled('fecha_estudio')) {
            $this->merge(['fecha_estudio' => now()->toDateString()]);
        }

        if (! $this->filled('estado_registro')) {
            $this->merge(['estado_registro' => 'Finalizada']);
        }
    }

    /**
     * Estado con el que quedará el registro después de esta petición.
     */
    protected function estadoRegistroResultante(): string
    {
        return (string) $this->input('estado_registro', 'Finalizada');
    }

    /**
     * Paciente al que pertenece el estudio de esta petición.
     */
    protected function pacienteDelEstudio(): ?int
    {
        $patientId = $this->input('patient_id');

        return is_numeric($patientId) ? (int) $patientId : null;
    }
}
