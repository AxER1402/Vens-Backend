<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
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
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'clinical_history_id' => ['nullable', 'integer', 'exists:clinical_histories,id'],
            'tipo' => ['required', Rule::in([Invoice::TIPO_RECIBO, Invoice::TIPO_FACTURA])],
            'fecha_emision' => ['nullable', 'date'],

            // El NIT solo se exige en la factura: un recibo interno se entrega
            // igual a quien no lo trae.
            'nit_receptor' => ['nullable', 'string', 'max:20'],
            'nombre_receptor' => ['required', 'string', 'max:150'],
            'direccion_receptor' => ['nullable', 'string', 'max:255'],

            'metodo_pago' => ['required', 'string', 'max:40'],
            'observaciones' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.tipo' => ['nullable', Rule::in(['B', 'S'])],
            'items.*.descripcion' => ['required', 'string', 'max:255'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.descuento' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Una factura sin NIT no se puede certificar, así que se rechaza al
     * emitirla y no cuando ya está entregada. El recibo no lo necesita.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $esFactura = $this->input('tipo') === Invoice::TIPO_FACTURA;
            $nit = trim((string) $this->input('nit_receptor'));

            if ($esFactura && $nit === '') {
                $validator->errors()->add(
                    'nit_receptor',
                    'La factura necesita el NIT del receptor. Use CF si el paciente no lo proporciona.'
                );
            }

            foreach ((array) $this->input('items', []) as $i => $item) {
                $bruto = (float) ($item['cantidad'] ?? 0) * (float) ($item['precio_unitario'] ?? 0);

                if ((float) ($item['descuento'] ?? 0) > $bruto) {
                    $validator->errors()->add(
                        "items.{$i}.descuento",
                        'El descuento no puede ser mayor que el renglón que descuenta.'
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'patient_id.required' => 'El documento tiene que ir a nombre de un paciente registrado.',
            'nombre_receptor.required' => 'Falta el nombre de quien recibe el documento.',
            'metodo_pago.required' => 'Indique cómo se pagó.',
            'items.required' => 'Un documento sin renglones no cobra nada: agregue al menos uno.',
            'items.min' => 'Un documento sin renglones no cobra nada: agregue al menos uno.',
            'items.*.descripcion.required' => 'Cada renglón necesita decir qué se está cobrando.',
            'items.*.cantidad.min' => 'La cantidad de un renglón tiene que ser mayor que cero.',
        ];
    }
}
