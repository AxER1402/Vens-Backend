<?php

namespace App\Http\Requests\Ajuste;

use App\Support\Ajustes\Ajustes;
use App\Support\Contacto\Telefono;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Los datos de la clínica.
 *
 * Las reglas se arman a partir de Ajustes::CAMPOS en vez de escribirse a mano:
 * así, añadir un ajuste es añadir una fila a esa lista y no acordarse de tocar
 * también este archivo.
 */
class UpdateAjustesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El teléfono se limpia antes de validar, como en el resto del sistema.
     *
     * Se reescribe el grupo entero y no la clave con punto: `merge` toma la
     * clave al pie de la letra, así que pasarle 'clinica.telefono' crearía un
     * campo llamado así en la raíz en vez de tocar el que está dentro de
     * 'clinica', y la validación seguiría viendo el valor sin limpiar.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('clinica.telefono')) {
            return;
        }

        $clinica = (array) $this->input('clinica', []);
        $clinica['telefono'] = Telefono::normalizar($clinica['telefono'] ?? null);

        $this->merge(['clinica' => $clinica]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reglas = [];

        foreach (Ajustes::CAMPOS as $clave => $campo) {
            if (! ($campo['editable'] ?? true)) {
                continue;
            }

            // Todo es opcional: el formulario manda lo que cambió, y una
            // clínica puede no tener todavía NIT o correo.
            $reglas[$clave] = match ($campo['tipo']) {
                'porcentaje' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
                'correo' => ['sometimes', 'nullable', 'string', 'email', 'max:'.$campo['max']],
                'telefono' => Telefono::reglas(false, true),
                default => ['sometimes', 'nullable', 'string', 'max:'.($campo['max'] ?? 255)],
            };
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(Telefono::mensajes('clinica.telefono'), [
            'facturacion.iva.numeric' => 'El porcentaje de IVA debe ser un número.',
            'facturacion.iva.min' => 'El porcentaje de IVA no puede ser negativo.',
            'facturacion.iva.max' => 'El porcentaje de IVA no puede pasar de 100.',
            'clinica.correo.email' => 'Escriba una dirección de correo válida.',
            'facturacion.moneda.max' => 'La moneda se escribe con su código de tres letras (GTQ, USD).',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'clinica.nombre' => 'nombre de la clínica',
            'clinica.especialidad' => 'especialidad',
            'clinica.direccion' => 'dirección',
            'clinica.telefono' => 'teléfono',
            'clinica.correo' => 'correo',
            'facturacion.emisor' => 'razón social',
            'facturacion.nit' => 'NIT',
            'facturacion.direccion' => 'dirección fiscal',
            'facturacion.serie' => 'serie',
            'facturacion.moneda' => 'moneda',
            'facturacion.iva' => 'porcentaje de IVA',
            'medico.nombre' => 'nombre del médico responsable',
            'medico.colegiado' => 'número de colegiado',
        ];
    }
}
