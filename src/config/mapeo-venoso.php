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
    | Colores: significado clínico del vaso
    |--------------------------------------------------------------------------
    |
    | En la lámina de mapeo el color no es un adorno: es el diagnóstico. Un
    | trazo rojo no es "una vena dibujada en rojo", es una vena incompetente, y
    | esa lectura es la misma para cualquier médico que abra la hoja.
    |
    | Por eso el color se archiva como referencia ('rojo') y no como hexadecimal:
    | el informe necesita imprimir el significado —"reflujo patológico"— y no un
    | color, que en una impresión en blanco y negro no dice nada. El hexadecimal
    | vive aquí para que el editor pinte, y puede afinarse sin reinterpretar los
    | mapeos ya archivados.
    |
    | `label` es el nombre corto del color y `ayuda` su significado: el editor
    | los muestra juntos al pasar el puntero por encima de la muestra.
    |
    */
    'colores' => [
        [
            // No está en la lámina impresa: es el azul de la interfaz, para
            // trazar un recorrido como referencia anatómica sin afirmar todavía
            // en qué estado está la vena. Va primero y es el que trae el editor
            // al abrirse, porque empezar en azul «competente» pondría en el
            // expediente un diagnóstico que el médico no ha hecho.
            'id' => 'auto',
            'label' => 'Auto',
            'hex' => '#243757',
            'ayuda' => 'Referencia anatómica, sin lectura clínica asignada.',
        ],
        [
            'id' => 'azul',
            'label' => 'Azul',
            'hex' => '#1F6FB2',
            'ayuda' => 'Vena competente / flujo fisiológico.',
        ],
        [
            'id' => 'rojo',
            'label' => 'Rojo',
            'hex' => '#C1272D',
            'ayuda' => 'Vena incompetente / reflujo patológico.',
        ],
        [
            'id' => 'negro',
            'label' => 'Negro',
            'hex' => '#1A1A1A',
            'ayuda' => 'Vena trombosada.',
        ],
        [
            'id' => 'gris',
            'label' => 'Gris',
            'hex' => '#8C8C8C',
            'ayuda' => 'Vena ablacionada (quirúrgica o térmica).',
        ],
        [
            'id' => 'verde',
            'label' => 'Verde',
            'hex' => '#1E7A3C',
            'ayuda' => 'Estructuras linfáticas / ganglios.',
        ],
        [
            'id' => 'amarillo',
            'label' => 'Amarillo',
            // Más saturado que el amarillo de la hoja impresa: sobre el blanco
            // de la pantalla el amarillo puro se pierde y el trazo deja de verse.
            'hex' => '#E0A800',
            'ayuda' => 'Estructuras no vasculares (nervios, quiste de Baker).',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Trayectos: cómo se dibuja un recorrido venoso
    |--------------------------------------------------------------------------
    |
    | El segundo eje del mapeo. El color dice en qué estado está la vena; el
    | patrón de línea dice qué clase de trayecto es y en qué plano corre. Son
    | independientes a propósito —una safena hipoplásica puede estar sana o
    | refluyente— y separarlos es lo que evita una lista de veinte "hallazgos"
    | que fueran todas las combinaciones posibles.
    |
    | Los dos troncos safenos y los dos primeros patrones se expresan con un
    | stroke-dasharray o con una línea lisa, que es lo que entiende SVG
    | directamente. Los otros tres no: una onda, una cadena de
    | equis y una línea doble no son un patrón de guiones, así que el editor los
    | dibuja con la geometría que describe `render` + `parametros`. `muestra`
    | es el trocito de línea que se pinta en la barra de herramientas y en la
    | leyenda, para que el médico reconozca el trazo antes de elegirlo.
    |
    | `label` reproduce el nombre tal cual aparece en la lámina impresa —es el
    | que el médico ya reconoce— y `ayuda` lo explica: juntos son lo que el
    | editor muestra al pasar el puntero por encima.
    |
    */
    'trayectos' => [
        // Los dos troncos con nombre propio van primero: son los que el médico
        // nombra en el informe y los que se correlacionan segmento a segmento
        // con el Ecodöppler. Se dibujan como línea continua, que es como corre
        // una safena por su compartimento fascial.
        [
            'id' => 'safena_interna',
            'label' => 'Safena interna (Mayor)',
            'abrev' => 'SI',
            'grosor' => 3,
            'patron' => null,
            'render' => 'linea',
            'parametros' => [],
            'muestra' => '<path d="M2 7 L62 7" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>',
            'ayuda' => 'Tronco safeno magno, de la cara interna del miembro.',
        ],
        [
            'id' => 'safena_externa',
            'label' => 'Safena externa (Menor)',
            'abrev' => 'SE',
            'grosor' => 3,
            'patron' => null,
            'render' => 'linea',
            'parametros' => [],
            'muestra' => '<path d="M2 7 L62 7" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>',
            'ayuda' => 'Tronco safeno parvo, de la cara posterior de la pierna.',
        ],
        [
            'id' => 'subfascial',
            'label' => 'Trayecto subfascial / intrafascial',
            'abrev' => 'SUB',
            'grosor' => 3,
            'patron' => null,
            'render' => 'linea',
            'parametros' => [],
            'muestra' => '<path d="M2 7 L62 7" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>',
            'ayuda' => 'Vena dentro de su compartimento fascial.',
        ],
        [
            'id' => 'epifascial',
            'label' => 'Trayecto epifascial (solo patológico)',
            'abrev' => 'EPI',
            'grosor' => 3,
            'patron' => null,
            'render' => 'ondulado',
            'parametros' => ['amplitud' => 3, 'longitud' => 9],
            'muestra' => '<path d="M2 7 L3.13 9.12 L4.25 10 L5.38 9.12 L6.5 7 L7.63 4.88 L8.75 4 L9.88 4.88 L11 7 L12.13 9.12 L13.25 10 L14.38 9.12 L15.5 7 L16.63 4.88 L17.75 4 L18.88 4.88 L20 7 L21.13 9.12 L22.25 10 L23.38 9.12 L24.5 7 L25.63 4.88 L26.75 4 L27.88 4.88 L29 7 L30.13 9.12 L31.25 10 L32.38 9.12 L33.5 7 L34.63 4.88 L35.75 4 L36.88 4.88 L38 7 L39.13 9.12 L40.25 10 L41.38 9.12 L42.5 7 L43.63 4.88 L44.75 4 L45.88 4.88 L47 7 L48.13 9.12 L49.25 10 L50.38 9.12 L51.5 7 L52.63 4.88 L53.75 4 L54.88 4.88 L56 7 L57.13 9.12 L58.25 10 L59.38 9.12 L60.5 7 L61.63 4.88" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>',
            // Solo patológico según la lámina, pero no se rechaza en otro color:
            // negarle el guardado a un médico que ya trazó el recorrido le haría
            // perder el dibujo, y el informe deja constancia igual.
            'solo_patologico' => true,
            'ayuda' => 'Vena por fuera de la fascia; en la lámina solo se traza cuando es patológica.',
        ],
        [
            'id' => 'hipoplasico',
            'label' => 'Trayecto subfascial hipoplásico',
            'abrev' => 'HIPO',
            'grosor' => 3,
            'patron' => '9 7',
            'render' => 'linea',
            'parametros' => [],
            'muestra' => '<path d="M2 7 L62 7" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="9 7" stroke-linecap="round" stroke-linejoin="round"/>',
            'ayuda' => 'Segmento de calibre reducido respecto al resto del trayecto.',
        ],
        [
            'id' => 'aplasico',
            'label' => 'Trayecto subfascial aplásico o vena ablacionada',
            'abrev' => 'APL',
            'grosor' => 1.5,
            'patron' => '1 3',
            'render' => 'linea',
            'parametros' => [],
            'muestra' => '<path d="M2 7 L62 7" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="1 3" stroke-linecap="round" stroke-linejoin="round"/>',
            'ayuda' => 'Segmento ausente, o abolido por ablación quirúrgica o térmica.',
        ],
        [
            'id' => 'adherencias',
            'label' => 'Trayecto con adherencias',
            'abrev' => 'ADH',
            'grosor' => 1.5,
            'patron' => null,
            'render' => 'cruces',
            'parametros' => ['paso' => 6.5, 'alto' => 8],
            'muestra' => '<path d="M-0.83 4.17L4.83 9.83 M-0.83 9.83L4.83 4.17 M5.67 4.17L11.33 9.83 M5.67 9.83L11.33 4.17 M12.17 4.17L17.83 9.83 M12.17 9.83L17.83 4.17 M18.67 4.17L24.33 9.83 M18.67 9.83L24.33 4.17 M25.17 4.17L30.83 9.83 M25.17 9.83L30.83 4.17 M31.67 4.17L37.33 9.83 M31.67 9.83L37.33 4.17 M38.17 4.17L43.83 9.83 M38.17 9.83L43.83 4.17 M44.67 4.17L50.33 9.83 M44.67 9.83L50.33 4.17 M51.17 4.17L56.83 9.83 M51.17 9.83L56.83 4.17 M57.67 4.17L63.33 9.83 M57.67 9.83L63.33 4.17" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="butt" stroke-linejoin="round"/>',
            'ayuda' => 'Trayecto fijado a los planos vecinos por adherencias.',
        ],
        [
            'id' => 'engrosamiento',
            'label' => 'Engrosamiento de pared',
            'abrev' => 'ENG',
            'grosor' => 1.5,
            'patron' => null,
            'render' => 'doble',
            'parametros' => ['separacion' => 5],
            'muestra' => '<path d="M2 9.5 L62 9.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 4.5 L62 4.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>',
            'ayuda' => 'Pared engrosada respecto al resto del trayecto.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Marcadores: hallazgos puntuales
    |--------------------------------------------------------------------------
    |
    | Tercer eje: lo que se marca en un punto y no a lo largo de un recorrido.
    |
    | `svg` es el símbolo dibujado sobre un viewBox de 24×24 centrado en (12,12),
    | siempre con `currentColor`: el editor lo tiñe con el color elegido y así el
    | mismo símbolo sirve para las seis lecturas clínicas sin duplicar la lista.
    | `color_por_defecto` es el color con el que aparece en la lámina impresa.
    |
    | `tamano` es el diámetro del símbolo en unidades del viewBox de la plantilla
    | y no lo elige el médico: en un mapeo el tamaño de una marca no significa
    | nada —la extensión se dibuja con un trazo, no agrandando un círculo—, y
    | dejarlo variable solo conseguiría que dos perforantes iguales se leyeran
    | como distintas. Se fija aquí para que todas se impriman igual.
    |
    */
    'marcadores' => [
        [
            'id' => 'perforante',
            'label' => 'Vena perforante',
            'abrev' => 'PERF',
            'color_por_defecto' => 'azul',
            'tamano' => 16,
            'svg' => '<circle cx="12" cy="12" r="7" fill="none" stroke="currentColor" stroke-width="2.4"/>',
            'ayuda' => 'Se numera para correlacionarla con el Ecodöppler.',
        ],
        [
            'id' => 'golfo_venoso',
            'label' => 'Golfo venoso',
            'abrev' => 'GV',
            'color_por_defecto' => 'negro',
            'tamano' => 18,
            'svg' => '<line x1="0" y1="12" x2="24" y2="12" stroke="currentColor" stroke-width="2.2"/><circle cx="12" cy="12" r="6.5" fill="currentColor"/>',
            'ayuda' => 'Dilatación sacular sobre el trayecto venoso.',
        ],
        [
            'id' => 'no_venosa',
            'label' => 'Estructura no venosa',
            'abrev' => 'ENV',
            'color_por_defecto' => 'azul',
            'tamano' => 18,
            'svg' => '<ellipse cx="12" cy="12" rx="9" ry="4.5" fill="none" stroke="currentColor" stroke-width="2.4"/>',
            'ayuda' => 'Nervio, quiste de Baker, adenopatía u otra estructura no venosa.',
        ],
        [
            'id' => 'safenectomia',
            'label' => 'Safenectomía / Crosectomía',
            'abrev' => 'SAF',
            'color_por_defecto' => 'negro',
            'tamano' => 22,
            'svg' => '<path d="M1 12 h22 M5 4.5 v15 M10 4.5 v15 M15 4.5 v15 M20 4.5 v15" fill="none" stroke="currentColor" stroke-width="2"/>',
            'ayuda' => 'Segmento resecado o cayado ligado en una cirugía previa.',
        ],
        [
            'id' => 'ulcera',
            'label' => 'Úlcera',
            'abrev' => 'ULC',
            'color_por_defecto' => 'negro',
            'tamano' => 24,
            'svg' => '<path d="M8 3.5 Q3.5 5 4.5 9 Q1.5 12 4 14.5 Q3 18.5 7 19.5 Q7.5 22.5 11 21 Q14.5 23 16.5 19.5 Q20.5 19 20 15.5 Q23 12.5 20 9.5 Q20.5 5 16.5 4.5 Q13.5 1.5 10.5 3 Z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 3 v18.5 M12.5 2 v20.5 M17 4 v16 M3 8.5 h18 M2 13 h20.5 M3.5 17.5 h17" fill="none" stroke="currentColor" stroke-width="1.4"/>',
            'ayuda' => 'Úlcera venosa activa o cicatrizada.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Hallazgos heredados
    |--------------------------------------------------------------------------
    |
    | Vocabulario anterior, cuando un solo `hallazgo` mezclaba el color, el
    | patrón y el símbolo. El editor ya no lo ofrece: se dibuja con
    | color + trayecto y color + marcador.
    |
    | Se conserva porque los mapeos archivados con él siguen archivados con él, y
    | un informe clínico tiene que poder reimprimirse años después tal y como se
    | firmó. Borrar esta lista no borraría esos documentos: los dejaría
    | imprimiendo códigos crudos.
    |
    | No añadir entradas aquí.
    |
    */
    'hallazgos_legacy' => [
        ['id' => 'safena_interna', 'tipo' => 'trazo',     'label' => 'Safena interna (Mayor)',            'abrev' => 'SI',  'color' => '#0C7D8C', 'grosor' => 3,    'patron' => null,  'simbolo' => null],
        ['id' => 'safena_externa', 'tipo' => 'trazo',     'label' => 'Safena externa (Menor)',            'abrev' => 'SE',  'color' => '#243757', 'grosor' => 3,    'patron' => null,  'simbolo' => null],
        ['id' => 'colateral',      'tipo' => 'trazo',     'label' => 'Colateral / tributaria',            'abrev' => 'COL', 'color' => '#3A5F6F', 'grosor' => 2,    'patron' => null,  'simbolo' => null],
        ['id' => 'varice',         'tipo' => 'trazo',     'label' => 'Vena varicosa',                     'abrev' => 'VAR', 'color' => '#B43C32', 'grosor' => 5,    'patron' => null,  'simbolo' => null],
        ['id' => 'reticular',      'tipo' => 'trazo',     'label' => 'Reticulares / telangiectasias',     'abrev' => 'RET', 'color' => '#8E5AA8', 'grosor' => 2,    'patron' => '5 4', 'simbolo' => null],
        ['id' => 'perforante',     'tipo' => 'marcador',  'label' => 'Perforante insuficiente',           'abrev' => 'P',   'color' => '#B43C32', 'grosor' => null, 'patron' => null,  'simbolo' => 'aspa'],
        ['id' => 'cayado',         'tipo' => 'marcador',  'label' => 'Cayado (unión safeno-femoral / poplítea)', 'abrev' => 'CAY', 'color' => '#243757', 'grosor' => null, 'patron' => null, 'simbolo' => 'doble-circulo'],
        ['id' => 'trombo',         'tipo' => 'marcador',  'label' => 'Trombo / trombosis',                'abrev' => 'TR',  'color' => '#1B2A42', 'grosor' => null, 'patron' => null,  'simbolo' => 'rombo'],
        ['id' => 'ulcera',         'tipo' => 'marcador',  'label' => 'Úlcera',                            'abrev' => 'ULC', 'color' => '#B43C32', 'grosor' => null, 'patron' => null,  'simbolo' => 'circulo-relleno'],
        ['id' => 'puncion',        'tipo' => 'marcador',  'label' => 'Punto de punción / escleroterapia', 'abrev' => 'PN',  'color' => '#6B8F71', 'grosor' => null, 'patron' => null,  'simbolo' => 'punto'],
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
    |
    | El `grosor` por defecto de cada trayecto sale de esta lista: es la única
    | que valida el backend, y un valor fuera de ella haría que el editor no
    | pudiera guardar su propio ajuste inicial.
    |
    */
    'grosores' => [1.5, 3, 5, 8],

    /*
    |--------------------------------------------------------------------------
    | Versiones del formato que el backend sabe leer
    |--------------------------------------------------------------------------
    */
    'versiones' => [1],

];
