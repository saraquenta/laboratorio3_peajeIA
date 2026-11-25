@extends('layouts.app')
@section('titulo', 'Tarifas por categoría')
@section('contenido')
<div class="caja tabla-scroll">
<p style="color:var(--suave);margin-top:0">Tarifa = monto base + (ejes detectados − ejes incluidos) × monto por eje adicional. Las categorías vienen de M2 (segmentación) y los ejes de M3 (keypoints de llantas).</p>
<table>
    <thead><tr><th>Categoría</th><th>Monto base (Bs)</th><th>Ejes incluidos</th><th>Bs por eje adicional</th><th>Ejemplo 3 ejes</th><th></th></tr></thead>
    <tbody>
    @foreach($tarifas as $t)
        <tr>
            <td><strong>{{ \App\Models\Tarifa::NOMBRES[$t->categoria] }}</strong></td>
            <td><input form="f-t-{{ $t->id }}" type="number" step="0.5" min="0" name="monto_base" value="{{ $t->monto_base }}" style="width:110px"></td>
            <td><input form="f-t-{{ $t->id }}" type="number" min="2" max="9" name="ejes_incluidos" value="{{ $t->ejes_incluidos }}" style="width:80px"></td>
            <td><input form="f-t-{{ $t->id }}" type="number" step="0.5" min="0" name="monto_eje_adicional" value="{{ $t->monto_eje_adicional }}" style="width:110px"></td>
            <td class="num">Bs {{ number_format($t->calcular(3), 2) }}</td>
            <td><form id="f-t-{{ $t->id }}" method="post" action="{{ route('admin.tarifas.update', $t) }}">@csrf @method('put')<button class="btn chico">Guardar</button></form></td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endsection
