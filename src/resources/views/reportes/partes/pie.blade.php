{{--
    Pie de página. La numeración "X de Y" es lo que permite darse cuenta de que
    a un expediente impreso le falta una hoja.
--}}
<div class="pie">
    <table width="100%">
        <tr>
            <td class="pie-izq">{{ $centro['nombre'] }} · {{ $titulo }}</td>
            <td class="pie-der">Página {PAGENO} de {nbpg}</td>
        </tr>
    </table>
</div>
