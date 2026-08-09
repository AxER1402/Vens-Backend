<?php

namespace App\Http\Requests\ClinicalHistory;

class StoreClinicalHistoryRequest extends ClinicalHistoryRequest
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
        ], $this->reglasClinicas());
    }

    /**
     * Al registrar, la fecha de consulta se asume el día actual si no se envía.
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (! $this->filled('fecha_consulta')) {
            $this->merge(['fecha_consulta' => now()->toDateString()]);
        }
    }
}
