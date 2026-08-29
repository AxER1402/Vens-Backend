<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\MapeoVenoso\Catalogo;
use Illuminate\Http\JsonResponse;

class VenousMapCatalogController extends Controller
{
    /**
     * Entregar el catálogo clínico del mapeo venoso: los tres ejes con los que
     * se dibuja —color, trayecto y marcador—, las zonas anatómicas y los datos
     * de la plantilla.
     *
     * El editor lo consume para construir su barra de herramientas y su leyenda,
     * y el backend lo usa para validar el documento vectorial y para redactar el
     * reporte. Servirlo desde aquí es lo que evita que el catálogo se bifurque en
     * dos copias que acaben discrepando: si el editor pudiera ofrecer un hallazgo
     * que el backend no conoce, ese hallazgo se guardaría y el informe lo
     * imprimiría como un código sin nombre.
     *
     * El vocabulario anterior (`hallazgos`) no se publica: el editor no debe
     * volver a ofrecerlo. El backend lo sigue leyendo para poder reabrir e
     * imprimir los mapeos archivados con él.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'plantilla' => Catalogo::plantilla(),
                'miembros' => Catalogo::miembros(),
                'zonas' => Catalogo::zonas(),

                // Los tres ejes del dibujo. El color es la lectura clínica del
                // vaso, el trayecto el recorrido que se traza —los dos troncos
                // safenos y los patrones de la lámina— y el marcador el
                // símbolo de un hallazgo puntual. Cada entrada
                // viaja con su `ayuda`, que es el texto que el editor muestra
                // al pasar el puntero por encima, y con la muestra o el símbolo
                // ya dibujados para que la barra de herramientas y la lámina se
                // vean igual sin reimplementarlos en el cliente.
                'colores' => Catalogo::colores(),
                'trayectos' => $this->trayectos(),
                'marcadores' => Catalogo::marcadores(),

                'grosores' => Catalogo::grosores(),
                'limites' => config('mapeo-venoso.limites'),
                'versiones' => Catalogo::versiones(),
            ],
        ], 200);
    }

    /**
     * Los trayectos, con `parametros` siempre como objeto.
     *
     * Un array vacío de PHP se serializa como `[]` y no como `{}`, así que los
     * trayectos que no llevan parámetros llegarían al cliente con una lista
     * donde el resto trae un diccionario. Es la clase de incoherencia que
     * revienta en el cliente meses después y no aquí.
     *
     * @return array<int, array<string, mixed>>
     */
    private function trayectos(): array
    {
        return array_map(
            fn (array $trayecto) => [...$trayecto, 'parametros' => (object) $trayecto['parametros']],
            Catalogo::trayectos()
        );
    }
}
