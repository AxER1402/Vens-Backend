<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
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
            'patient_id' => ['sometimes', 'required', 'integer', 'exists:patients,id'],
            'medico_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'fecha_hora_inicio' => ['sometimes', 'required', 'date_format:Y-m-d H:i:s,Y-m-d\TH:i:s,Y-m-d\TH:i:sP,Y-m-d\TH:i:s.v\Z'],
            'fecha_hora_fin' => ['sometimes', 'required', 'date_format:Y-m-d H:i:s,Y-m-d\TH:i:s,Y-m-d\TH:i:sP,Y-m-d\TH:i:s.v\Z', 'after:fecha_hora_inicio'],
            'motivo' => ['sometimes', 'required', 'string', 'max:255'],
            'estado' => ['sometimes', 'required', Rule::in(['Programada', 'Confirmada', 'Completada', 'Reagendada', 'Cancelada', 'No Asistió'])],
            'motivo_cancelacion' => ['nullable', 'string'],
            'notas' => ['nullable', 'string'],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'patient_id.exists' => 'El paciente seleccionado no existe en la base de datos.',
            'medico_id.exists' => 'El médico seleccionado no existe en la base de datos.',
            'fecha_hora_fin.after' => 'La fecha/hora fin debe ser posterior a la fecha/hora de inicio.',
            'estado.in' => 'El estado especificado no es válido.',
        ];
    }
}
