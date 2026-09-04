<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Datos de la clínica que emite
    |--------------------------------------------------------------------------
    */

    'emisor' => [
        'nombre' => env('FACTURACION_EMISOR', 'Clínica Doctora Yojana Mendoza — Flebología'),
        'nit' => env('FACTURACION_NIT', 'CF'),
        'direccion' => env('FACTURACION_DIRECCION', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Correlativo y moneda
    |--------------------------------------------------------------------------
    |
    | La serie es el correlativo propio de la clínica, independiente del que
    | asigna la SAT al certificar.
    |
    */

    'serie' => env('FACTURACION_SERIE', 'A'),
    'moneda' => env('FACTURACION_MONEDA', 'GTQ'),

    /*
    |--------------------------------------------------------------------------
    | IVA
    |--------------------------------------------------------------------------
    |
    | En Guatemala el impuesto va incluido en el precio: el total es lo que
    | paga el paciente y la base se desglosa hacia atrás. Se guarda en cada
    | documento el porcentaje con el que se calculó, para que cambiar este
    | valor no altere lo ya emitido.
    |
    */

    'iva_porcentaje' => (float) env('FACTURACION_IVA', 12),

];
