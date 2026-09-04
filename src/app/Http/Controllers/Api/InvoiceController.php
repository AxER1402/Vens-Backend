<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Support\Facturacion\Certificador;
use App\Support\Facturacion\Totales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function __construct(private readonly Certificador $certificador)
    {
    }

    /**
     * Documentos emitidos, con los filtros que usan el historial y los reportes.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()->with([
            'items',
            'patient:id,nombre,telefono',
            'creator:id,name',
        ]);

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->byDateRange($request->input('from_date'), $request->input('to_date'));
        }

        if ($request->filled('patient_id')) {
            $query->byPatient((int) $request->input('patient_id'));
        }

        if ($request->filled('clinical_history_id')) {
            $query->where('clinical_history_id', (int) $request->input('clinical_history_id'));
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        // Los anulados no se borran, pero tampoco estorban salvo que se pidan
        if (! $request->boolean('incluir_anuladas')) {
            $query->vigentes();
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('fecha_emision')->orderByDesc('id')->get(),
        ], 200);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['items', 'patient', 'clinicalHistory:id,fecha_consulta,patient_id', 'creator:id,name']);

        return response()->json(['success' => true, 'data' => $invoice], 200);
    }

    /**
     * Emitir un documento de cobro.
     *
     * Las cuentas las hace el servidor sobre las cantidades y los precios que
     * llegan, y nunca se toma el total que manda el cliente: el importe de un
     * documento no puede depender de lo que diga el navegador.
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $ivaPorcentaje = (float) config('facturacion.iva_porcentaje', 12);

        $cuentas = Totales::calcular($datos['items'], $ivaPorcentaje);

        $documento = DB::transaction(function () use ($datos, $cuentas, $ivaPorcentaje, $request): Invoice {
            $serie = (string) config('facturacion.serie', 'A');

            // El correlativo se toma dentro de la transacción y con la fila
            // bloqueada, o dos cobros simultáneos se llevan el mismo número.
            Invoice::query()->where('serie', $serie)->lockForUpdate()->max('numero');

            $documento = Invoice::create([
                'patient_id' => $datos['patient_id'],
                'clinical_history_id' => $datos['clinical_history_id'] ?? null,
                'created_by' => $request->user()?->id,
                'tipo' => $datos['tipo'],
                'serie' => $serie,
                'numero' => Invoice::siguienteNumero($serie),
                'fecha_emision' => $datos['fecha_emision'] ?? now()->toDateString(),
                'nit_receptor' => trim((string) ($datos['nit_receptor'] ?? '')) ?: Invoice::NIT_CONSUMIDOR_FINAL,
                'nombre_receptor' => $datos['nombre_receptor'],
                'direccion_receptor' => $datos['direccion_receptor'] ?? null,
                'moneda' => (string) config('facturacion.moneda', 'GTQ'),
                'subtotal' => $cuentas['subtotal'],
                'descuento' => $cuentas['descuento'],
                'total' => $cuentas['total'],
                'iva_porcentaje' => $ivaPorcentaje,
                'iva_monto' => $cuentas['iva_monto'],
                'metodo_pago' => $datos['metodo_pago'],
                'estado' => 'Emitida',
                'observaciones' => $datos['observaciones'] ?? null,
                'fel_estado' => $datos['tipo'] === Invoice::TIPO_FACTURA ? 'Pendiente' : 'No aplica',
            ]);

            $documento->items()->createMany($cuentas['renglones']);

            return $documento;
        });

        // Una factura además se manda a certificar. Hoy no hay certificador
        // contratado y vuelve «Pendiente»; el documento ya quedó guardado, así
        // que no se pierde nada mientras tanto.
        if ($documento->tipo === Invoice::TIPO_FACTURA) {
            $resultado = $this->certificador->certificar($documento);

            $documento->update([
                'fel_estado' => $resultado['estado'],
                'fel_uuid' => $resultado['uuid'],
                'fel_serie' => $resultado['serie'],
                'fel_numero' => $resultado['numero'],
                'fel_certificador' => $resultado['certificador'],
                'fel_mensaje' => $resultado['mensaje'],
                'fel_certificado_at' => $resultado['estado'] === 'Certificada' ? now() : null,
            ]);
        }

        $documento->load(['items', 'patient:id,nombre,telefono']);

        return response()->json([
            'success' => true,
            'message' => $documento->tipo === Invoice::TIPO_FACTURA
                ? "Factura {$documento->correlativo} emitida. {$documento->fel_mensaje}"
                : "Recibo {$documento->correlativo} emitido.",
            'data' => $documento,
        ], 201);
    }

    /**
     * Anular un documento.
     *
     * No se borra ni se corrige: un documento de cobro entregado tiene que
     * seguir existiendo con su número, marcado como anulado y con el motivo a
     * la vista. Deja de contar para los ingresos desde ese momento.
     */
    public function anular(Request $request, Invoice $invoice): JsonResponse
    {
        $request->validate(
            ['motivo_anulacion' => ['required', 'string', 'max:255']],
            ['motivo_anulacion.required' => 'Indique por qué se anula el documento.']
        );

        if ($invoice->estado === 'Anulada') {
            return response()->json([
                'success' => false,
                'message' => 'El documento ya estaba anulado.',
            ], 422);
        }

        $invoice->update([
            'estado' => 'Anulada',
            'motivo_anulacion' => $request->input('motivo_anulacion'),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Documento {$invoice->correlativo} anulado.",
            'data' => $invoice->fresh(['items']),
        ], 200);
    }
}
