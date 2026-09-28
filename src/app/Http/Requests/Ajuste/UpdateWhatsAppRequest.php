<?php

namespace App\Http\Requests\Ajuste;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Encender o apagar los recordatorios de citas por WhatsApp.
 */
class UpdateWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recordatorios' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recordatorios.required' => 'Indique si los recordatorios quedan activados o no.',
            'recordatorios.boolean' => 'Los recordatorios solo pueden quedar activados o desactivados.',
        ];
    }
}
