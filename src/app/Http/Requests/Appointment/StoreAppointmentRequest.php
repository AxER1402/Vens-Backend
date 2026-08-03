<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
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
            'medico_id' => ['nullable', 'integer', 'exists:users,id'],
            'fecha_hora_inicio' => ['required', 'date_format:Y-m-d H:i:s,Y-m-d\TH:i:s,Y-m-d\TH:i:sP,Y-m-d\TH:i:s.v\Z'],
            'fecha_hora_fin' => ['required', 'date_format:Y-m-d H:i:s,Y-m-d\TH:i:s,Y-m-d\TH:i:sP,Y-m-d\TH:i:s.v\Z', 'after:fecha_hora_inicio'],
            'motivo' => ['required', 'string', 'max:255'],
            'notas' => ['nullable', 'string'],
        ];
    }

    /**
     * Dynamic messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'patient_id.required' => 'El paciente es obligatorio para agendar la cita.',
            'patient_id.exists' => 'El paciente seleccionado no existe en la base de datos.',
            'medico_id.exists' => 'El médico seleccionado no existe en la base de datos.',
            'fecha_hora_inicio.required' => 'La fecha y hora de inicio son requeridas.',
            'fecha_hora_fin.required' => 'La fecha y hora de fin son requeridas.',
            'fecha_hora_fin.after' => 'La fecha/hora fin debe ser posterior a la fecha/hora de inicio.',
            'motivo.required' => 'El motivo de la cita es obligatorio.',
        ];
    }
}
