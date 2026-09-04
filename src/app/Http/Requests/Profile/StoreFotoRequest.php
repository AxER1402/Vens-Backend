<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La foto de perfil.
 *
 * El límite de 2 MB no es tacañería de disco: la imagen se muestra siempre
 * pequeña —el avatar del menú y el del panel lateral— y subir el archivo de
 * doce megapixeles que salió del teléfono solo hace lenta cada carga de la
 * aplicación para pintar un círculo de cuarenta píxeles.
 */
class StoreFotoRequest extends FormRequest
{
    /** Kilobytes. */
    public const MAXIMO_KB = 2048;

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
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAXIMO_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'foto.required' => 'Seleccione una imagen.',
            'foto.image' => 'El archivo debe ser una imagen.',
            'foto.mimes' => 'La imagen debe estar en formato JPG, PNG o WEBP.',
            'foto.max' => 'La imagen no puede pesar más de 2 MB.',
        ];
    }
}
