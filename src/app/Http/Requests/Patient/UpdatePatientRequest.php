<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
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
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'edad' => ['sometimes', 'required', 'integer', 'min:0', 'max:120'],
            'telefono' => ['sometimes', 'required', 'string', 'max:25'],
            'lugar_residencia' => ['sometimes', 'required', 'string', 'max:255'],
            'estado_civil' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['Soltero/a', 'Casado/a', 'Divorciado/a', 'Viudo/a', 'Unión Libre', 'Otro']),
            ],
            'religion' => ['nullable', 'string', 'max:100'],
            'estado' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['Activo', 'Seguimiento', 'Alta']),
            ],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre completo del paciente no puede estar vacío.',
            'edad.required' => 'La edad del paciente es obligatoria.',
            'edad.integer' => 'La edad debe ser un número entero.',
            'telefono.required' => 'El teléfono de contacto es obligatorio.',
            'lugar_residencia.required' => 'El lugar de residencia es obligatorio.',
            'estado_civil.in' => 'El estado civil seleccionado no es válido.',
            'estado.in' => 'El estado del paciente debe ser: Activo, Seguimiento o Alta.',
        ];
    }
}
