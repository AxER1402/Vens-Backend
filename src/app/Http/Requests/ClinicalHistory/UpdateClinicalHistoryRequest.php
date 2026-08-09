<?php

namespace App\Http\Requests\ClinicalHistory;

class UpdateClinicalHistoryRequest extends ClinicalHistoryRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * El paciente no se incluye a propósito: una historia clínica no se traslada
     * a otro expediente. Si se envía patient_id, simplemente se ignora.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->reglasClinicas();
    }
}
