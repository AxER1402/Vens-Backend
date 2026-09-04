<?php

namespace App\Support\Facturacion;

/**
 * El monto escrito con letras.
 *
 * Un recibo lo lleva por una razón práctica y vieja: la cifra en números se
 * puede alterar con un trazo, y el texto no. Por eso se escribe entero y los
 * centavos se dejan en fracción —«con 50/100»—, que es la forma en que se
 * escriben en el papel de toda la vida.
 */
class Cantidad
{
    private const UNIDADES = [
        0 => '', 1 => 'uno', 2 => 'dos', 3 => 'tres', 4 => 'cuatro', 5 => 'cinco',
        6 => 'seis', 7 => 'siete', 8 => 'ocho', 9 => 'nueve', 10 => 'diez',
        11 => 'once', 12 => 'doce', 13 => 'trece', 14 => 'catorce', 15 => 'quince',
        16 => 'dieciséis', 17 => 'diecisiete', 18 => 'dieciocho', 19 => 'diecinueve',
        20 => 'veinte', 21 => 'veintiuno', 22 => 'veintidós', 23 => 'veintitrés',
        24 => 'veinticuatro', 25 => 'veinticinco', 26 => 'veintiséis',
        27 => 'veintisiete', 28 => 'veintiocho', 29 => 'veintinueve',
    ];

    private const DECENAS = [
        3 => 'treinta', 4 => 'cuarenta', 5 => 'cincuenta',
        6 => 'sesenta', 7 => 'setenta', 8 => 'ochenta', 9 => 'noventa',
    ];

    private const CENTENAS = [
        1 => 'ciento', 2 => 'doscientos', 3 => 'trescientos', 4 => 'cuatrocientos',
        5 => 'quinientos', 6 => 'seiscientos', 7 => 'setecientos',
        8 => 'ochocientos', 9 => 'novecientos',
    ];

    /**
     * «Mil ochocientos setenta y cinco quetzales con 00/100».
     */
    public static function enLetras(float $monto, string $moneda = 'quetzales', string $monedaSingular = 'quetzal'): string
    {
        $monto = round(abs($monto), 2);
        $entero = (int) floor($monto);
        $centavos = (int) round(($monto - $entero) * 100);

        // «un quetzal» y «veintiún quetzales», no «uno quetzal»: delante del
        // nombre de la moneda el uno se apocopa.
        $letras = $entero === 0 ? 'cero' : self::apocopar(self::numero($entero));
        $nombre = $entero === 1 ? $monedaSingular : $moneda;

        // «un millón de quetzales», pero «un millón doscientos mil quetzales»:
        // el «de» solo aparece cuando el millón va pegado al nombre.
        if (str_ends_with($letras, 'millón') || str_ends_with($letras, 'millones')) {
            $nombre = 'de '.$nombre;
        }

        return ucfirst(trim($letras)).' '.$nombre.' con '.str_pad((string) $centavos, 2, '0', STR_PAD_LEFT).'/100';
    }

    /**
     * El uno pierde la o delante de un sustantivo masculino: «un quetzal»,
     * «veintiún quetzales», «doscientos un quetzales».
     */
    private static function apocopar(string $texto): string
    {
        if (str_ends_with($texto, 'veintiuno')) {
            return substr($texto, 0, -9).'veintiún';
        }

        if ($texto === 'uno' || str_ends_with($texto, ' uno')) {
            return substr($texto, 0, -3).'un';
        }

        return $texto;
    }

    private static function numero(int $n): string
    {
        if ($n >= 1_000_000) {
            $millones = intdiv($n, 1_000_000);
            $resto = $n % 1_000_000;

            $texto = $millones === 1 ? 'un millón' : self::numero($millones).' millones';

            return $resto > 0 ? $texto.' '.self::numero($resto) : $texto;
        }

        if ($n >= 1000) {
            $miles = intdiv($n, 1000);
            $resto = $n % 1000;

            // «mil» y no «uno mil»: el uno se calla delante de mil, y
            // «veintiún mil» y no «veintiuno mil».
            $texto = $miles === 1 ? 'mil' : self::apocopar(self::numero($miles)).' mil';

            return $resto > 0 ? $texto.' '.self::numero($resto) : $texto;
        }

        if ($n === 100) {
            return 'cien';
        }

        if ($n > 100) {
            $centena = self::CENTENAS[intdiv($n, 100)];
            $resto = $n % 100;

            return $resto > 0 ? $centena.' '.self::numero($resto) : $centena;
        }

        if ($n < 30) {
            return self::UNIDADES[$n];
        }

        $decena = self::DECENAS[intdiv($n, 10)];
        $unidad = $n % 10;

        return $unidad > 0 ? $decena.' y '.self::UNIDADES[$unidad] : $decena;
    }
}
