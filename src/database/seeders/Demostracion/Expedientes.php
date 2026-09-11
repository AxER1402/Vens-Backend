<?php

namespace Database\Seeders\Demostracion;

use Illuminate\Support\Carbon;

/**
 * Los expedientes de demostración, tal como se verían impresos.
 *
 * Esto no es un generador aleatorio: cada paciente es un caso clínico completo
 * y coherente consigo mismo, porque el objetivo es enseñar cómo se leen los
 * informes que emite el sistema. Un peso de 300 kg o un reflujo de 40 segundos
 * no se notan en una pantalla de pruebas, pero en una hoja impresa delante de
 * la institución lo primero que se ve es que los datos no significan nada.
 *
 * Por eso cada historia encaja con su Ecodöppler, cada control repite las
 * mismas medidas mejorando, y los precios, los teléfonos y las direcciones son
 * los de Guatemala, que es donde está la clínica.
 *
 * Las fechas se expresan en días hacia atrás desde hoy para que los reportes de
 * período tengan siempre algo que resumir, sin importar cuándo se siembre.
 *
 * Los nombres son inventados. Cualquier parecido con un paciente real es
 * casualidad: esto es material de demostración, no un expediente.
 */
class Expedientes
{
    /*
    |--------------------------------------------------------------------------
    | Recorridos sobre la plantilla del mapeo venoso
    |--------------------------------------------------------------------------
    |
    | Coordenadas normalizadas 0-1 sobre la lámina de seis vistas, calibradas
    | sobre el dibujo: la safena magna baja por la cara antero-interna y la
    | safena menor por la cara posterior, que es por donde corren de verdad.
    |
    | El panel derecho de la lámina es el izquierdo reflejado, así que un
    | recorrido del MID es el del MII espejado sobre x = 1.010.
    |
    */

    /** Safena magna del MII, de la ingle al tobillo por la cara antero-interna. */
    private const SAFENA_MII = [
        [0.274, 0.245], [0.268, 0.310], [0.264, 0.380], [0.262, 0.450],
        [0.261, 0.520], [0.262, 0.565], [0.259, 0.620], [0.257, 0.680],
        [0.260, 0.730], [0.265, 0.780], [0.270, 0.815],
    ];

    /** Safena magna del MID. */
    private const SAFENA_MID = [
        [0.736, 0.245], [0.742, 0.310], [0.746, 0.380], [0.748, 0.450],
        [0.749, 0.520], [0.748, 0.565], [0.751, 0.620], [0.753, 0.680],
        [0.750, 0.730], [0.745, 0.780], [0.740, 0.815],
    ];

    /** Safena magna del MII solo hasta la rodilla: el segmento de muslo. */
    private const SAFENA_MII_MUSLO = [
        [0.274, 0.245], [0.268, 0.310], [0.264, 0.380], [0.262, 0.450], [0.261, 0.520],
    ];

    /** Safena magna del MII de la rodilla abajo. */
    private const SAFENA_MII_PIERNA = [
        [0.262, 0.565], [0.259, 0.620], [0.257, 0.680], [0.260, 0.730], [0.265, 0.780], [0.270, 0.815],
    ];

    /** Safena menor del MII, del hueco poplíteo al maléolo, por la cara posterior. */
    private const SAFENA_MENOR_MII = [
        [0.139, 0.575], [0.137, 0.630], [0.136, 0.690], [0.138, 0.745], [0.141, 0.790],
    ];

    /** Safena menor del MID. */
    private const SAFENA_MENOR_MID = [
        [0.871, 0.575], [0.873, 0.630], [0.874, 0.690], [0.872, 0.745], [0.869, 0.790],
    ];

    /** Colateral varicosa en la cara antero-externa del MII: el zigzag de una várice. */
    private const COLATERAL_MII_EXT = [
        [0.395, 0.330], [0.404, 0.390], [0.397, 0.445], [0.408, 0.500],
        [0.399, 0.555], [0.409, 0.610], [0.400, 0.660],
    ];

    /** Colateral varicosa en la cara antero-externa del MID. */
    private const COLATERAL_MID_EXT = [
        [0.615, 0.330], [0.606, 0.390], [0.613, 0.445], [0.602, 0.500],
        [0.611, 0.555], [0.601, 0.610], [0.610, 0.660],
    ];

    /** Tributaria de muslo del MII, sobre la propia cara antero-interna. */
    private const TRIBUTARIA_MII_MUSLO = [
        [0.294, 0.300], [0.305, 0.350], [0.298, 0.400], [0.308, 0.450], [0.300, 0.495],
    ];

    /**
     * Todos los expedientes de demostración.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function todos(Carbon $hoy): array
    {
        return [
            self::giron($hoy),
            self::archila($hoy),
            self::tobar($hoy),
            self::tzoc($hoy),
            self::maldonado($hoy),
            self::recinos($hoy),
        ];
    }

    /**
     * Fecha a tantos días de hoy, como cadena.
     */
    private static function dia(Carbon $hoy, int $atras): string
    {
        return $hoy->copy()->subDays($atras)->toDateString();
    }

    /*
    |--------------------------------------------------------------------------
    | 1 · Insuficiencia de safena magna izquierda (CEAP C3)
    |--------------------------------------------------------------------------
    |
    | El caso más corriente de la consulta: mujer de mediana edad, multípara,
    | con pesadez y edema vespertino. Tres consultas —valoración, primera y
    | segunda sesión de escleroterapia— con las medidas del examen físico
    | mejorando de una a otra, que es lo que se quiere poder enseñar impreso.
    |
    */

    /**
     * @return array<string, mixed>
     */
    private static function giron(Carbon $hoy): array
    {
        return [
            'paciente' => [
                'nombre' => 'Marta Elena Girón de Ramírez',
                'edad' => 47,
                'telefono' => '54126738',
                'lugar_residencia' => '3a avenida 5-22, zona 3, Santa Cruz del Quiché',
                'estado_civil' => 'Casado/a',
                'religion' => 'Católica',
                'estado' => 'Seguimiento',
                'activo' => true,
            ],

            'consultas' => [
                [
                    'fecha' => self::dia($hoy, 118),
                    'cita' => ['hora' => '08:30', 'motivo' => 'Primera consulta por várices en miembro inferior izquierdo'],
                    'campos' => [
                        'consulta_por' => 'Enfermedad',
                        'disminuyen_otros' => 'Baños de agua fría al terminar la jornada',
                        'familiar_varices' => true,
                        'alergias' => 'Ninguna conocida',
                        'cirugias' => 'Cesárea segmentaria (2011). Colecistectomía laparoscópica (2019).',
                        'gestas' => 3,
                        'abortos' => 0,
                        'partos' => 2,
                        'cesareas' => 1,
                        'hijos_vivos' => 3,
                        'hijos_muertos' => 0,
                        'ultima_menstruacion' => self::dia($hoy, 132),
                        'hormonas' => 'Anticonceptivo oral combinado durante seis años, suspendido en 2018',
                        'presion_arterial' => '118/76',
                        'frecuencia_cardiaca' => 74,
                        'frecuencia_respiratoria' => 16,
                        'temperatura' => 36.4,
                        'peso' => 72.40,
                        'perimetro_tobillo' => 24.5,
                        'perimetro_pantorrilla' => 36.8,
                        'ubicacion_patologia' => 'MII',
                        'ceap_c' => 'C3',
                        'indicaciones_detalle' => [
                            'Venotónico' => 'Diosmina/Hesperidina 450/50 mg, una tableta cada 12 horas por tres meses',
                            'Medias Compresivas' => 'Media hasta la rodilla, compresión 20-30 mmHg, colocada antes de levantarse',
                        ],
                        'estado_general' => 'Requiere nuevas sesiones',
                        'notas' => 'Primera consulta. Refiere pesadez y edema vespertino del miembro inferior izquierdo de tres años de evolución, que empeoró después del último embarazo y que cede al elevar las piernas. A la exploración, várices trunculares en cara antero-interna de muslo y pierna izquierdos, sin cambios tróficos de la piel. Maniobra de Trendelenburg positiva a la izquierda. Se solicita Ecodöppler venoso de miembros inferiores antes de programar la escleroterapia.',
                    ],
                    'selecciones' => [
                        'zonas_pierna' => ['Muslo', 'Pantorrilla', 'Tobillo'],
                        'sintomas' => ['Pesadez', 'Cansancio', 'Calambres', 'Hinchazón'],
                        'sintomas_aumentan' => ['Estar de pie', 'Calor'],
                        'sintomas_disminuyen' => ['Elevación de las piernas', 'Medias compresivas'],
                        'ceap_diagnostico' => ['Primaria', 'Superficial', 'Reflujo'],
                        'indicaciones' => ['Venotónico', 'Medias Compresivas'],
                    ],
                    'mapeo' => [
                        ['tipo' => 'trazo', 'trayecto' => 'safena_interna', 'color' => 'rojo', 'grosor' => 3,
                            'zona' => 'izq_antero_interna', 'puntos' => self::SAFENA_MII],
                        ['tipo' => 'trazo', 'trayecto' => 'epifascial', 'color' => 'rojo', 'grosor' => 3,
                            'zona' => 'izq_antero_interna', 'puntos' => self::TRIBUTARIA_MII_MUSLO],
                        ['tipo' => 'trazo', 'trayecto' => 'epifascial', 'color' => 'rojo', 'grosor' => 3,
                            'zona' => 'izq_antero_externa', 'puntos' => self::COLATERAL_MII_EXT],
                        ['tipo' => 'trazo', 'trayecto' => 'safena_interna', 'color' => 'azul', 'grosor' => 3,
                            'zona' => 'der_antero_interna', 'puntos' => self::SAFENA_MID],
                        ['tipo' => 'marcador', 'marcador' => 'perforante', 'color' => 'rojo', 'numero' => 1,
                            'zona' => 'izq_antero_interna', 'x' => 0.259, 'y' => 0.610],
                        ['tipo' => 'marcador', 'marcador' => 'perforante', 'color' => 'rojo', 'numero' => 2,
                            'zona' => 'izq_antero_interna', 'x' => 0.257, 'y' => 0.700],
                        ['tipo' => 'marcador', 'marcador' => 'golfo_venoso', 'color' => 'rojo', 'numero' => 3,
                            'zona' => 'izq_antero_externa', 'x' => 0.404, 'y' => 0.500],
                        ['tipo' => 'anotacion', 'color' => 'negro', 'numero' => 4,
                            'zona' => 'izq_antero_interna', 'x' => 0.276, 'y' => 0.255,
                            'texto' => 'Cayado safeno-femoral incompetente, 9.1 mm'],
                        ['tipo' => 'texto', 'color' => 'negro', 'tamano' => 17,
                            'zona' => 'izq_antero_externa', 'x' => 0.352, 'y' => 0.700,
                            'texto' => 'Colateral varicosa'],
                    ],
                    'cobro' => [
                        'metodo_pago' => 'Efectivo',
                        'renglones' => [
                            ['descripcion' => 'Consulta de primera vez — Flebología', 'cantidad' => 1, 'precio_unitario' => 400.00],
                        ],
                    ],
                ],

                [
                    'fecha' => self::dia($hoy, 76),
                    'cita' => ['hora' => '09:00', 'motivo' => 'Primera sesión de escleroterapia — MII'],
                    'campos' => [
                        'consulta_por' => 'Enfermedad',
                        'familiar_varices' => true,
                        'alergias' => 'Ninguna conocida',
                        'presion_arterial' => '116/74',
                        'frecuencia_cardiaca' => 72,
                        'frecuencia_respiratoria' => 16,
                        'temperatura' => 36.5,
                        'peso' => 71.80,
                        'perimetro_tobillo' => 23.8,
                        'perimetro_pantorrilla' => 36.2,
                        'ubicacion_patologia' => 'MII',
                        'ceap_c' => 'C3',
                        'esclero_concentracion' => 1.00,
                        'esclero_forma' => 'Espuma',
                        'esclero_volumen' => 6.00,
                        'indicaciones_detalle' => [
                            'Venotónico' => 'Diosmina/Hesperidina 450/50 mg cada 12 horas, continúa',
                            'AINEs' => 'Ibuprofeno 400 mg cada 8 horas por tres días, solo si hay dolor',
                            'Medias Compresivas' => 'Media 20-30 mmHg de uso continuo durante 15 días, incluso para dormir las primeras 48 horas',
                        ],
                        'evolucion' => 'Mejoría',
                        'estado_general' => 'Requiere nuevas sesiones',
                        'notas' => 'Primera sesión de escleroterapia con espuma de polidocanol al 1 %, 6 ml, sobre tronco de safena magna y colateral de muslo izquierdo, con guía ecográfica. Se coloca vendaje elástico y se indica deambulación inmediata durante 30 minutos. Tolerancia adecuada, sin dolor ni reacción vagal durante el procedimiento. El perímetro de tobillo bajó 0.7 cm respecto a la primera consulta.',
                    ],
                    'selecciones' => [
                        'zonas_pierna' => ['Muslo', 'Pantorrilla'],
                        'sintomas' => ['Pesadez', 'Hinchazón'],
                        'sintomas_aumentan' => ['Estar de pie', 'Calor'],
                        'sintomas_disminuyen' => ['Elevación de las piernas', 'Medias compresivas'],
                        'ceap_diagnostico' => ['Primaria', 'Superficial', 'Reflujo'],
                        'tx_zonas' => ['Varicosas trunculares', 'Reticulares'],
                        'indicaciones' => ['Venotónico', 'AINEs', 'Medias Compresivas'],
                        'observaciones' => ['Buena respuesta', 'Sin complicaciones'],
                    ],
                    'cobro' => [
                        'metodo_pago' => 'Efectivo',
                        'renglones' => [
                            ['descripcion' => 'Sesión de escleroterapia con espuma — MII', 'cantidad' => 1, 'precio_unitario' => 750.00],
                            ['tipo' => 'B', 'descripcion' => 'Media compresiva hasta la rodilla 20-30 mmHg', 'cantidad' => 1, 'precio_unitario' => 385.00],
                        ],
                    ],
                ],

                [
                    'fecha' => self::dia($hoy, 34),
                    'cita' => ['hora' => '09:00', 'motivo' => 'Segunda sesión de escleroterapia — MII'],
                    'campos' => [
                        'consulta_por' => 'Enfermedad',
                        'familiar_varices' => true,
                        'alergias' => 'Ninguna conocida',
                        'presion_arterial' => '114/72',
                        'frecuencia_cardiaca' => 70,
                        'frecuencia_respiratoria' => 16,
                        'temperatura' => 36.5,
                        'peso' => 71.20,
                        'perimetro_tobillo' => 23.2,
                        'perimetro_pantorrilla' => 35.6,
                        'ubicacion_patologia' => 'MII',
                        'ceap_c' => 'C2',
                        'esclero_concentracion' => 0.50,
                        'esclero_forma' => 'Espuma',
                        'esclero_volumen' => 4.50,
                        'indicaciones_detalle' => [
                            'Venotónico' => 'Diosmina/Hesperidina 450/50 mg cada 12 horas, continúa hasta completar tres meses',
                            'Medias Compresivas' => 'Media 20-30 mmHg durante el día por 15 días más',
                            'Crema' => 'Protector solar en la pierna tratada mientras persista la pigmentación',
                        ],
                        'evolucion' => 'Mejoría',
                        'estado_general' => 'Requiere nuevas sesiones',
                        'notas' => 'Segunda sesión de escleroterapia, 4.5 ml de espuma de polidocanol al 0.5 % sobre reticulares y telangiectasias residuales de pierna izquierda. El tronco safeno tratado en la sesión anterior se encuentra ocluido en el control ecográfico. Persiste pigmentación lineal en cara interna de pierna, en regresión, que se explica a la paciente como esperable. Se programa tercera sesión en seis semanas.',
                    ],
                    'selecciones' => [
                        'zonas_pierna' => ['Pantorrilla'],
                        'sintomas' => ['Pesadez'],
                        'sintomas_aumentan' => ['Estar de pie'],
                        'sintomas_disminuyen' => ['Elevación de las piernas', 'Medias compresivas'],
                        'ceap_diagnostico' => ['Primaria', 'Superficial'],
                        'tx_zonas' => ['Reticulares', 'Telangiectasias'],
                        'indicaciones' => ['Venotónico', 'Crema', 'Medias Compresivas'],
                        'observaciones' => ['Buena respuesta', 'Pigmentación'],
                    ],
                    'cobro' => [
                        'tipo' => 'factura',
                        'nit' => '7108452-3',
                        'metodo_pago' => 'Transferencia',
                        'renglones' => [
                            ['descripcion' => 'Sesión de escleroterapia con espuma — MII', 'cantidad' => 1, 'precio_unitario' => 750.00],
                        ],
                    ],
                ],
            ],

            'doppler' => [
                [
                    'fecha' => self::dia($hoy, 111),
                    'consulta' => 0,
                    'cita' => ['hora' => '10:00', 'motivo' => 'Ecodöppler venoso de miembros inferiores'],
                    'campos' => [
                        'der_profundo' => 'Eje femoro-poplíteo permeable y compresible, con flujo espontáneo, fásico con la respiración y sin reflujo a la maniobra de Valsalva.',
                        'der_segmentos' => [
                            ['nombre' => 'SFJ', 'diametro_max' => 5.8, 'velocidad' => 18, 'duracion' => 0.3, 'diametro' => 5.2, 'observaciones' => 'Cayado competente'],
                            ['nombre' => 'GSV Muslo', 'diametro_max' => 4.6, 'velocidad' => 14, 'duracion' => 0.2, 'diametro' => 4.3, 'observaciones' => 'Sin reflujo'],
                            ['nombre' => 'GSV Pierna', 'diametro_max' => 3.4, 'velocidad' => 11, 'duracion' => 0.2, 'diametro' => 3.2, 'observaciones' => 'Sin reflujo'],
                            ['nombre' => 'Safena menor', 'diametro_max' => 2.9, 'velocidad' => 10, 'duracion' => 0.2, 'diametro' => 2.8, 'observaciones' => 'Competente'],
                            ['nombre' => null],
                        ],
                        'der_perforantes' => 'Sin perforantes incompetentes identificadas.',
                        'der_trombosis' => 'Sin signos de trombosis aguda ni de secuela post-trombótica.',
                        'izq_profundo' => 'Eje femoro-poplíteo permeable y compresible, sin reflujo ni material endoluminal.',
                        'izq_segmentos' => [
                            ['nombre' => 'SFJ', 'diametro_max' => 9.1, 'velocidad' => 26, 'duracion' => 2.4, 'diametro' => 8.4, 'observaciones' => 'Reflujo patológico sostenido'],
                            ['nombre' => 'GSV Muslo', 'diametro_max' => 7.6, 'velocidad' => 22, 'duracion' => 2.1, 'diametro' => 7.0, 'observaciones' => 'Reflujo en todo el trayecto'],
                            ['nombre' => 'GSV Pierna', 'diametro_max' => 5.9, 'velocidad' => 17, 'duracion' => 1.6, 'diametro' => 5.4, 'observaciones' => 'Reflujo hasta tercio medio'],
                            ['nombre' => 'Tributaria antero-lateral de muslo', 'diametro_max' => 4.8, 'velocidad' => 13, 'duracion' => 1.4, 'diametro' => 4.4, 'observaciones' => 'Varicosa, tributaria de la safena magna'],
                            ['nombre' => 'Safena menor', 'diametro_max' => 3.1, 'velocidad' => 12, 'duracion' => 0.3, 'diametro' => 3.0, 'observaciones' => 'Competente'],
                        ],
                        'izq_perforantes' => 'Perforantes de Boyd y de Cockett II incompetentes, de 3.6 mm y 3.9 mm, con flujo de dentro hacia afuera.',
                        'izq_trombosis' => 'Sin signos de trombosis venosa profunda ni superficial.',
                        'conclusion' => 'Insuficiencia venosa superficial primaria del miembro inferior izquierdo, por incompetencia de la unión safeno-femoral y del tronco de la safena magna en todo su trayecto, con dos perforantes incompetentes en pierna. Sistema venoso profundo permeable y competente en ambos miembros. Miembro inferior derecho sin hallazgos patológicos. Se sugiere tratamiento de la safena magna izquierda y control clínico a los tres meses.',
                    ],
                    'cobro' => [
                        'metodo_pago' => 'Tarjeta',
                        'renglones' => [
                            ['descripcion' => 'Ecodöppler venoso de miembros inferiores (bilateral)', 'cantidad' => 1, 'precio_unitario' => 650.00],
                        ],
                    ],
                ],
            ],

            'citas' => [
                ['dias' => -12, 'hora' => '09:00', 'duracion' => 45, 'estado' => 'Programada',
                    'motivo' => 'Tercera sesión de escleroterapia — MII'],
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 2 · Síndrome post-trombótico con úlcera activa (CEAP C6)
    |--------------------------------------------------------------------------
    |
    | El extremo opuesto del anterior: enfermedad secundaria, comorbilidades y
    | una úlcera que se sigue consulta a consulta. Es el expediente que enseña
    | que el informe sirve para seguir a un paciente y no solo para archivarlo,
    | porque las tres consultas cuentan el cierre de la misma lesión.
    |
    */

    /**
     * @return array<string, mixed>
     */
    private static function archila(Carbon $hoy): array
    {
        return [
            'paciente' => [
                'nombre' => 'Julio César Archila Morales',
                'edad' => 63,
                'telefono' => '42196075',
                'lugar_residencia' => 'Cantón Chugüexá II, Chichicastenango, Quiché',
                'estado_civil' => 'Casado/a',
                'religion' => 'Evangélica',
                'estado' => 'Activo',
                'activo' => true,
            ],

            'consultas' => [
                [
                    'fecha' => self::dia($hoy, 96),
                    'cita' => ['hora' => '11:00', 'duracion' => 45, 'motivo' => 'Primera consulta por úlcera en pierna derecha'],
                    'campos' => [
                        'consulta_por' => 'Enfermedad',
                        'familiar_varices' => false,
                        'alergias' => 'Penicilina (exantema generalizado en 1998)',
                        'cirugias' => 'Herniorrafia inguinal derecha (2004).',
                        'enfermedades_otros' => 'Trombosis venosa profunda del miembro inferior derecho en 2009, tratada con anticoagulación oral durante seis meses.',
                        'presion_arterial' => '138/86',
                        'frecuencia_cardiaca' => 78,
                        'frecuencia_respiratoria' => 18,
                        'temperatura' => 36.6,
                        'peso' => 88.50,
                        'perimetro_tobillo' => 27.4,
                        'perimetro_pantorrilla' => 41.2,
                        'ubicacion_patologia' => 'MID',
                        'ceap_c' => 'C6',
                        'indicaciones_detalle' => [
                            'Venotónico' => 'Diosmina/Hesperidina 450/50 mg cada 12 horas',
                            'Crema' => 'Sulfadiazina de plata sobre el lecho de la úlcera, cura cada 72 horas',
                            'Medias Compresivas' => 'Vendaje multicapa de compresión inelástica, recambio cada 72 horas',
                        ],
                        'indicaciones_otros' => 'Se refiere a medicina interna para ajuste de hipoglucemiantes y a la clínica de heridas para curación cada 72 horas.',
                        'estado_general' => 'Requiere nuevas sesiones',
                        'notas' => 'Paciente con antecedente de trombosis venosa profunda del miembro inferior derecho en 2009. Presenta úlcera de 3.2 x 2.6 cm en región supramaleolar interna derecha, de cuatro meses de evolución, con fondo granulante, bordes bien delimitados y exudado seroso escaso, sin signos de infección ni celulitis perilesional. Hiperpigmentación ocre y lipodermatoesclerosis en el tercio distal de la pierna. Pulsos pedio y tibial posterior presentes y simétricos; índice tobillo-brazo de 1.02, que permite compresión. Se inicia terapia compresiva y curación, y se solicita Ecodöppler venoso.',
                    ],
                    'selecciones' => [
                        'zonas_pierna' => ['Pantorrilla', 'Tobillo'],
                        'sintomas' => ['Úlceras', 'Manchas en la piel', 'Hinchazón', 'Picazón', 'Calambres'],
                        'sintomas_aumentan' => ['Estar de pie', 'Calor'],
                        'sintomas_disminuyen' => ['Elevación de las piernas', 'Reposo'],
                        'enfermedades' => ['Diabetes', 'Alta o baja presión', 'Otros'],
                        'ceap_diagnostico' => ['Secundaria', 'Profunda', 'Perforantes', 'Obstrucción'],
                        'indicaciones' => ['Venotónico', 'Crema', 'Medias Compresivas'],
                        'observaciones' => ['Pigmentación', 'Inflamación'],
                    ],
                    'mapeo' => [
                        ['tipo' => 'trazo', 'trayecto' => 'safena_interna', 'color' => 'rojo', 'grosor' => 3,
                            'zona' => 'der_antero_interna', 'puntos' => self::SAFENA_MID],
                        ['tipo' => 'trazo', 'trayecto' => 'epifascial', 'color' => 'rojo', 'grosor' => 3,
                            'zona' => 'der_antero_externa', 'puntos' => self::COLATERAL_MID_EXT],
                        ['tipo' => 'trazo', 'trayecto' => 'safena_externa', 'color' => 'azul', 'grosor' => 3,
                            'zona' => 'der_posterior', 'puntos' => self::SAFENA_MENOR_MID],
                        ['tipo' => 'trazo', 'trayecto' => 'safena_interna', 'color' => 'azul', 'grosor' => 3,
                            'zona' => 'izq_antero_interna', 'puntos' => self::SAFENA_MII],
                        ['tipo' => 'marcador', 'marcador' => 'ulcera', 'color' => 'rojo', 'numero' => 1,
                            'zona' => 'der_antero_interna', 'x' => 0.744, 'y' => 0.800],
                        ['tipo' => 'marcador', 'marcador' => 'perforante', 'color' => 'rojo', 'numero' => 2,
                            'zona' => 'der_antero_interna', 'x' => 0.752, 'y' => 0.730],
                        ['tipo' => 'marcador', 'marcador' => 'perforante', 'color' => 'rojo', 'numero' => 3,
                            'zona' => 'der_antero_interna', 'x' => 0.754, 'y' => 0.655],
                        ['tipo' => 'anotacion', 'color' => 'negro', 'numero' => 4,
                            'zona' => 'der_antero_interna', 'x' => 0.700, 'y' => 0.800,
                            'texto' => 'Úlcera supramaleolar interna, 3.2 x 2.6 cm, fondo granulante'],
                        ['tipo' => 'anotacion', 'color' => 'negro', 'numero' => 5,
                            'zona' => 'der_antero_externa', 'x' => 0.606, 'y' => 0.520,
                            'texto' => 'Lipodermatoesclerosis del tercio distal'],
                    ],
                    'cobro' => [
                        'metodo_pago' => 'Efectivo',
                        'renglones' => [
                            ['descripcion' => 'Consulta de primera vez — Flebología', 'cantidad' => 1, 'precio_unitario' => 400.00],
                            ['tipo' => 'B', 'descripcion' => 'Vendaje multicapa de compresión', 'cantidad' => 1, 'precio_unitario' => 420.00],
                        ],
                    ],
                ],

                [
                    'fecha' => self::dia($hoy, 54),
                    'cita' => ['hora' => '11:00', 'motivo' => 'Control de úlcera venosa y cambio de vendaje'],
                    'campos' => [
                        'consulta_por' => 'Enfermedad',
                        'familiar_varices' => false,
                        'alergias' => 'Penicilina (exantema generalizado en 1998)',
                        'presion_arterial' => '132/84',
                        'frecuencia_cardiaca' => 76,
                        'frecuencia_respiratoria' => 17,
                        'temperatura' => 36.5,
                        'peso' => 87.90,
                        'perimetro_tobillo' => 26.2,
                        'perimetro_pantorrilla' => 40.1,
                        'ubicacion_patologia' => 'MID',
                        'ceap_c' => 'C6',
                        'indicaciones_detalle' => [
                            'Venotónico' => 'Diosmina/Hesperidina 450/50 mg cada 12 horas, continúa',
                            'Crema' => 'Apósito de hidrofibra sobre el lecho y emoliente en la piel perilesional',
                            'Medias Compresivas' => 'Vendaje multicapa, recambio cada 72 horas',
                        ],
                        'evolucion' => 'Mejoría',
                        'estado_general' => 'Requiere nuevas sesiones',
                        'notas' => 'La úlcera mide hoy 2.1 x 1.4 cm, con reducción del 65 % del área respecto a la primera consulta y tejido de granulación que cubre todo el lecho. Sin exudado ni signos de infección. El perímetro de tobillo bajó 1.2 cm con la compresión. El paciente refiere tolerar bien el vendaje y haber reducido el tiempo de bipedestación en su trabajo. Se mantiene el mismo esquema.',
                    ],
                    'selecciones' => [
                        'zonas_pierna' => ['Tobillo'],
                        'sintomas' => ['Úlceras', 'Manchas en la piel', 'Hinchazón'],
                        'sintomas_aumentan' => ['Estar de pie'],
                        'sintomas_disminuyen' => ['Elevación de las piernas', 'Medias compresivas'],
                        'enfermedades' => ['Diabetes', 'Alta o baja presión'],
                        'ceap_diagnostico' => ['Secundaria', 'Profunda', 'Perforantes'],
                        'indicaciones' => ['Venotónico', 'Crema', 'Medias Compresivas'],
                        'observaciones' => ['Buena respuesta', 'Pigmentación'],
                    ],
                    'cobro' => [
                        'metodo_pago' => 'Efectivo',
                        'renglones' => [
                            ['descripcion' => 'Consulta de control y curación de úlcera venosa', 'cantidad' => 1, 'precio_unitario' => 275.00],
                            ['tipo' => 'B', 'descripcion' => 'Vendaje multicapa de compresión', 'cantidad' => 1, 'precio_unitario' => 420.00],
                        ],
                    ],
                ],

                [
                    'fecha' => self::dia($hoy, 18),
                    'cita' => ['hora' => '11:30', 'motivo' => 'Control de úlcera venosa'],
                    'campos' => [
                        'consulta_por' => 'Enfermedad',
                        'familiar_varices' => false,
                        'alergias' => 'Penicilina (exantema generalizado en 1998)',
                        'presion_arterial' => '128/80',
                        'frecuencia_cardiaca' => 74,
                        'frecuencia_respiratoria' => 16,
                        'temperatura' => 36.4,
                        'peso' => 87.20,
                        'perimetro_tobillo' => 25.4,
                        'perimetro_pantorrilla' => 39.4,
                        'ubicacion_patologia' => 'MID',
                        'ceap_c' => 'C6',
                        'indicaciones_detalle' => [
                            'Venotónico' => 'Diosmina/Hesperidina 450/50 mg cada 12 horas',
                            'Medias Compresivas' => 'Al cerrar la úlcera, media de compresión 30-40 mmHg de uso diario permanente',
                        ],
                        'indicaciones_otros' => 'Una vez epitelizada la úlcera se valorará la ablación de las perforantes de Cockett incompetentes.',
                        'evolucion' => 'Mejoría',
                        'estado_general' => 'Requiere nuevas sesiones',
                        'notas' => 'Úlcera de 0.9 x 0.6 cm, en fase de epitelización, con bordes que avanzan desde la periferia. Hemoglobina glucosilada de control en 7.1 %. Se prevé cierre completo en las próximas tres a cuatro semanas y se explica al paciente que la compresión debe continuar después del cierre para evitar la recidiva, que es lo que ocurre en la mayoría de casos que la abandonan.',
                    ],
                    'selecciones' => [
                        'zonas_pierna' => ['Tobillo'],
                        'sintomas' => ['Úlceras', 'Manchas en la piel'],
                        'sintomas_aumentan' => ['Estar de pie'],
                        'sintomas_disminuyen' => ['Medias compresivas', 'Elevación de las piernas'],
                        'enfermedades' => ['Diabetes', 'Alta o baja presión'],
                        'ceap_diagnostico' => ['Secundaria', 'Profunda', 'Perforantes'],
                        'indicaciones' => ['Venotónico', 'Medias Compresivas'],
                        'observaciones' => ['Buena respuesta', 'Sin complicaciones'],
                    ],
                    'cobro' => [
                        'metodo_pago' => 'Efectivo',
                        'renglones' => [
                            ['descripcion' => 'Consulta de control y curación de úlcera venosa', 'cantidad' => 1, 'precio_unitario' => 275.00],
                        ],
                    ],
                ],
            ],

            'doppler' => [
                [
                    'fecha' => self::dia($hoy, 89),
                    'consulta' => 0,
                    'cita' => ['hora' => '15:00', 'motivo' => 'Ecodöppler venoso de miembros inferiores'],
                    'campos' => [
                        'der_profundo' => 'Vena femoral común permeable. Vena femoral y poplítea con paredes engrosadas, compresibilidad parcial y material ecogénico organizado adherido a la pared, compatible con secuela post-trombótica. Reflujo poplíteo de 2.8 segundos.',
                        'der_segmentos' => [
                            ['nombre' => 'SFJ', 'diametro_max' => 6.2, 'velocidad' => 16, 'duracion' => 1.2, 'diametro' => 5.8, 'observaciones' => 'Reflujo leve'],
                            ['nombre' => 'GSV Muslo', 'diametro_max' => 5.4, 'velocidad' => 15, 'duracion' => 1.4, 'diametro' => 5.1, 'observaciones' => 'Reflujo segmentario'],
                            ['nombre' => 'GSV Pierna', 'diametro_max' => 6.8, 'velocidad' => 19, 'duracion' => 2.6, 'diametro' => 6.2, 'observaciones' => 'Reflujo sostenido con dilatación varicosa'],
                            ['nombre' => 'Perforante de Cockett I', 'diametro_max' => 4.4, 'velocidad' => 21, 'duracion' => 2.2, 'diametro' => 4.1, 'observaciones' => 'Incompetente, flujo de dentro hacia afuera'],
                            ['nombre' => 'Perforante de Cockett II', 'diametro_max' => 4.1, 'velocidad' => 18, 'duracion' => 1.9, 'diametro' => 3.8, 'observaciones' => 'Incompetente, bajo el lecho ulceroso'],
                        ],
                        'der_perforantes' => 'Dos perforantes de Cockett incompetentes en el tercio distal, de 4.4 mm y 4.1 mm, ambas situadas bajo el área de la úlcera.',
                        'der_trombosis' => 'Secuela de trombosis venosa profunda femoro-poplítea, sin trombo agudo.',
                        'izq_profundo' => 'Eje femoro-poplíteo permeable, compresible y sin reflujo.',
                        'izq_segmentos' => [
                            ['nombre' => 'SFJ', 'diametro_max' => 5.2, 'velocidad' => 17, 'duracion' => 0.3, 'diametro' => 4.9, 'observaciones' => 'Competente'],
                            ['nombre' => 'GSV Muslo', 'diametro_max' => 4.1, 'velocidad' => 13, 'duracion' => 0.2, 'diametro' => 3.9, 'observaciones' => 'Sin reflujo'],
                            ['nombre' => 'GSV Pierna', 'diametro_max' => 3.2, 'velocidad' => 11, 'duracion' => 0.2, 'diametro' => 3.1, 'observaciones' => 'Sin reflujo'],
                            ['nombre' => null],
                            ['nombre' => null],
                        ],
                        'izq_perforantes' => 'Sin perforantes incompetentes.',
                        'izq_trombosis' => 'Sin signos de trombosis.',
                        'conclusion' => 'Síndrome post-trombótico del miembro inferior derecho, con reflujo profundo poplíteo de 2.8 segundos y dos perforantes de Cockett incompetentes bajo el lecho ulceroso. Insuficiencia superficial secundaria de la safena magna derecha. Miembro inferior izquierdo sin alteraciones. Se recomienda mantener la terapia compresiva de alta rigidez y valorar la ablación de las perforantes una vez cerrada la úlcera.',
                    ],
                    'cobro' => [
                        'metodo_pago' => 'Efectivo',
                        'renglones' => [
                            ['descripcion' => 'Ecodöppler venoso de miembros inferiores (bilateral)', 'cantidad' => 1, 'precio_unitario' => 650.00],
                        ],
                    ],
                ],
            ],

            'citas' => [
                ['dias' => -5, 'hora' => '11:00', 'estado' => 'Programada',
                    'motivo' => 'Control de úlcera venosa y curación'],
                ['dias' => 36, 'hora' => '11:00', 'estado' => 'No Asistió',
                    'motivo' => 'Control de úlcera venosa',
                    'notas' => 'El paciente avisó al día siguiente que no pudo llegar por el paro de transporte.'],
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 3 · Telangiectasias, consulta estética (CEAP C1)
    |--------------------------------------------------------------------------
    |
    | Sin Ecodöppler y sin mapeo venoso, a propósito: no todo expediente los
    | lleva, y el informe tiene que leerse igual de completo cuando la consulta
    | fue solo clínica. Termina en alta, que es el otro desenlace que la
    | institución querrá ver.
    |
    */

    /**
     * @return array<string, mixed>
     */
    private static function tobar(Carbon $hoy): array
    {
        return [
            'paciente' => [
                'nombre' => 'Ana Lucía Tobar Cabrera',
                'edad' => 28,
                'telefono' => '55840392',
                'lugar_residencia' => 'Barrio San Sebastián, zona 1, Santa Cruz del Quiché',
                'estado_civil' => 'Soltero/a',
                'religion' => 'Ninguna',
                'estado' => 'Alta',
                'activo' => true,
            ],

            'consultas' => [
                [
                    'fecha' => self::dia($hoy, 83),
                    'cita' => ['hora' => '16:00', 'motivo' => 'Valoración estética de arañas vasculares'],
                    'campos' => [
                        'consulta_por' => 'Estética',
                        'familiar_varices' => true,
                        'alergias' => 'Ninguna conocida',
                        'gestas' => 0,
                        'abortos' => 0,
                        'partos' => 0,
                        'cesareas' => 0,
                        'hijos_vivos' => 0,
                        'hijos_muertos' => 0,
                        'ultima_menstruacion' => self::dia($hoy, 95),
                        'hormonas' => 'Anticonceptivo oral combinado, en uso desde hace tres años',
                        'presion_arterial' => '110/70',
                        'frecuencia_cardiaca' => 68,
                        'frecuencia_respiratoria' => 16,
                        'temperatura' => 36.5,
                        'peso' => 58.30,
                        'perimetro_tobillo' => 21.6,
                        'perimetro_pantorrilla' => 33.4,
                        'ubicacion_patologia' => 'BILATERAL',
                        'ceap_c' => 'C1',
                        'esclero_concentracion' => 0.25,
                        'esclero_forma' => 'Líquida',
                        'esclero_volumen' => 3.00,
                        'indicaciones_detalle' => [
                            'Medias Compresivas' => 'Media hasta la rodilla, 15-20 mmHg, durante siete días después de cada sesión',
                        ],
                        'estado_general' => 'Requiere nuevas sesiones',
                        'notas' => 'Consulta por motivo estético. Telangiectasias y venas reticulares en cara lateral de ambos muslos y hueco poplíteo, sin síntomas ni signos de insuficiencia troncular. Exploración con Döppler de bolsillo sin reflujo en cayado safeno-femoral ni safeno-poplíteo, por lo que no se indica Ecodöppler. Primera sesión de escleroterapia con polidocanol al 0.25 % en cara lateral de muslo izquierdo. Se explica que serán necesarias entre tres y cuatro sesiones y que la pigmentación temporal es esperable.',
                    ],
                    'selecciones' => [
                        'zonas_pierna' => ['Muslo', 'Pantorrilla'],
                        'sintomas' => ['Asintomática'],
                        'sintomas_aumentan' => ['Estar de pie'],
                        'ceap_diagnostico' => ['Primaria', 'Superficial'],
                        'tx_zonas' => ['Telangiectasias', 'Reticulares'],
                        'indicaciones' => ['Medias Compresivas'],
                    ],
                    'cobro' => [
                        'metodo_pago' => 'Tarjeta',
                        'renglones' => [
                            ['descripcion' => 'Consulta de primera vez — Flebología', 'cantidad' => 1, 'precio_unitario' => 400.00],
                            ['descripcion' => 'Sesión de escleroterapia de telangiectasias', 'cantidad' => 1, 'precio_unitario' => 650.00],
                        ],
                    ],
                ],

                [
                    'fecha' => self::dia($hoy, 48),
                    'cita' => ['hora' => '16:00', 'motivo' => 'Segunda sesión de escleroterapia estética'],
                    'campos' => [
                        'consulta_por' => 'Estética',
                        'familiar_varices' => true,
                        'alergias' => 'Ninguna conocida',
                        'presion_arterial' => '112/70',
                        'frecuencia_cardiaca' => 70,
                        'frecuencia_respiratoria' => 16,
                        'temperatura' => 36.4,
                        'peso' => 58.60,
                        'ubicacion_patologia' => 'BILATERAL',
                        'ceap_c' => 'C1',
                        'esclero_concentracion' => 0.25,
                        'esclero_forma' => 'Líquida',
                        'esclero_volumen' => 3.50,
                        'indicaciones_detalle' => [
                            'Medias Compresivas' => 'Media 15-20 mmHg durante siete días',
                            'Crema' => 'Protector solar factor 50 en las zonas tratadas mientras persista la pigmentación',
                        ],
                        'evolucion' => 'Mejoría',
                        'estado_general' => 'Requiere nuevas sesiones',
                        'notas' => 'Segunda sesión, ahora sobre cara lateral de muslo derecho y hueco poplíteo izquierdo. Las zonas tratadas en la primera sesión muestran desaparición de la mayor parte de las telangiectasias, con matting leve en un área de 2 cm en cara lateral de muslo izquierdo que se explica a la paciente y se deja en observación.',
                    ],
                    'selecciones' => [
                        'zonas_pierna' => ['Muslo', 'Pantorrilla'],
                        'sintomas' => ['Asintomática'],
                        'sintomas_aumentan' => ['Estar de pie'],
                        'ceap_diagnostico' => ['Primaria', 'Superficial'],
                        'tx_zonas' => ['Telangiectasias', 'Reticulares'],
                        'indicaciones' => ['Crema', 'Medias Compresivas'],
                        'observaciones' => ['Buena respuesta', 'Matting'],
                    ],
                    'cobro' => [
                        'metodo_pago' => 'Tarjeta',
                        'renglones' => [
                            ['descripcion' => 'Sesión de escleroterapia de telangiectasias', 'cantidad' => 1, 'precio_unitario' => 650.00],
                        ],
                    ],
                ],

                [
                    'fecha' => self::dia($hoy, 12),
                    'cita' => ['hora' => '16:30', 'motivo' => 'Tercera sesión de escleroterapia estética'],
                    'campos' => [
                        'consulta_por' => 'Estética',
                        'familiar_varices' => true,
                        'alergias' => 'Ninguna conocida',
                        'presion_arterial' => '110/68',
                        'frecuencia_cardiaca' => 66,
                        'frecuencia_respiratoria' => 16,
                        'temperatura' => 36.5,
                        'peso' => 58.40,
                        'ubicacion_patologia' => 'BILATERAL',
                        'ceap_c' => 'C1',
                        'esclero_concentracion' => 0.25,
                        'esclero_forma' => 'Líquida',
                        'esclero_volumen' => 2.00,
                        'indicaciones_detalle' => [
                            'No se prescribe tratamiento adicional' => 'Control anual o antes si aparecen nuevas lesiones',
                        ],
                        'evolucion' => 'Mejoría',
                        'estado_general' => 'Respuesta satisfactoria',
                        'notas' => 'Tercera y última sesión, de retoque sobre lesiones residuales. El matting de la sesión anterior desapareció por completo. Resultado estético satisfactorio según la propia paciente. Se da de alta con indicación de control anual y de evitar la bipedestación prolongada sin medias en su jornada de trabajo.',
                    ],
                    'selecciones' => [
                        'zonas_pierna' => ['Muslo'],
                        'sintomas' => ['Asintomática'],
                        'ceap_diagnostico' => ['Primaria', 'Superficial'],
                        'tx_zonas' => ['Telangiectasias'],
                        'indicaciones' => ['No se prescribe tratamiento adicional'],
                        'observaciones' => ['Buena respuesta', 'Sin complicaciones'],
                    ],
                    'cobro' => [
                        'metodo_pago' => 'Efectivo',
                        'renglones' => [
                            ['descripcion' => 'Sesión de escleroterapia de telangiectasias (retoque)', 'cantidad' => 1, 'precio_unitario' => 450.00],
                        ],
                    ],
                ],
            ],

            'doppler' => [],

            'citas' => [
                ['dias' => 65, 'hora' => '17:00', 'estado' => 'Cancelada',
                    'motivo' => 'Segunda sesión de escleroterapia estética',
                    'cancelacion' => 'La paciente reprogramó por un viaje de trabajo.'],
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 4 · Cambios tróficos bilaterales con comorbilidad (CEAP C4a)
    |--------------------------------------------------------------------------
    |
    | Patología bilateral y antecedentes ginecológicos extensos: es el
    | expediente que llena todas las secciones del informe a la vez, y el que
    | sirve para comprobar que una historia larga sigue cabiendo y leyéndose.
    |
    */

    /**
     * @return array<string, mixed>
     */
    private static function tzoc(Carbon $hoy): array
    {
        return [
            'paciente' => [
                'nombre' => 'Rosa María Tzoc Xiloj',
                'edad' => 56,
                'telefono' => '30764158',
                'lugar_residencia' => 'Cantón Xatinap Segundo, Santa Cruz del Quiché',
                'estado_civil' => 'Unión Libre',
                'religion' => 'Católica',
                'estado' => 'Seguimiento',
                'activo' => true,
            ],

            'consultas' => [
                [
                    'fecha' => self::dia($hoy, 63),
                    'cita' => ['hora' => '14:00', 'duracion' => 45, 'motivo' => 'Primera consulta por manchas y picazón en ambas piernas'],
                    'campos' => [
                        'consulta_por' => 'Enfermedad',
                        'disminuyen_otros' => 'Dormir con una almohada bajo los pies',
                        'familiar_varices' => true,
                        'alergias' => 'Ninguna conocida',
                        'cirugias' => 'Histerectomía abdominal por miomatosis uterina (2016).',
                        'gestas' => 6,
                        'abortos' => 1,
                        'partos' => 5,
                        'cesareas' => 0,
                        'hijos_vivos' => 5,
                        'hijos_muertos' => 0,
                        'hormonas' => 'Ninguna. Histerectomizada desde 2016.',
                        'presion_arterial' => '142/88',
                        'frecuencia_cardiaca' => 80,
                        'frecuencia_respiratoria' => 18,
                        'temperatura' => 36.7,
                        'peso' => 84.20,
                        'perimetro_tobillo' => 28.6,
                        'perimetro_pantorrilla' => 42.4,
                        'ubicacion_patologia' => 'BILATERAL',
                        'ceap_c' => 'C4a',
                        'indicaciones_detalle' => [
                            'Venotónico' => 'Diosmina micronizada 500 mg cada 12 horas por tres meses',
                            'Crema' => 'Emoliente con urea al 10 % dos veces al día en ambas piernas',
                            'Medias Compresivas' => 'Media hasta la rodilla, 20-30 mmHg, en ambos miembros',
                        ],
                        'indicaciones_otros' => 'Se refiere a nutrición para control de peso y a medicina interna para ajuste de hipoglucemiantes y de antihipertensivo.',
                        'estado_general' => 'Requiere nuevas sesiones',
                        'notas' => 'Dermatitis ocre y eccema varicoso en el tercio distal de ambas piernas, más marcado a la izquierda, con prurito que la despierta de noche. Sin úlcera activa ni antecedente de ella. Várices trunculares bilaterales, de predominio izquierdo. Diabetes tipo 2 de ocho años de evolución en tratamiento con metformina, con hemoglobina glucosilada de 7.8 %, e hipertensión arterial en tratamiento. Se prioriza la compresión y el cuidado de la piel antes de cualquier procedimiento, y se solicita Ecodöppler bilateral.',
                    ],
                    'selecciones' => [
                        'zonas_pierna' => ['Pantorrilla', 'Tobillo', 'Pies'],
                        'sintomas' => ['Picazón', 'Manchas en la piel', 'Hinchazón', 'Pesadez', 'Calambres'],
                        'sintomas_aumentan' => ['Estar de pie', 'Calor'],
                        'sintomas_disminuyen' => ['Elevación de las piernas', 'Reposo'],
                        'enfermedades' => ['Diabetes', 'Alta o baja presión', 'Artritis'],
                        'ceap_diagnostico' => ['Primaria', 'Superficial', 'Perforantes', 'Reflujo'],
                        'indicaciones' => ['Venotónico', 'Crema', 'Medias Compresivas'],
                        'observaciones' => ['Pigmentación', 'Inflamación'],
                    ],
                    'mapeo' => [
                        ['tipo' => 'trazo', 'trayecto' => 'safena_interna', 'color' => 'rojo', 'grosor' => 3,
                            'zona' => 'izq_antero_interna', 'puntos' => self::SAFENA_MII],
                        ['tipo' => 'trazo', 'trayecto' => 'safena_interna', 'color' => 'rojo', 'grosor' => 3,
                            'zona' => 'der_antero_interna', 'puntos' => self::SAFENA_MID],
                        ['tipo' => 'trazo', 'trayecto' => 'epifascial', 'color' => 'rojo', 'grosor' => 3,
                            'zona' => 'izq_antero_externa', 'puntos' => self::COLATERAL_MII_EXT],
                        ['tipo' => 'trazo', 'trayecto' => 'epifascial', 'color' => 'rojo', 'grosor' => 3,
                            'zona' => 'der_antero_externa', 'puntos' => self::COLATERAL_MID_EXT],
                        ['tipo' => 'trazo', 'trayecto' => 'safena_externa', 'color' => 'azul', 'grosor' => 3,
                            'zona' => 'izq_posterior', 'puntos' => self::SAFENA_MENOR_MII],
                        ['tipo' => 'marcador', 'marcador' => 'perforante', 'color' => 'rojo', 'numero' => 1,
                            'zona' => 'izq_antero_interna', 'x' => 0.258, 'y' => 0.650],
                        ['tipo' => 'marcador', 'marcador' => 'perforante', 'color' => 'rojo', 'numero' => 2,
                            'zona' => 'izq_antero_interna', 'x' => 0.262, 'y' => 0.745],
                        ['tipo' => 'marcador', 'marcador' => 'perforante', 'color' => 'rojo', 'numero' => 3,
                            'zona' => 'der_antero_interna', 'x' => 0.752, 'y' => 0.650],
                        ['tipo' => 'anotacion', 'color' => 'negro', 'numero' => 4,
                            'zona' => 'izq_antero_interna', 'x' => 0.268, 'y' => 0.800,
                            'texto' => 'Dermatitis ocre y eccema, tercio distal bilateral'],
                    ],
                    'cobro' => [
                        'metodo_pago' => 'Efectivo',
                        'renglones' => [
                            ['descripcion' => 'Consulta de primera vez — Flebología', 'cantidad' => 1, 'precio_unitario' => 400.00],
                            ['tipo' => 'B', 'descripcion' => 'Media compresiva hasta la rodilla 20-30 mmHg', 'cantidad' => 2, 'precio_unitario' => 385.00, 'descuento' => 70.00],
                        ],
                    ],
                ],

                [
                    'fecha' => self::dia($hoy, 21),
                    'cita' => ['hora' => '14:00', 'motivo' => 'Control y primera sesión de escleroterapia — MII'],
                    'campos' => [
                        'consulta_por' => 'Enfermedad',
                        'familiar_varices' => true,
                        'alergias' => 'Ninguna conocida',
                        'presion_arterial' => '134/84',
                        'frecuencia_cardiaca' => 78,
                        'frecuencia_respiratoria' => 17,
                        'temperatura' => 36.6,
                        'peso' => 82.60,
                        'perimetro_tobillo' => 27.4,
                        'perimetro_pantorrilla' => 41.2,
                        'ubicacion_patologia' => 'BILATERAL',
                        'ceap_c' => 'C4a',
                        'esclero_concentracion' => 1.00,
                        'esclero_forma' => 'Espuma',
                        'esclero_volumen' => 5.00,
                        'indicaciones_detalle' => [
                            'Venotónico' => 'Diosmina micronizada 500 mg cada 12 horas, continúa',
                            'Crema' => 'Emoliente con urea al 10 %, continúa',
                            'Medias Compresivas' => 'Media 20-30 mmHg de uso diario',
                        ],
                        'evolucion' => 'Mejoría',
                        'estado_general' => 'Requiere nuevas sesiones',
                        'notas' => 'El prurito cedió por completo y el eccema está resuelto; persiste la hiperpigmentación ocre, que es residual y no se espera que revierta. Bajó 1.6 kg y 1.2 cm de perímetro de tobillo. Con la piel ya íntegra se realiza la primera sesión de escleroterapia con espuma al 1 % sobre las várices trunculares del miembro inferior izquierdo. Se programa el miembro derecho para dentro de seis semanas.',
                    ],
                    'selecciones' => [
                        'zonas_pierna' => ['Pantorrilla', 'Tobillo'],
                        'sintomas' => ['Manchas en la piel', 'Pesadez', 'Hinchazón'],
                        'sintomas_aumentan' => ['Estar de pie', 'Calor'],
                        'sintomas_disminuyen' => ['Elevación de las piernas', 'Medias compresivas'],
                        'enfermedades' => ['Diabetes', 'Alta o baja presión', 'Artritis'],
                        'ceap_diagnostico' => ['Primaria', 'Superficial', 'Perforantes', 'Reflujo'],
                        'tx_zonas' => ['Varicosas trunculares', 'Reticulares'],
                        'indicaciones' => ['Venotónico', 'Crema', 'Medias Compresivas'],
                        'observaciones' => ['Buena respuesta', 'Pigmentación'],
                    ],
                    'cobro' => [
                        'metodo_pago' => 'Efectivo',
                        'renglones' => [
                            ['descripcion' => 'Sesión de escleroterapia con espuma — MII', 'cantidad' => 1, 'precio_unitario' => 750.00],
                        ],
                    ],
                ],
            ],

            'doppler' => [
                [
                    'fecha' => self::dia($hoy, 56),
                    'consulta' => 0,
                    'cita' => ['hora' => '15:30', 'motivo' => 'Ecodöppler venoso de miembros inferiores'],
                    'campos' => [
                        'der_profundo' => 'Eje femoro-poplíteo permeable y compresible, con reflujo femoral de 0.8 segundos, en el límite de lo fisiológico.',
                        'der_segmentos' => [
                            ['nombre' => 'SFJ', 'diametro_max' => 7.2, 'velocidad' => 20, 'duracion' => 1.8, 'diametro' => 6.6, 'observaciones' => 'Reflujo patológico'],
                            ['nombre' => 'GSV Muslo', 'diametro_max' => 6.1, 'velocidad' => 18, 'duracion' => 1.5, 'diametro' => 5.7, 'observaciones' => 'Reflujo en tercio proximal y medio'],
                            ['nombre' => 'GSV Pierna', 'diametro_max' => 4.9, 'velocidad' => 15, 'duracion' => 1.1, 'diametro' => 4.6, 'observaciones' => 'Reflujo leve'],
                            ['nombre' => 'Perforante de Boyd', 'diametro_max' => 3.7, 'velocidad' => 16, 'duracion' => 1.3, 'diametro' => 3.5, 'observaciones' => 'Incompetente'],
                            ['nombre' => null],
                        ],
                        'der_perforantes' => 'Perforante de Boyd incompetente, de 3.7 mm.',
                        'der_trombosis' => 'Sin signos de trombosis.',
                        'izq_profundo' => 'Eje femoro-poplíteo permeable y compresible, sin reflujo.',
                        'izq_segmentos' => [
                            ['nombre' => 'SFJ', 'diametro_max' => 8.4, 'velocidad' => 24, 'duracion' => 2.2, 'diametro' => 7.8, 'observaciones' => 'Reflujo patológico sostenido'],
                            ['nombre' => 'GSV Muslo', 'diametro_max' => 7.1, 'velocidad' => 21, 'duracion' => 2.0, 'diametro' => 6.7, 'observaciones' => 'Reflujo en todo el trayecto'],
                            ['nombre' => 'GSV Pierna', 'diametro_max' => 6.3, 'velocidad' => 19, 'duracion' => 1.8, 'diametro' => 5.9, 'observaciones' => 'Reflujo hasta el tobillo'],
                            ['nombre' => 'Perforante de Cockett II', 'diametro_max' => 4.2, 'velocidad' => 19, 'duracion' => 1.7, 'diametro' => 4.0, 'observaciones' => 'Incompetente, bajo el área de dermatitis'],
                            ['nombre' => 'Perforante de Boyd', 'diametro_max' => 3.9, 'velocidad' => 17, 'duracion' => 1.4, 'diametro' => 3.7, 'observaciones' => 'Incompetente'],
                        ],
                        'izq_perforantes' => 'Dos perforantes incompetentes en pierna izquierda, de 4.2 mm y 3.9 mm.',
                        'izq_trombosis' => 'Sin signos de trombosis.',
                        'conclusion' => 'Insuficiencia venosa superficial primaria bilateral, de predominio izquierdo, con incompetencia de ambas uniones safeno-femorales y de los troncos de safena magna, y tres perforantes incompetentes en total. El sistema venoso profundo es permeable y competente en ambos miembros. Los hallazgos explican los cambios tróficos de la piel. Se recomienda compresión elástica sostenida y tratamiento escalonado, comenzando por el miembro inferior izquierdo.',
                    ],
                    'cobro' => [
                        'metodo_pago' => 'Efectivo',
                        'renglones' => [
                            ['descripcion' => 'Ecodöppler venoso de miembros inferiores (bilateral)', 'cantidad' => 1, 'precio_unitario' => 650.00],
                        ],
                    ],
                ],
            ],

            'citas' => [
                ['dias' => -19, 'hora' => '14:00', 'duracion' => 45, 'estado' => 'Programada',
                    'motivo' => 'Primera sesión de escleroterapia — MID'],
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 5 · Várices trunculares en paciente deportista (CEAP C2)
    |--------------------------------------------------------------------------
    |
    | Trae la única consulta en borrador de los datos de muestra: sirve para
    | enseñar impresa la marca de agua que distingue un expediente sin cerrar
    | de uno firmado, que es una de las cosas que la institución va a preguntar.
    |
    */

    /**
     * @return array<string, mixed>
     */
    private static function maldonado(Carbon $hoy): array
    {
        return [
            'paciente' => [
                'nombre' => 'Héctor Fernando Maldonado Paz',
                'edad' => 39,
                'telefono' => '41238860',
                'lugar_residencia' => '2a calle 4-18, zona 1, Joyabaj, Quiché',
                'estado_civil' => 'Soltero/a',
                'religion' => 'Ninguna',
                'estado' => 'Activo',
                'activo' => true,
            ],

            'consultas' => [
                [
                    'fecha' => self::dia($hoy, 40),
                    'cita' => ['hora' => '07:30', 'motivo' => 'Primera consulta por várices en miembro inferior derecho'],
                    'campos' => [
                        'consulta_por' => 'Enfermedad',
                        'familiar_varices' => true,
                        'alergias' => 'Ninguna conocida',
                        'presion_arterial' => '124/78',
                        'frecuencia_cardiaca' => 58,
                        'frecuencia_respiratoria' => 14,
                        'temperatura' => 36.3,
                        'peso' => 79.60,
                        'perimetro_tobillo' => 23.4,
                        'perimetro_pantorrilla' => 38.6,
                        'ubicacion_patologia' => 'MID',
                        'ceap_c' => 'C2',
                        'indicaciones_detalle' => [
                            'Venotónico' => 'Diosmina/Hesperidina 450/50 mg cada 12 horas por dos meses',
                            'Medias Compresivas' => 'Media deportiva de compresión graduada 15-20 mmHg para los entrenamientos largos',
                        ],
                        'estado_general' => 'Requiere nuevas sesiones',
                        'notas' => 'Corredor de fondo, entrena entre 60 y 70 kilómetros por semana. Refiere pesadez y calambres nocturnos después de los entrenamientos largos, de un año de evolución. A la exploración, várices trunculares visibles en cara antero-interna de muslo y pierna derechos, sin cambios tróficos ni edema. Bradicardia sinusal de reposo propia del entrenamiento, asintomática. Se solicita Ecodöppler para definir el nivel del reflujo antes de planificar el tratamiento, y se explica que la escleroterapia obliga a suspender el entrenamiento de alta intensidad durante una semana por sesión.',
                    ],
                    'selecciones' => [
                        'zonas_pierna' => ['Muslo', 'Pantorrilla'],
                        'sintomas' => ['Cansancio', 'Calambres', 'Pesadez'],
                        'sintomas_aumentan' => ['Ejercicio', 'Estar de pie'],
                        'sintomas_disminuyen' => ['Elevación de las piernas', 'Medias compresivas'],
                        'ceap_diagnostico' => ['Primaria', 'Superficial', 'Reflujo'],
                        'indicaciones' => ['Venotónico', 'Medias Compresivas'],
                    ],
                    'mapeo' => [
                        ['tipo' => 'trazo', 'trayecto' => 'safena_interna', 'color' => 'rojo', 'grosor' => 3,
                            'zona' => 'der_antero_interna', 'puntos' => self::SAFENA_MID],
                        ['tipo' => 'trazo', 'trayecto' => 'hipoplasico', 'color' => 'azul', 'grosor' => 3,
                            'zona' => 'der_posterior', 'puntos' => self::SAFENA_MENOR_MID],
                        ['tipo' => 'trazo', 'trayecto' => 'safena_interna', 'color' => 'azul', 'grosor' => 3,
                            'zona' => 'izq_antero_interna', 'puntos' => self::SAFENA_MII],
                        ['tipo' => 'marcador', 'marcador' => 'perforante', 'color' => 'rojo', 'numero' => 1,
                            'zona' => 'der_antero_interna', 'x' => 0.751, 'y' => 0.628],
                        ['tipo' => 'marcador', 'marcador' => 'golfo_venoso', 'color' => 'rojo', 'numero' => 2,
                            'zona' => 'der_antero_interna', 'x' => 0.746, 'y' => 0.430],
                        ['tipo' => 'anotacion', 'color' => 'negro', 'numero' => 3,
                            'zona' => 'der_posterior', 'x' => 0.874, 'y' => 0.690,
                            'texto' => 'Safena menor hipoplásica, sin reflujo'],
                    ],
                    'cobro' => [
                        'metodo_pago' => 'Tarjeta',
                        'renglones' => [
                            ['descripcion' => 'Consulta de primera vez — Flebología', 'cantidad' => 1, 'precio_unitario' => 400.00],
                        ],
                    ],
                ],

                [
                    'fecha' => self::dia($hoy, 5),
                    'estado_registro' => 'Borrador',
                    'cita' => ['hora' => '07:30', 'motivo' => 'Planificación de escleroterapia — MID'],
                    'campos' => [
                        'consulta_por' => 'Enfermedad',
                        'familiar_varices' => true,
                        'alergias' => 'Ninguna conocida',
                        'presion_arterial' => '122/76',
                        'frecuencia_cardiaca' => 56,
                        'peso' => 79.20,
                        'perimetro_tobillo' => 23.1,
                        'perimetro_pantorrilla' => 38.4,
                        'ubicacion_patologia' => 'MID',
                        'ceap_c' => 'C2',
                        'estado_general' => 'Requiere nuevas sesiones',
                        'notas' => 'Consulta de planificación. Con el Ecodöppler a la vista se acuerda tratar primero el tronco safeno de muslo con espuma y dejar las colaterales de pierna para una segunda sesión. Falta confirmar con el paciente la fecha, que depende de su calendario de competencia.',
                    ],
                    'selecciones' => [
                        'zonas_pierna' => ['Muslo', 'Pantorrilla'],
                        'sintomas' => ['Cansancio', 'Pesadez'],
                        'ceap_diagnostico' => ['Primaria', 'Superficial', 'Reflujo'],
                    ],
                ],
            ],

            'doppler' => [
                [
                    'fecha' => self::dia($hoy, 33),
                    'consulta' => 0,
                    'cita' => ['hora' => '08:00', 'motivo' => 'Ecodöppler venoso de miembros inferiores'],
                    'campos' => [
                        'der_profundo' => 'Eje femoro-poplíteo permeable y compresible, con flujo fásico y sin reflujo.',
                        'der_segmentos' => [
                            ['nombre' => 'SFJ', 'diametro_max' => 7.4, 'velocidad' => 23, 'duracion' => 1.9, 'diametro' => 6.9, 'observaciones' => 'Reflujo patológico'],
                            ['nombre' => 'GSV Muslo', 'diametro_max' => 6.2, 'velocidad' => 19, 'duracion' => 1.7, 'diametro' => 5.8, 'observaciones' => 'Reflujo en tercio proximal y medio'],
                            ['nombre' => 'GSV Pierna', 'diametro_max' => 4.1, 'velocidad' => 13, 'duracion' => 0.4, 'diametro' => 3.9, 'observaciones' => 'Competente por debajo de la rodilla'],
                            ['nombre' => 'Perforante de Dodd', 'diametro_max' => 3.5, 'velocidad' => 15, 'duracion' => 1.2, 'diametro' => 3.3, 'observaciones' => 'Incompetente'],
                            ['nombre' => 'Safena menor', 'diametro_max' => 2.2, 'velocidad' => 9, 'duracion' => 0.2, 'diametro' => 2.1, 'observaciones' => 'Hipoplásica, sin reflujo'],
                        ],
                        'der_perforantes' => 'Perforante de Dodd incompetente, de 3.5 mm, en el canal de Hunter.',
                        'der_trombosis' => 'Sin signos de trombosis.',
                        'izq_profundo' => 'Eje femoro-poplíteo permeable, compresible y sin reflujo.',
                        'izq_segmentos' => [
                            ['nombre' => 'SFJ', 'diametro_max' => 5.1, 'velocidad' => 16, 'duracion' => 0.3, 'diametro' => 4.8, 'observaciones' => 'Competente'],
                            ['nombre' => 'GSV Muslo', 'diametro_max' => 4.2, 'velocidad' => 14, 'duracion' => 0.2, 'diametro' => 4.0, 'observaciones' => 'Sin reflujo'],
                            ['nombre' => 'GSV Pierna', 'diametro_max' => 3.3, 'velocidad' => 12, 'duracion' => 0.2, 'diametro' => 3.2, 'observaciones' => 'Sin reflujo'],
                            ['nombre' => null],
                            ['nombre' => null],
                        ],
                        'izq_perforantes' => 'Sin perforantes incompetentes.',
                        'izq_trombosis' => 'Sin signos de trombosis.',
                        'conclusion' => 'Insuficiencia de la unión safeno-femoral y del tronco de la safena magna derecha limitada al muslo, con perforante de Dodd incompetente y safena magna de pierna competente. Safena menor derecha hipoplásica sin reflujo. Miembro inferior izquierdo normal. El reflujo es segmentario, por lo que se puede tratar el segmento de muslo y conservar el resto del tronco.',
                    ],
                    // Sin cobro propio: este estudio lo paga el patrono del
                    // paciente, con factura a nombre de la empresa. Está en
                    // Cobros::sueltos().
                ],
            ],

            'citas' => [
                ['dias' => -8, 'hora' => '07:30', 'duracion' => 45, 'estado' => 'Programada',
                    'motivo' => 'Primera sesión de escleroterapia — MID'],
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 6 · Várices del embarazo sin regresión (CEAP C2)
    |--------------------------------------------------------------------------
    |
    | Tres consultas repartidas a lo largo de cuatro meses, que es lo que hace
    | que los reportes de período tengan actividad tanto en los meses anteriores
    | como en el mes en curso.
    |
    */

    /**
     * @return array<string, mixed>
     */
    private static function recinos(Carbon $hoy): array
    {
        return [
            'paciente' => [
                'nombre' => 'Silvia Patricia Recinos de León',
                'edad' => 33,
                'telefono' => '57690244',
                'lugar_residencia' => 'Colonia El Calvario, zona 2, Santa Cruz del Quiché',
                'estado_civil' => 'Casado/a',
                'religion' => 'Católica',
                'estado' => 'Seguimiento',
                'activo' => true,
            ],

            'consultas' => [
                [
                    'fecha' => self::dia($hoy, 110),
                    'cita' => ['hora' => '10:30', 'motivo' => 'Primera consulta por várices aparecidas en el embarazo'],
                    'campos' => [
                        'consulta_por' => 'Enfermedad',
                        'familiar_varices' => true,
                        'alergias' => 'Ninguna conocida',
                        'cirugias' => 'Ninguna.',
                        'gestas' => 2,
                        'abortos' => 0,
                        'partos' => 2,
                        'cesareas' => 0,
                        'hijos_vivos' => 2,
                        'hijos_muertos' => 0,
                        'ultima_menstruacion' => self::dia($hoy, 118),
                        'hormonas' => 'Ninguna. Lactancia materna finalizada hace dos meses.',
                        'presion_arterial' => '112/72',
                        'frecuencia_cardiaca' => 76,
                        'frecuencia_respiratoria' => 16,
                        'temperatura' => 36.5,
                        'peso' => 64.80,
                        'perimetro_tobillo' => 22.8,
                        'perimetro_pantorrilla' => 34.9,
                        'ubicacion_patologia' => 'BILATERAL',
                        'ceap_c' => 'C2',
                        'indicaciones_detalle' => [
                            'Venotónico' => 'Diosmina/Hesperidina 450/50 mg cada 12 horas por dos meses',
                            'Medias Compresivas' => 'Media hasta la rodilla, 15-20 mmHg, de uso diurno',
                        ],
                        'estado_general' => 'Requiere nuevas sesiones',
                        'notas' => 'Várices aparecidas durante el segundo embarazo, sin regresión a los diez meses del parto. Refiere pesadez y adormecimiento al final del día, sobre todo en la pierna derecha. Lactancia materna finalizada hace dos meses, por lo que ya es posible iniciar escleroterapia. Sin cambios tróficos de la piel ni antecedente de trombosis. Se solicita Ecodöppler para descartar incompetencia troncular antes de tratar.',
                    ],
                    'selecciones' => [
                        'zonas_pierna' => ['Muslo', 'Pantorrilla'],
                        'sintomas' => ['Pesadez', 'Hinchazón', 'Adormecimiento'],
                        'sintomas_aumentan' => ['Estar de pie', 'Calor'],
                        'sintomas_disminuyen' => ['Elevación de las piernas', 'Medias compresivas'],
                        'ceap_diagnostico' => ['Primaria', 'Superficial'],
                        'indicaciones' => ['Venotónico', 'Medias Compresivas'],
                    ],
                    'cobro' => [
                        'metodo_pago' => 'Efectivo',
                        'renglones' => [
                            ['descripcion' => 'Consulta de primera vez — Flebología', 'cantidad' => 1, 'precio_unitario' => 400.00],
                        ],
                    ],
                ],

                [
                    'fecha' => self::dia($hoy, 58),
                    'cita' => ['hora' => '10:30', 'motivo' => 'Primera sesión de escleroterapia — bilateral'],
                    'campos' => [
                        'consulta_por' => 'Enfermedad',
                        'familiar_varices' => true,
                        'alergias' => 'Ninguna conocida',
                        'presion_arterial' => '110/70',
                        'frecuencia_cardiaca' => 74,
                        'frecuencia_respiratoria' => 16,
                        'temperatura' => 36.4,
                        'peso' => 64.20,
                        'perimetro_tobillo' => 22.4,
                        'perimetro_pantorrilla' => 34.5,
                        'ubicacion_patologia' => 'BILATERAL',
                        'ceap_c' => 'C2',
                        'esclero_concentracion' => 0.50,
                        'esclero_forma' => 'Espuma',
                        'esclero_volumen' => 4.00,
                        'indicaciones_detalle' => [
                            'Medias Compresivas' => 'Media 15-20 mmHg de uso continuo durante diez días',
                            'AINEs' => 'Acetaminofén 500 mg cada 8 horas si hay molestia, solo los primeros dos días',
                        ],
                        'evolucion' => 'Mejoría',
                        'estado_general' => 'Requiere nuevas sesiones',
                        'notas' => 'Primera sesión de escleroterapia con espuma de polidocanol al 0.5 % sobre colaterales de pierna derecha y reticulares de muslo izquierdo. Sin incidentes. Se indica caminar 30 minutos al salir y evitar baños calientes y exposición solar durante dos semanas.',
                    ],
                    'selecciones' => [
                        'zonas_pierna' => ['Muslo', 'Pantorrilla'],
                        'sintomas' => ['Pesadez', 'Hinchazón'],
                        'sintomas_aumentan' => ['Estar de pie'],
                        'sintomas_disminuyen' => ['Elevación de las piernas', 'Medias compresivas'],
                        'ceap_diagnostico' => ['Primaria', 'Superficial'],
                        'tx_zonas' => ['Varicosas trunculares', 'Reticulares'],
                        'indicaciones' => ['AINEs', 'Medias Compresivas'],
                        'observaciones' => ['Buena respuesta', 'Sin complicaciones'],
                    ],
                    // El primer recibo salió con el concepto de la sesión anterior
                    // y por el precio de una sola pierna. No se corrige: se
                    // anula —el número queda gastado— y se emite el bueno, que
                    // es lo que el módulo obliga a hacer.
                    'cobro_anulado' => [
                        'metodo_pago' => 'Transferencia',
                        'anulacion' => [
                            'dias' => 58,
                            'motivo' => 'Emitido con el concepto equivocado: dice sesión de telangiectasias y la sesión fue de espuma bilateral',
                        ],
                        'renglones' => [
                            ['descripcion' => 'Sesión de escleroterapia de telangiectasias', 'cantidad' => 1, 'precio_unitario' => 650.00],
                        ],
                    ],
                    'cobro' => [
                        'metodo_pago' => 'Transferencia',
                        'observaciones' => 'Sustituye al documento anulado de la misma fecha.',
                        'renglones' => [
                            ['descripcion' => 'Sesión de escleroterapia con espuma — bilateral', 'cantidad' => 1, 'precio_unitario' => 850.00],
                        ],
                    ],
                ],

                [
                    'fecha' => self::dia($hoy, 9),
                    'cita' => ['hora' => '10:00', 'motivo' => 'Segunda sesión de escleroterapia'],
                    'campos' => [
                        'consulta_por' => 'Enfermedad',
                        'familiar_varices' => true,
                        'alergias' => 'Ninguna conocida',
                        'presion_arterial' => '110/68',
                        'frecuencia_cardiaca' => 72,
                        'frecuencia_respiratoria' => 16,
                        'temperatura' => 36.5,
                        'peso' => 63.90,
                        'perimetro_tobillo' => 22.0,
                        'perimetro_pantorrilla' => 34.1,
                        'ubicacion_patologia' => 'MID',
                        'ceap_c' => 'C1',
                        'esclero_concentracion' => 0.25,
                        'esclero_forma' => 'Líquida',
                        'esclero_volumen' => 2.50,
                        'indicaciones_detalle' => [
                            'Medias Compresivas' => 'Media 15-20 mmHg durante diez días más',
                            'Crema' => 'Protector solar factor 50 en las zonas tratadas',
                        ],
                        'evolucion' => 'Mejoría',
                        'estado_general' => 'Respuesta satisfactoria',
                        'notas' => 'Segunda sesión sobre telangiectasias residuales de pierna derecha. Las colaterales tratadas en la sesión anterior están ocluidas y no se palpan cordones. Cedió la pesadez y el perímetro de tobillo bajó 0.8 cm desde la primera consulta. Se cita a control en tres meses y se explica que un tercer embarazo puede hacer reaparecer las várices.',
                    ],
                    'selecciones' => [
                        'zonas_pierna' => ['Pantorrilla'],
                        'sintomas' => ['Asintomática'],
                        'sintomas_disminuyen' => ['Medias compresivas'],
                        'ceap_diagnostico' => ['Primaria', 'Superficial'],
                        'tx_zonas' => ['Telangiectasias', 'Reticulares'],
                        'indicaciones' => ['Crema', 'Medias Compresivas'],
                        'observaciones' => ['Buena respuesta', 'Sin complicaciones'],
                    ],
                    'cobro' => [
                        'metodo_pago' => 'Transferencia',
                        'renglones' => [
                            ['descripcion' => 'Sesión de escleroterapia de telangiectasias', 'cantidad' => 1, 'precio_unitario' => 650.00],
                        ],
                    ],
                ],
            ],

            'doppler' => [
                [
                    'fecha' => self::dia($hoy, 103),
                    'consulta' => 0,
                    'cita' => ['hora' => '11:30', 'motivo' => 'Ecodöppler venoso de miembros inferiores'],
                    'campos' => [
                        'der_profundo' => 'Eje femoro-poplíteo permeable y compresible, sin reflujo.',
                        'der_segmentos' => [
                            ['nombre' => 'SFJ', 'diametro_max' => 5.6, 'velocidad' => 17, 'duracion' => 0.4, 'diametro' => 5.2, 'observaciones' => 'Competente'],
                            ['nombre' => 'GSV Muslo', 'diametro_max' => 4.4, 'velocidad' => 14, 'duracion' => 0.3, 'diametro' => 4.2, 'observaciones' => 'Sin reflujo'],
                            ['nombre' => 'GSV Pierna', 'diametro_max' => 3.8, 'velocidad' => 12, 'duracion' => 0.3, 'diametro' => 3.6, 'observaciones' => 'Sin reflujo'],
                            ['nombre' => 'Colateral de pierna', 'diametro_max' => 4.6, 'velocidad' => 11, 'duracion' => 1.1, 'diametro' => 4.3, 'observaciones' => 'Varicosa, con reflujo propio'],
                            ['nombre' => null],
                        ],
                        'der_perforantes' => 'Sin perforantes incompetentes.',
                        'der_trombosis' => 'Sin signos de trombosis.',
                        'izq_profundo' => 'Eje femoro-poplíteo permeable y compresible, sin reflujo.',
                        'izq_segmentos' => [
                            ['nombre' => 'SFJ', 'diametro_max' => 5.1, 'velocidad' => 16, 'duracion' => 0.3, 'diametro' => 4.8, 'observaciones' => 'Competente'],
                            ['nombre' => 'GSV Muslo', 'diametro_max' => 4.0, 'velocidad' => 13, 'duracion' => 0.2, 'diametro' => 3.8, 'observaciones' => 'Sin reflujo'],
                            ['nombre' => 'GSV Pierna', 'diametro_max' => 3.4, 'velocidad' => 11, 'duracion' => 0.2, 'diametro' => 3.3, 'observaciones' => 'Sin reflujo'],
                            ['nombre' => null],
                            ['nombre' => null],
                        ],
                        'izq_perforantes' => 'Sin perforantes incompetentes.',
                        'izq_trombosis' => 'Sin signos de trombosis.',
                        'conclusion' => 'Ambos troncos safenos son competentes y el sistema venoso profundo es permeable, sin secuelas. Las várices corresponden a colaterales con reflujo propio, de origen gestacional, tratables con escleroterapia sin necesidad de actuar sobre el tronco safeno. No hay criterios para tratamiento quirúrgico.',
                    ],
                    'cobro' => [
                        'metodo_pago' => 'Efectivo',
                        'renglones' => [
                            ['descripcion' => 'Ecodöppler venoso de miembros inferiores (bilateral)', 'cantidad' => 1, 'precio_unitario' => 650.00],
                        ],
                    ],
                ],
            ],

            'citas' => [
                ['dias' => -26, 'hora' => '10:00', 'estado' => 'Programada',
                    'motivo' => 'Control a los tres meses'],
            ],
        ];
    }
}
