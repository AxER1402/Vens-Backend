<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;

class AssignPatientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Debe proporcionar el ID del paciente a asignar.',
            'patient_id.exists' => 'El paciente seleccionado no existe en la base de datos.',
        ];
    }
}
