<?php

namespace App\Http\Requests\Profile;

use App\Support\Contacto\Telefono;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Los datos que un usuario puede cambiarse a sí mismo.
 *
 * Deliberadamente no están aquí ni el correo, ni el rol, ni el estado activo:
 * el correo es con lo que se inicia sesión y con lo que llega el enlace de
 * recuperación, y el rol es el permiso. Que alguien se los cambie solo es
 * abrirse una puerta, no editar su perfil; eso pasa por el administrador.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El teléfono llega como lo escribió quien lo tecleó: con guiones o
     * espacios. Se limpia antes de validar, igual que en el resto del sistema.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('telefono')) {
            $this->merge(['telefono' => Telefono::normalizar($this->input('telefono'))]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'telefono' => Telefono::reglas(false),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(Telefono::mensajes(), [
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre debe ser una cadena de texto.',
            'name.max' => 'El nombre no puede exceder los 255 caracteres.',
        ]);
    }
}
