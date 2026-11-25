@extends('layouts.app')
@section('titulo', 'Historial de pasos')
@section('contenido')
<form class="filtros" method="get">
    <input type="search" name="placa" value="{{ request('placa') }}" placeholder="Placa">
    <select name="caseta_id"><option value="">Todas las casetas</option>
        @foreach($casetas as $c)<option value="{{ $c->id }}" @selected(request('caseta_id') == $c->id)>{{ $c->nombre }}</option>@endforeach</select>
    <select name="estado"><option value="">Todos</option><option value="cobrado" @selected(request('estado') === 'cobrado')>Cobrado</option><option value="alerta" @selected(request('estado') === 'alerta')>Alerta</option></select>
    <input type="date" name="fecha" value="{{ request('fecha') }}">
    <button class="btn sec">Filtrar</button>
</form>
<div class="caja tabla-scroll">
<table>
    <thead><tr><th>Fecha</th><th>Evidencia</th><th>Placa</th><th>Categoría</th><th class="num">Ejes</th><th class="num">Pasajeros</th><th class="num">OCR</th><th class="num">Monto</th><th>Estado</th><th>Caseta</th></tr></thead>
    <tbody>
    @forelse($pasos as $p)
        <tr>
            <td style="white-space:nowrap">{{ $p->created_at->format('d/m/Y H:i:s') }}</td>
            <td>@if($p->imagen_url)<a href="{{ route('pasos.show', $p) }}"><img class="miniatura" src="{{ $p->imagen_url }}" alt="evidencia" loading="lazy"></a>@endif</td>
            <td><a href="{{ route('pasos.show', $p) }}"><span class="placa">{{ $p->placa ?? '—' }}</span></a><br><small>{{ $p->vehiculo->propietario ?? 'No registrada' }}</small></td>
            <td>{{ $p->categoria }}</td>
            <td class="num">{{ $p->ejes }}</td>
            <td class="num">{{ $p->pasajeros }}</td>
            <td class="num">{{ $p->confianza_ocr !== null ? number_format($p->confianza_ocr * 100, 0).'%' : '' }}</td>
            <td class="num">Bs {{ number_format($p->monto, 2) }}</td>
            <td>{!! $p->estado === 'cobrado' ? '<span class="etq ok">Cobrado</span>' : '<span class="etq mal">Alerta</span>' !!}</td>
            <td>{{ $p->caseta->nombre ?? '' }}</td>
        </tr>
    @empty
        <tr><td colspan="10" class="vacio">Sin pasos registrados.</td></tr>
    @endforelse
    </tbody>
</table>
<div class="paginacion">{{ $pasos->links('paginacion') }}</div>
</div>
@endsection
