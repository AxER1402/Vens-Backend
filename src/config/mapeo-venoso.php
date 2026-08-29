<?php

/*
|--------------------------------------------------------------------------
| Catálogo clínico del mapeo venoso
|--------------------------------------------------------------------------
|
| Fuente única de verdad de los hallazgos, las zonas anatómicas y la plantilla
| sobre la que se dibuja. El editor guarda referencias ('perforante',
| 'izq_antero_interna') y es este catálogo el que las traduce a texto legible:
| sin él, el reporte imprimiría códigos en crudo en lugar de
| "Perforante insuficiente — MII, cara antero-interna".
|
| Vive en config/ y no en la tabla clinical_options a propósito. clinical_options
| guarda listas que el médico marca en un formulario y que se editan en caliente;
| esto es distinto: lleva datos de render (color, grosor, patrón de línea,
| símbolo) acoplados a componentes del editor que se versionan con el código. Si
| el catálogo pudiera cambiar en base de datos, un mapeo archivado se leería
| distinto a como se dibujó.
|
| El frontend lo consume por GET /api/v1/venous-map/catalog.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Plantilla
    |--------------------------------------------------------------------------
    |
    | El ancho y el alto NO son el tamaño en píxeles del archivo: son la rejilla
    | del viewBox en la que el editor expresa los grosores de trazo. Se mantienen
    | fijos para que cambiar la plantilla por un escaneo de mayor resolución no
    | altere el grosor de los mapeos ya archivados.
    |
    | Las coordenadas de los objetos se guardan normalizadas 0-1 respecto a esta
    | plantilla, así que tampoco dependen de estos valores.
    |
    */
    'plantilla' => [
        'id' => 'merit-mmii-6-vistas',
        'ancho' => 1450,
        'alto' => 848,
        // Ruta en el disco local, relativa a public/. La usa el PDF del mapeo.
        'imagen' => 'img/mapeo-venoso-plantilla.png',
    ],

    /*
    |--------------------------------------------------------------------------
    | Miembros
    |--------------------------------------------------------------------------
    */
    'miembros' => [
        'izq' => ['id' => 'izq', 'abrev' => 'MII', 'label' => 'Miembro inferior izquierdo'],
        'der' => ['id' => 'der', 'abrev' => 'MID', 'label' => 'Miembro inferior derecho'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Zonas anatómicas
    |--------------------------------------------------------------------------
    |
    | La plantilla trae seis vistas de miembro inferior repartidas en dos
    | paneles. Cada objeto que el médico dibuja se atribuye a la vista donde cayó,
    | y por eso el mapeo deja de ser un dibujo y pasa a ser un dato.
    |
    | `rect` es [x0, y0, x1, y1] en coordenadas normalizadas. El límite entre dos
    | vistas vecinas es el punto medio entre ellas, de modo que un marcador puesto
    | un poco fuera del contorno igual se atribuye bien. La franja central
    | (0.480 - 0.530) separa los dos paneles y a propósito no pertenece a ninguna
    | zona: un clic ahí no está sobre ningún miembro.
    |
    */
    'zonas' => [
        ['id' => 'izq_posterior',      'miembro' => 'izq', 'cara' => 'Cara posterior',      'rect' => [0.000, 0.078, 0.162, 0.923]],
        ['id' => 'izq_antero_interna', 'miembro' => 'izq', 'cara' => 'Cara antero-interna', 'rect' => [0.162, 0.078, 0.343, 0.923]],
        ['id' => 'izq_antero_externa', 'miembro' => 'izq', 'cara' => 'Cara antero-externa', 'rect' => [0.343, 0.078, 0.480, 0.923]],
        ['id' => 'der_antero_externa', 'miembro' => 'der', 'cara' => 'Cara antero-externa', 'rect' => [0.530, 0.078, 0.666, 0.923]],
        ['id' => 'der_antero_interna', 'miembro' => 'der', 'cara' => 'Cara antero-interna', 'rect' => [0.666, 0.078, 0.847, 0.923]],
        ['id' => 'der_posterior',      'miembro' => 'der', 'cara' => 'Cara posterior',      'rect' => [0.847, 0.078, 1.000, 0.923]],
    ],

    /*
    |--------------------------------------------------------------------------
    | Hallazgos
    |--------------------------------------------------------------------------
    |
    | El médico elige el *hallazgo*, no el color. Así el mapeo genera información
    | estructurada en lugar de un dibujo cuya lectura dependa de quién lo trazó.
    |
    | Cada hallazgo se distingue por color **y** por forma o patrón de línea: el
    | mapeo se imprime a menudo en blanco y negro y debe seguir siendo legible, y
    | el color por sí solo tampoco sirve para un médico con daltonismo.
    |
    | `patron` es un stroke-dasharray; null significa línea continua.
    | `simbolo` referencia un <symbol> definido en el editor.
    |
    */
    'hallazgos' => [

        // ── Trazos: recorridos venosos ───────────────────────────────────────
        [
            'id' => 'safena_interna',
            'tipo' => 'trazo',
            'label' => 'Safena interna (Mayor)',
            'abrev' => 'SI',
            'color' => '#0C7D8C',
            'grosor' => 3,
            'patron' => null,
            'simbolo' => null,
            'ayuda' => 'Trayecto de la vena safena mayor.',
        ],
        [
            'id' => 'safena_externa',
            'tipo' => 'trazo',
            'label' => 'Safena externa (Menor)',
            'abrev' => 'SE',
            'color' => '#243757',
            'grosor' => 3,
            'patron' => null,
            'simbolo' => null,
            'ayuda' => 'Trayecto de la vena safena menor, cara posterior.',
        ],
        [
            'id' => 'colateral',
            'tipo' => 'trazo',
            'label' => 'Colateral / tributaria',
            'abrev' => 'COL',
            'color' => '#3A5F6F',
            'grosor' => 2,
            'patron' => null,
            'simbolo' => null,
            'ayuda' => 'Ramas tributarias que drenan a los troncos safenos.',
        ],
        [
            'id' => 'varice',
            'tipo' => 'trazo',
            'label' => 'Vena varicosa',
            'abrev' => 'VAR',
            'color' => '#B43C32',
            'grosor' => 5,
            'patron' => null,
            'simbolo' => null,
            'ayuda' => 'Dilatación varicosa visible o palpable.',
        ],
        [
            'id' => 'reticular',
            'tipo' => 'trazo',
            'label' => 'Reticulares / telangiectasias',
            'abrev' => 'RET',
            'color' => '#8E5AA8',
            'grosor' => 2,
            'patron' => '5 4',
            'simbolo' => null,
            'ayuda' => 'Red reticular y telangiectasias.',
        ],

        // ── Marcadores: hallazgos puntuales ──────────────────────────────────
        [
            'id' => 'perforante',
            'tipo' => 'marcador',
            'label' => 'Perforante insuficiente',
            'abrev' => 'P',
            'color' => '#B43C32',
            'grosor' => null,
            'patron' => null,
            'simbolo' => 'aspa',
            'ayuda' => 'Perforante con reflujo. Se numera para correlacionar con el Ecodöppler.',
        ],
        [
            'id' => 'cayado',
            'tipo' => 'marcador',
            'label' => 'Cayado (unión safeno-femoral / poplítea)',
            'abrev' => 'CAY',
            'color' => '#243757',
            'grosor' => null,
            'patron' => null,
            'simbolo' => 'doble-circulo',
            'ayuda' => 'Unión safeno-femoral o safeno-poplítea.',
        ],
        [
            'id' => 'trombo',
            'tipo' => 'marcador',
            'label' => 'Trombo / trombosis',
            'abrev' => 'TR',
            'color' => '#1B2A42',
            'grosor' => null,
            'patron' => null,
            'simbolo' => 'rombo',
            'ayuda' => 'Segmento trombosado.',
        ],
        [
            'id' => 'ulcera',
            'tipo' => 'marcador',
            'label' => 'Úlcera',
            'abrev' => 'ULC',
            'color' => '#B43C32',
            'grosor' => null,
            'patron' => null,
            'simbolo' => 'circulo-relleno',
            'ayuda' => 'Úlcera venosa activa o cicatrizada.',
        ],
        [
            'id' => 'puncion',
            'tipo' => 'marcador',
            'label' => 'Punto de punción / escleroterapia',
            'abrev' => 'PN',
            'color' => '#6B8F71',
            'grosor' => null,
            'patron' => null,
            'simbolo' => 'punto',
            'ayuda' => 'Sitio de punción previsto o ya tratado.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Límites del documento vectorial
    |--------------------------------------------------------------------------
    |
    | Topes defensivos para que una petición manipulada no llene la columna JSON.
    | `max_puntos_total` es el que realmente acota el tamaño: limitar solo el
    | número de objetos no sirve de nada porque un único trazo puede traer
    | cientos de miles de puntos.
    |
    | Un mapeo real no pasa de unas decenas de trazos y marcas.
    |
    */
    'limites' => [
        'max_objetos' => 500,
        'max_puntos_total' => 20000,
        'max_puntos_trazo' => 5000,
        'max_longitud_texto' => 500,
        'max_bytes_png' => 5 * 1024 * 1024,
    ],

    /*
    |--------------------------------------------------------------------------
    | Grosores ofrecidos por el editor (unidades del viewBox)
    |--------------------------------------------------------------------------
    */
    'grosores' => [1.5, 3, 5, 8],

    /*
    |--------------------------------------------------------------------------
    | Versiones del formato que el backend sabe leer
    |--------------------------------------------------------------------------
    */
    'versiones' => [1],

];
