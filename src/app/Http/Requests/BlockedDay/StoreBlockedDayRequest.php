<?php

namespace App\Http\Requests\BlockedDay;

use App\Models\BlockedDay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBlockedDayRequest extends FormRequest
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
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'motivo' => ['required', 'string', 'max:255'],
            'tipo' => ['nullable', Rule::in(BlockedDay::TIPOS)],
        ];
    }

    /**
     * Dynamic messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'fecha_inicio.required' => 'La fecha de inicio del bloqueo es obligatoria.',
            'fecha_inicio.date_format' => 'La fecha de inicio debe tener el formato AAAA-MM-DD.',
            'fecha_fin.required' => 'La fecha de fin del bloqueo es obligatoria.',
            'fecha_fin.date_format' => 'La fecha de fin debe tener el formato AAAA-MM-DD.',
            'fecha_fin.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
            'motivo.required' => 'Debe indicar el motivo del bloqueo (ej. Día de la Independencia).',
            'tipo.in' => 'El tipo de bloqueo especificado no es válido.',
        ];
    }
}
