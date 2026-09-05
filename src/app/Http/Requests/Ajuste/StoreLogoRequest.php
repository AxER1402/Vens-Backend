<?php

namespace App\Http\Requests\Ajuste;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El logo del membrete.
 *
 * Se acepta PNG y JPG, pero no WEBP: el logo no lo pinta un navegador, lo
 * incrusta mPDF al componer el PDF del informe, y ahí el WEBP no entra.
 */
class StoreLogoRequest extends FormRequest
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
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:'.self::MAXIMO_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.required' => 'Seleccione una imagen.',
            'logo.image' => 'El archivo debe ser una imagen.',
            'logo.mimes' => 'El logo debe estar en formato PNG o JPG: es el que sabe incrustar el generador de PDF.',
            'logo.max' => 'La imagen no puede pesar más de 2 MB.',
        ];
    }
}
