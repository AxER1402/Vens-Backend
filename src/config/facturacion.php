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

        // El nombre del establecimiento, cuando no es el mismo que el de quien
        // factura: en la FEL va debajo del NIT, en su propia línea.
        'nombre_comercial' => env('FACTURACION_NOMBRE_COMERCIAL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificación FEL
    |--------------------------------------------------------------------------
    |
    | 'interna': el propio sistema genera el número de autorización, la serie
    | y el número de DTE, y la factura sale impresa como certificada.
    |
    | 'pendiente': no se certifica nada y la factura queda esperando.
    |
    */

    'certificacion' => env('FACTURACION_CERTIFICACION', 'interna'),

    'certificador' => [
        'nombre' => env('FACTURACION_CERTIFICADOR_NOMBRE', 'Certificador FEL'),
        'nit' => env('FACTURACION_CERTIFICADOR_NIT', ''),
    ],

    // La frase del régimen del emisor, que la SAT exige impresa en la factura
    'frase' => env('FACTURACION_FRASE', 'Sujeto a retención definitiva ISR'),

    // Lo que lleva el código QR de la factura impresa. Admite {uuid},
    // {emisor}, {receptor} y {monto}; puede ser la URL de un verificador.
    'qr' => env('FACTURACION_QR', 'DTE:{uuid}|EMISOR:{emisor}|RECEPTOR:{receptor}|MONTO:{monto}'),

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
