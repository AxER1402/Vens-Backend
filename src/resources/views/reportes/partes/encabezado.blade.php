{{--
    Membrete del informe.

    mPDF lo repite en todas las páginas, así que va aparte del cuerpo: una hoja
    suelta de un expediente tiene que poder identificarse sin la primera página.

    El logo se lee del disco local (public/img). Pasarle una URL a mPDF provoca
    una petición HTTP que además falla si APP_URL no resuelve desde el contenedor.

    El ancho del logo va como atributo del <img> y no en la hoja de estilo: mPDF
    no aplica de forma fiable un selector descendente a una imagen dentro de una
    celda, y sin ancho explícito la pinta a su tamaño intrínseco —el isotipo mide
    2816 px— y se come media página.
--}}
<table class="membrete" width="100%">
    <tr>
        @if ($logo)
            <td class="membrete-logo" width="32%">
                <img src="{{ $logo }}" width="132" alt="">
            </td>
        @endif
        <td class="membrete-datos" width="{{ $logo ? 42 : 74 }}%">
            <div class="membrete-nombre">{{ $centro['nombre'] }}</div>
            @if ($centro['especialidad'])
                <div class="membrete-especialidad">{{ $centro['especialidad'] }}</div>
            @endif
        </td>
        <td class="membrete-contacto" width="26%">
            @if ($centro['direccion'])<div>{{ $centro['direccion'] }}</div>@endif
            @if ($centro['telefono'])<div>Tel. {{ $centro['telefono'] }}</div>@endif
            @if ($centro['correo'])<div>{{ $centro['correo'] }}</div>@endif
        </td>
    </tr>
</table>
<div class="membrete-regla"></div>
