<?php

namespace App\Http\Requests\ClinicalHistory;

use Illuminate\Foundation\Http\FormRequest;

class StoreVenousMapRequest extends FormRequest
{
    /**
     * Tamaño máximo permitido del PNG decodificado (5 MB).
     */
    public const MAX_BYTES = 5 * 1024 * 1024;

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
            // El canvas del mapeo venoso genera un PNG en formato data URL
            'imagen' => ['required', 'string', 'regex:/^data:image\/png;base64,[A-Za-z0-9+\/=\s]+$/'],
        ];
    }

    /**
     * Devolver el contenido binario del PNG recibido.
     */
    public function contenidoBinario(): ?string
    {
        $base64 = substr($this->input('imagen'), strlen('data:image/png;base64,'));

        $binario = base64_decode(preg_replace('/\s+/', '', $base64), true);

        return $binario === false ? null : $binario;
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'imagen.required' => 'No se recibió el mapeo venoso a guardar.',
            'imagen.regex' => 'El mapeo venoso debe enviarse como una imagen PNG válida.',
        ];
    }
}
