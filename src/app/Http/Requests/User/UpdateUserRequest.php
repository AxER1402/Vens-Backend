<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        /** @var \App\Models\User|string|int|null $userParam */
        $userParam = $this->route('user');
        $userId = is_object($userParam) ? $userParam->id : $userParam;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'rol' => ['sometimes', 'required', 'string', Rule::in(['administrador', 'medico', 'enfermera', 'recepcionista'])],
            'telefono' => ['nullable', 'string', 'max:20'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Mensajes de validación personalizados en español.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre debe ser una cadena de texto.',
            'name.max' => 'El nombre no puede exceder los 255 caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingrese una dirección de correo electrónico válida.',
            'email.unique' => 'El correo electrónico ya se encuentra registrado por otro usuario.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'rol.required' => 'El rol del usuario es obligatorio.',
            'rol.in' => 'El rol seleccionado no es válido. Los roles permitidos son: administrador, medico, enfermera, recepcionista.',
            'telefono.max' => 'El teléfono no puede exceder los 20 caracteres.',
            'activo.boolean' => 'El campo activo debe ser un valor booleano (true o false).',
        ];
    }
}
