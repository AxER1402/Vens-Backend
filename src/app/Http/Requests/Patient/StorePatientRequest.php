<?php

namespace App\Http\Requests\Patient;

use App\Support\Contacto\Telefono;
use App\Support\Pacientes\Duplicados;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
     * El teléfono llega como lo escribió quien lo copió de una tarjeta: con
     * guiones, espacios o paréntesis. Se limpia antes de validar para que el
     * sistema guarde siempre el mismo formato y la búsqueda encuentre.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('telefono')) {
            $this->merge(['telefono' => Telefono::normalizar($this->input('telefono'))]);
        }
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
            'telefono' => Telefono::reglas(true),
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
     * Regla de negocio: no se registra dos veces al mismo paciente (mismo
     * nombre y teléfono). Se revisa después de las reglas de formato, para
     * no comparar contra datos que de todos modos se van a rechazar.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $existente = Duplicados::buscar($this->input('nombre'), $this->input('telefono'));

            if ($existente !== null) {
                $validator->errors()->add('nombre', Duplicados::mensaje($existente));
            }
        });
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(Telefono::mensajes(), [
            'nombre.required' => 'El nombre completo del paciente es obligatorio.',
            'edad.required' => 'La edad del paciente es obligatoria.',
            'edad.integer' => 'La edad debe ser un número entero.',
            'lugar_residencia.required' => 'El lugar de residencia es obligatorio.',
            'estado_civil.required' => 'El estado civil es obligatorio.',
            'estado_civil.in' => 'El estado civil seleccionado no es válido.',
            'estado.in' => 'El estado del paciente debe ser: Activo, Seguimiento o Alta.',
        ]);
    }
}
