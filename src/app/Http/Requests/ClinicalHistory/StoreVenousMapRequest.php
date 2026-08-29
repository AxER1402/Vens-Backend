<?php

namespace App\Http\Requests\ClinicalHistory;

use App\Support\MapeoVenoso\Catalogo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVenousMapRequest extends FormRequest
{
    /**
     * Tipos de objeto que admite el documento vectorial.
     *
     * @var array<int, string>
     */
    public const TIPOS = ['trazo', 'marcador', 'anotacion', 'texto'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas planas del documento.
     *
     * La forma de cada objeto depende de su `tipo`, y eso los comodines de
     * Laravel no lo expresan: la geometría se valida en withValidator().
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // El editor del mapeo venoso genera un PNG en formato data URL
            'imagen' => ['required', 'string', 'regex:/^data:image\/png;base64,[A-Za-z0-9+\/=\s]+$/'],

            // Documento vectorial del mapeo, lo que permite reabrirlo y seguir
            // editándolo. Es opcional: sin él se archiva solo la imagen, como
            // hacía la versión anterior del módulo.
            'datos' => ['nullable', 'array'],
            'datos.version' => ['required_with:datos', 'integer', Rule::in(Catalogo::versiones())],
            'datos.plantilla' => ['nullable', 'string', Rule::in([Catalogo::plantillaId()])],
            'datos.objetos' => ['required_with:datos', 'array', 'max:'.Catalogo::limite('max_objetos')],
            'datos.objetos.*' => ['array'],
            'datos.objetos.*.tipo' => ['required', 'string', Rule::in(self::TIPOS)],
        ];
    }

    /**
     * Validar la geometría y los hallazgos objeto por objeto.
     *
     * Sin esto el documento entra como JSON libre: se archivaría un mapeo con
     * coordenadas fuera del lienzo o con un hallazgo que el catálogo no conoce,
     * y el problema no aparecería hasta que alguien intentara reabrirlo o
     * imprimirlo, cuando ya no hay forma de recuperar lo que el médico dibujó.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $objetos = $this->input('datos.objetos');

            if (! is_array($objetos)) {
                return;
            }

            $puntosTotales = 0;

            foreach ($objetos as $indice => $objeto) {
                if (! is_array($objeto)) {
                    continue;   // ya lo reporta la regla datos.objetos.*
                }

                $tipo = $objeto['tipo'] ?? null;

                if (! in_array($tipo, self::TIPOS, true)) {
                    continue;   // ya lo reporta la regla datos.objetos.*.tipo
                }

                $campo = "datos.objetos.{$indice}";

                $this->validarZona($validator, $campo, $objeto);

                match ($tipo) {
                    'trazo' => $puntosTotales += $this->validarTrazo($validator, $campo, $objeto),
                    'marcador' => $this->validarMarcador($validator, $campo, $objeto),
                    'anotacion', 'texto' => $this->validarTextual($validator, $campo, $objeto, $tipo),
                };
            }

            // El tope real del tamaño de la columna JSON. Limitar solo el número
            // de objetos no acota nada: un único trazo puede traer cientos de
            // miles de puntos.
            $maxPuntos = Catalogo::limite('max_puntos_total');

            if ($puntosTotales > $maxPuntos) {
                $validator->errors()->add(
                    'datos.objetos',
                    "El mapeo venoso supera el máximo de {$maxPuntos} puntos de trazo en total."
                );
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Validación por tipo de objeto
    |--------------------------------------------------------------------------
    */

    /**
     * Trazo: recorrido venoso. Devuelve cuántos puntos aportó al total.
     *
     * @param  array<string, mixed>  $objeto
     */
    private function validarTrazo(Validator $validator, string $campo, array $objeto): int
    {
        $this->validarVocabulario($validator, $campo, $objeto, 'trazo');

        // El color solo es obligatorio con el vocabulario nuevo: en el heredado
        // lo aportaba el propio hallazgo.
        $this->validarColor($validator, $campo, $objeto, isset($objeto['trayecto']));

        $puntos = $objeto['puntos'] ?? null;

        if (! is_array($puntos) || count($puntos) < 2) {
            $validator->errors()->add("{$campo}.puntos", 'Un trazo del mapeo venoso debe tener al menos dos puntos.');

            return 0;
        }

        $maxPuntosTrazo = Catalogo::limite('max_puntos_trazo');

        if (count($puntos) > $maxPuntosTrazo) {
            $validator->errors()->add("{$campo}.puntos", "Un trazo del mapeo venoso no puede superar los {$maxPuntosTrazo} puntos.");

            return count($puntos);
        }

        foreach ($puntos as $i => $punto) {
            if (! is_array($punto) || count($punto) !== 2
                || ! $this->esCoordenada($punto[0] ?? null)
                || ! $this->esCoordenada($punto[1] ?? null)) {
                $validator->errors()->add("{$campo}.puntos.{$i}", 'Un punto del trazo está fuera del lienzo del mapeo venoso.');
                break;   // basta con señalar el trazo una vez
            }
        }

        if (array_key_exists('grosor', $objeto) && $objeto['grosor'] !== null
            && ! in_array($objeto['grosor'], Catalogo::grosores(), false)) {
            $validator->errors()->add("{$campo}.grosor", 'El grosor de trazo no es uno de los admitidos.');
        }

        return count($puntos);
    }

    /**
     * Marcador: hallazgo puntual numerado.
     *
     * @param  array<string, mixed>  $objeto
     */
    private function validarMarcador(Validator $validator, string $campo, array $objeto): void
    {
        $this->validarVocabulario($validator, $campo, $objeto, 'marcador');
        $this->validarColor($validator, $campo, $objeto, false);
        $this->validarAncla($validator, $campo, $objeto);
    }

    /**
     * Anotación anclada o etiqueta de texto libre.
     *
     * @param  array<string, mixed>  $objeto
     */
    private function validarTextual(Validator $validator, string $campo, array $objeto, string $tipo): void
    {
        $this->validarColor($validator, $campo, $objeto, false);
        $this->validarAncla($validator, $campo, $objeto);

        $texto = $objeto['texto'] ?? null;
        $maxLongitud = Catalogo::limite('max_longitud_texto');

        if (! is_string($texto) || trim($texto) === '') {
            $validator->errors()->add("{$campo}.texto", 'Una anotación del mapeo venoso no puede quedar vacía.');
        } elseif (mb_strlen($texto) > $maxLongitud) {
            $validator->errors()->add("{$campo}.texto", "Una anotación del mapeo venoso no puede superar los {$maxLongitud} caracteres.");
        }

        if ($tipo === 'texto' && array_key_exists('tamano', $objeto) && $objeto['tamano'] !== null
            && (! is_numeric($objeto['tamano']) || $objeto['tamano'] <= 0 || $objeto['tamano'] > 200)) {
            $validator->errors()->add("{$campo}.tamano", 'El tamaño del texto del mapeo venoso no es válido.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Piezas compartidas
    |--------------------------------------------------------------------------
    */

    /**
     * Punto de anclaje de todo objeto que no sea un trazo.
     *
     * @param  array<string, mixed>  $objeto
     */
    private function validarAncla(Validator $validator, string $campo, array $objeto): void
    {
        foreach (['x', 'y'] as $eje) {
            if (! $this->esCoordenada($objeto[$eje] ?? null)) {
                $validator->errors()->add("{$campo}.{$eje}", 'Un objeto del mapeo venoso está fuera del lienzo.');
            }
        }
    }

    /**
     * Qué se dibujó: un tipo de trayecto si es un trazo, un símbolo si es un
     * hallazgo puntual. Es el eje que dice *qué* es la marca; el color, aparte,
     * dice en qué estado está.
     *
     * Se acepta también el `hallazgo` del vocabulario anterior, que mezclaba
     * ambas cosas. No es por compatibilidad decorativa: al reabrir un mapeo
     * archivado el editor devuelve sus objetos tal y como los leyó, y rechazar
     * ese vocabulario haría que guardar una corrección sobre un mapeo viejo
     * fallara entero.
     *
     * @param  array<string, mixed>  $objeto
     */
    private function validarVocabulario(Validator $validator, string $campo, array $objeto, string $tipo): void
    {
        [$clave, $ids] = $tipo === 'trazo'
            ? ['trayecto', Catalogo::idsTrayecto()]
            : ['marcador', Catalogo::idsMarcador()];

        $valor = $objeto[$clave] ?? null;

        if ($valor !== null) {
            if (! is_string($valor) || ! in_array($valor, $ids, true)) {
                $validator->errors()->add(
                    "{$campo}.{$clave}",
                    $tipo === 'trazo'
                        ? 'El mapeo venoso contiene un trayecto que no está en el catálogo.'
                        : 'El mapeo venoso contiene un marcador que no está en el catálogo.'
                );
            }

            return;
        }

        // Vocabulario heredado: debe seguir correspondiendo al tipo de objeto,
        // un marcador no puede llevar un hallazgo pensado para trazos.
        $hallazgo = $objeto['hallazgo'] ?? null;

        if (is_string($hallazgo) && in_array($hallazgo, Catalogo::idsHallazgo($tipo), true)) {
            return;
        }

        $validator->errors()->add(
            "{$campo}.{$clave}",
            $tipo === 'trazo'
                ? 'Cada trazo del mapeo venoso debe indicar su tipo de trayecto.'
                : 'Cada hallazgo puntual del mapeo venoso debe indicar qué marca.'
        );
    }

    /**
     * La zona la calcula el editor a partir de la posición; aquí solo se
     * comprueba que sea una de las vistas de la plantilla. Un objeto puede no
     * tener zona: cayó en la franja que separa los dos paneles.
     *
     * @param  array<string, mixed>  $objeto
     */
    private function validarZona(Validator $validator, string $campo, array $objeto): void
    {
        $zona = $objeto['zona'] ?? null;

        if ($zona !== null && ! in_array($zona, Catalogo::idsZona(), true)) {
            $validator->errors()->add("{$campo}.zona", 'El mapeo venoso hace referencia a una zona anatómica desconocida.');
        }
    }

    /**
     * Color, que en el mapeo es la lectura clínica del vaso y no un gusto: azul
     * competente, rojo refluyente, negro trombosado. Por eso se archiva como
     * referencia al catálogo ('rojo') y no como hexadecimal, y por eso en un
     * trazo del vocabulario nuevo es obligatorio: un recorrido sin color no dice
     * en qué estado está la vena.
     *
     * El hexadecimal se sigue admitiendo porque así se archivaron los mapeos
     * anteriores a esta separación.
     *
     * @param  array<string, mixed>  $objeto
     */
    private function validarColor(Validator $validator, string $campo, array $objeto, bool $obligatorio): void
    {
        $color = $objeto['color'] ?? null;

        if ($color === null) {
            if ($obligatorio) {
                $validator->errors()->add("{$campo}.color", 'Cada trazo del mapeo venoso debe indicar su color, que es lo que expresa el hallazgo.');
            }

            return;
        }

        if (is_string($color)
            && (in_array($color, Catalogo::idsColor(), true) || preg_match('/^#[0-9A-Fa-f]{6}$/', $color))) {
            return;
        }

        $validator->errors()->add("{$campo}.color", 'El color de un objeto del mapeo venoso no está en el catálogo.');
    }

    /**
     * Las coordenadas se archivan normalizadas 0-1 respecto a la plantilla, no
     * en píxeles: así cambiar la plantilla por una versión de mayor resolución
     * no invalida los mapeos ya archivados.
     */
    private function esCoordenada(mixed $valor): bool
    {
        return is_numeric($valor) && $valor >= 0 && $valor <= 1;
    }

    /*
    |--------------------------------------------------------------------------
    | Imagen
    |--------------------------------------------------------------------------
    */

    /**
     * Devolver el contenido binario del PNG recibido.
     */
    public function contenidoBinario(): ?string
    {
        $base64 = substr($this->input('imagen'), strlen('data:image/png;base64,'));

        $binario = base64_decode(preg_replace('/\s+/', '', $base64), true);

        return $binario === false ? null : $binario;
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'imagen.required' => 'No se recibió el mapeo venoso a guardar.',
            'imagen.regex' => 'El mapeo venoso debe enviarse como una imagen PNG válida.',
            'datos.version.in' => 'El mapeo venoso viene en un formato que este sistema no sabe leer.',
            'datos.plantilla.in' => 'El mapeo venoso fue dibujado sobre una plantilla desconocida.',
            'datos.objetos.max' => 'El mapeo venoso supera el máximo de :max elementos.',
            'datos.objetos.*.tipo.in' => 'El mapeo venoso contiene un elemento de tipo desconocido.',
        ];
    }
}
