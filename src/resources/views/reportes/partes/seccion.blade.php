{{--
    Una sección del informe. Se separa del cuerpo porque las secciones anidan:
    una sección de campos puede colgar de sí misma una tabla (`extra`) para no
    repetir el epígrafe, y el anexo del mapeo venoso cuelga varios bloques de la
    lámina.
--}}
@php
    $tipo = $seccion['tipo'] ?? 'campos';

    /* Una tabla corta viaja entera con su epígrafe: partirla deja el título al
       final de una página y dos filas sueltas al principio de la siguiente, y de
       paso empuja el resto del informe a hojas casi vacías. Las tablas largas sí
       pueden partirse —mPDF repite el <thead> en cada página—, porque forzarlas
       a caber sería peor. */
    $compacta = $tipo === 'tabla' && count($seccion['filas'] ?? []) <= 8;
@endphp

@if (!empty($seccion['salto']))
    <pagebreak />
@endif

<div class="seccion @if ($tipo === 'campos' || $compacta) seccion-campos @endif">
    @if (!empty($seccion['titulo']) && $tipo !== 'anexo')
        <div class="seccion-titulo">{{ $seccion['titulo'] }}</div>
    @endif

    @if ($tipo === 'campos')
        @php $filas = array_chunk($seccion['campos'], 2, true); @endphp
        <table class="campos">
            @foreach ($filas as $fila)
                <tr>
                    @foreach ($fila as $etiqueta => $valor)
                        <td class="et">{{ $etiqueta }}</td>
                        <td class="va">{{ $valor }}</td>
                    @endforeach
                    @for ($i = count($fila); $i < 2; $i++)
                        <td class="et"></td><td class="va"></td>
                    @endfor
                </tr>
            @endforeach
        </table>

    @elseif ($tipo === 'tabla')
        <table class="datos">
            <thead>
                <tr>
                    @foreach ($seccion['encabezados'] as $i => $encabezado)
                        <th @if (!empty($seccion['anchos'][$i])) width="{{ $seccion['anchos'][$i] }}%" @endif>{{ $encabezado }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($seccion['filas'] as $n => $fila)
                    <tr class="{{ $n % 2 ? 'par' : '' }}">
                        @foreach ($fila as $celda)
                            <td>{{ $celda }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>

    @elseif ($tipo === 'anexo')
        {{-- Portada de anexo: marca dónde termina la consulta y empieza lo que
             se le adjunta, para que un paquete de varias partes no se lea como
             un solo documento corrido. --}}
        <div class="anexo-portada">{{ $seccion['titulo'] }}</div>

    @elseif ($tipo === 'texto')
        <div class="texto">{{ $seccion['texto'] }}</div>

    @elseif ($tipo === 'imagen')
        @if (!empty($seccion['ruta']))
            <div class="lamina"><img src="{{ $seccion['ruta'] }}" alt=""></div>
        @endif
        @if (!empty($seccion['pie']))
            <div class="lamina-pie">{{ $seccion['pie'] }}</div>
        @endif
    @endif
</div>

{{-- Tabla que se cuelga de una sección de campos sin repetir el epígrafe --}}
@if (!empty($seccion['extra']))
    @include('reportes.partes.seccion', ['seccion' => $seccion['extra']])
@endif

{{-- Bloques que acompañan a una lámina (leyenda, hallazgos, anotaciones) --}}
@foreach ($seccion['bloques'] ?? [] as $bloque)
    @include('reportes.partes.seccion', ['seccion' => $bloque])
@endforeach
