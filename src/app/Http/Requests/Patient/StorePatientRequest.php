<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
            'edad' => ['required', 'integer', 'min:0', 'max:120'],
            'telefono' => ['required', 'string', 'max:25'],
            'lugar_residencia' => ['required', 'string', 'max:255'],
            'estado_civil' => [
                'required',
                'string',
                Rule::in(['Soltero/a', 'Casado/a', 'Divorciado/a', 'Viudo/a', 'Unión Libre', 'Otro']),
            ],
            'religion' => ['nullable', 'string', 'max:100'],
            'estado' => [
                'nullable',
                'string',
                Rule::in(['Activo', 'Seguimiento', 'Alta']),
            ],
            'activo' => ['nullable', 'boolean'],
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
            'nombre.required' => 'El nombre completo del paciente es obligatorio.',
            'edad.required' => 'La edad del paciente es obligatoria.',
            'edad.integer' => 'La edad debe ser un número entero.',
            'telefono.required' => 'El teléfono de contacto es obligatorio.',
            'lugar_residencia.required' => 'El lugar de residencia es obligatorio.',
            'estado_civil.required' => 'El estado civil es obligatorio.',
            'estado_civil.in' => 'El estado civil seleccionado no es válido.',
            'estado.in' => 'El estado del paciente debe ser: Activo, Seguimiento o Alta.',
        ];
    }
}
