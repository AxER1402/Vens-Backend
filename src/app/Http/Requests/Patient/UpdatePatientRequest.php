<?php

namespace App\Http\Requests\Patient;

use App\Support\Contacto\Telefono;
use App\Support\Pacientes\Duplicados;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'edad' => ['sometimes', 'required', 'integer', 'min:0', 'max:120'],
            'telefono' => Telefono::reglas(true, parcial: true),
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
     * Al cambiar el nombre o el teléfono, el paciente no puede quedar igual a
     * otro ya registrado. Si la edición no toca esos datos no se revisa, para
     * no bloquear cambios ajenos sobre registros anteriores a esta regla.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->hasAny(['nombre', 'telefono'])) {
                return;
            }

            $paciente = $this->route('patient');

            $existente = Duplicados::buscar(
                $this->input('nombre', $paciente?->nombre),
                $this->input('telefono', $paciente?->telefono),
                $paciente?->getKey(),
            );

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
            'nombre.required' => 'El nombre completo del paciente no puede estar vacío.',
            'edad.required' => 'La edad del paciente es obligatoria.',
            'edad.integer' => 'La edad debe ser un número entero.',
            'lugar_residencia.required' => 'El lugar de residencia es obligatorio.',
            'estado_civil.in' => 'El estado civil seleccionado no es válido.',
            'estado.in' => 'El estado del paciente debe ser: Activo, Seguimiento o Alta.',
        ]);
    }
}
