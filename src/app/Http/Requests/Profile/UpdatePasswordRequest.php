<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cambio de contraseña desde dentro de la sesión.
 *
 * Se pide la contraseña actual aunque el usuario ya esté autenticado: una
 * sesión abierta en una computadora del mostrador no prueba quién está
 * sentado delante, y sin ese dato bastaría con encontrar la pantalla sin
 * bloquear para quedarse con la cuenta.
 */
class UpdatePasswordRequest extends FormRequest
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
        return [
            'password_actual' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:password_actual'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password_actual.required' => 'Debe ingresar su contraseña actual.',
            'password.required' => 'La nueva contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'password.different' => 'La nueva contraseña debe ser distinta de la actual.',
        ];
    }
}
