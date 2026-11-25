@extends('layouts.app')
@section('titulo', 'Vehículos registrados')
@section('acciones')
    @if(auth()->user()->esAdmin())<a class="btn" href="{{ route('vehiculos.create') }}">+ Nuevo vehículo</a>@endif
@endsection
@section('contenido')
<form class="filtros" method="get">
    <input type="search" name="buscar" value="{{ request('buscar') }}" placeholder="Placa o propietario">
    <select name="categoria"><option value="">Todas las categorías</option>
        @foreach($categorias as $k => $n)<option value="{{ $k }}" @selected(request('categoria') === $k)>{{ $n }}</option>@endforeach
    </select>
    <button class="btn sec">Filtrar</button>
</form>
<div class="caja tabla-scroll">
<table>
    <thead><tr><th>Placa</th><th>Propietario</th><th>Categoría</th><th class="num">Ejes</th><th class="num">Saldo</th><th class="num">Pasos</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    @forelse($vehiculos as $v)
        <tr>
            <td><a href="{{ route('pasos.index', ['placa' => $v->placa]) }}"><span class="placa">{{ $v->placa }}</span></a></td>
            <td>{{ $v->propietario }}</td>
            <td>{{ $categorias[$v->categoria] ?? $v->categoria }}</td>
            <td class="num">{{ $v->ejes }}</td>
            <td class="num" style="{{ $v->saldo < 10 ? 'color:var(--mal)' : '' }}">Bs {{ number_format($v->saldo, 2) }}</td>
            <td class="num">{{ $v->pasos_count }}</td>
            <td>{!! $v->activo ? '<span class="etq ok">Activo</span>' : '<span class="etq mal">Inactivo</span>' !!}</td>
            <td style="white-space:nowrap">
                @if(auth()->user()->esAdmin())
                    <a class="btn chico sec" href="{{ route('vehiculos.edit', $v) }}">Editar</a>
                    <form method="post" action="{{ route('vehiculos.destroy', $v) }}" style="display:inline" onsubmit="return confirm('¿Eliminar {{ $v->placa }}? Sus pasos se conservan.')">
                        @csrf @method('delete')<button class="btn chico peligro">Eliminar</button>
                    </form>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="8" class="vacio">No hay vehículos que coincidan.</td></tr>
    @endforelse
    </tbody>
</table>
<div class="paginacion">{{ $vehiculos->links('paginacion') }}</div>
</div>
@endsection
