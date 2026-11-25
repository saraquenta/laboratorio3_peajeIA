@extends('layouts.app')
@section('titulo', 'Alertas')
@section('contenido')
<form class="filtros" method="get">
    <select name="estado">
        @foreach(['pendiente' => 'Pendientes', 'atendida' => 'Atendidas', 'descartada' => 'Descartadas', 'todas' => 'Todas'] as $k => $n)
            <option value="{{ $k }}" @selected($estado === $k)>{{ $n }}</option>@endforeach
    </select>
    <select name="tipo"><option value="">Todos los tipos</option>
        @foreach($tipos as $k => $n)<option value="{{ $k }}" @selected(request('tipo') === $k)>{{ $n }}</option>@endforeach</select>
    <button class="btn sec">Filtrar</button>
</form>
<div class="caja tabla-scroll">
<table>
    <thead><tr><th>Fecha</th><th>Tipo</th><th>Placa</th><th>Detalle</th><th>Caseta</th><th>Evidencia</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
    @forelse($alertas as $a)
        <tr>
            <td style="white-space:nowrap">{{ $a->created_at->format('d/m H:i') }}</td>
            <td><span class="etq {{ in_array($a->tipo, ['no_registrada', 'vehiculo_inactivo']) ? 'mal' : 'aviso' }}">{{ $tipos[$a->tipo] ?? $a->tipo }}</span></td>
            <td><span class="placa">{{ $a->placa ?? '—' }}</span></td>
            <td>{{ $a->mensaje }}</td>
            <td>{{ $a->caseta->nombre ?? '' }}</td>
            <td>@if($a->paso?->imagen_url)<a href="{{ route('pasos.show', $a->paso_id) }}"><img class="miniatura" src="{{ $a->paso->imagen_url }}" alt=""></a>@endif</td>
            <td>{{ $a->estado }} @if($a->atendidaPor)<br><small>{{ $a->atendidaPor->email_google }}</small>@endif</td>
            <td style="white-space:nowrap">
                @foreach(['atendida' => 'Atender', 'descartada' => 'Descartar', 'pendiente' => 'Reabrir'] as $e => $txt)
                    @if($a->estado !== $e)
                    <form method="post" action="{{ route('alertas.update', $a) }}" style="display:inline">@csrf @method('patch')
                        <input type="hidden" name="estado" value="{{ $e }}"><button class="btn chico {{ $e === 'atendida' ? '' : 'sec' }}">{{ $txt }}</button></form>
                    @endif
                @endforeach
            </td>
        </tr>
    @empty
        <tr><td colspan="8" class="vacio">Sin alertas.</td></tr>
    @endforelse
    </tbody>
</table>
<div class="paginacion">{{ $alertas->links('paginacion') }}</div>
</div>
@endsection
