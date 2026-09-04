<?php

namespace App\Support\Contacto;

/**
 * Normalización de un número de teléfono.
 *
 * El sistema guarda solo dígitos. La razón no es de estilo: si una persona
 * anota «2222-2222» y otra busca «22222222», la segunda no encuentra a nadie,
 * y en el mostrador eso se traduce en un paciente duplicado. Un formato único
 * hace que las dos escrituras sean el mismo dato.
 *
 * Se normaliza en vez de rechazar lo que llega con guiones o espacios: quien
 * escribe un teléfono lo copia de una tarjeta o de una pantalla, y esos
 * separadores son ruido, no un error del que haya que avisar. Lo que sí se
 * rechaza es lo que no es un teléfono.
 */
class Telefono
{
    /** Ocho dígitos es el largo local; quince, el máximo internacional. */
    public const MINIMO = 8;

    public const MAXIMO = 15;

    /**
     * Deja solo los dígitos. Null si no queda ninguno.
     */
    public static function normalizar(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $digitos = preg_replace('/\D+/', '', (string) $valor);

        return $digitos === '' ? null : $digitos;
    }

    /**
     * Regla de validación del valor ya normalizado.
     *
     * `$parcial` es para las actualizaciones: si el campo no viene en la
     * petición no se toca, pero si viene tiene que ser un teléfono.
     *
     * @return array<int, string>
     */
    public static function reglas(bool $obligatorio = true, bool $parcial = false): array
    {
        return array_values(array_filter([
            $parcial ? 'sometimes' : null,
            $obligatorio ? 'required' : 'nullable',
            'string',
            'regex:/^[0-9]+$/',
            'min:'.self::MINIMO,
            'max:'.self::MAXIMO,
        ]));
    }

    /**
     * Mensajes para el campo, con el nombre de atributo que se le quiera dar.
     *
     * @return array<string, string>
     */
    public static function mensajes(string $campo = 'telefono'): array
    {
        return [
            "{$campo}.required" => 'El teléfono de contacto es obligatorio.',
            "{$campo}.regex" => 'El teléfono se guarda solo con números, sin guiones ni espacios.',
            "{$campo}.min" => 'El teléfono debe tener al menos '.self::MINIMO.' dígitos.',
            "{$campo}.max" => 'El teléfono no puede tener más de '.self::MAXIMO.' dígitos.',
        ];
    }
}
