<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var \App\Models\Service|string|int|null $servicio */
        $servicio = $this->route('service');
        $id = is_object($servicio) ? $servicio->id : $servicio;

        return [
            'nombre' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('services', 'nombre')->ignore($id),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'precio' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del servicio es obligatorio.',
            'nombre.unique' => 'Ya existe otro servicio con ese nombre.',
            'nombre.max' => 'El nombre no puede exceder los 150 caracteres.',
            'precio.required' => 'El precio es obligatorio.',
            'precio.numeric' => 'El precio debe ser un número.',
            'precio.min' => 'El precio no puede ser negativo.',
        ];
    }
}
