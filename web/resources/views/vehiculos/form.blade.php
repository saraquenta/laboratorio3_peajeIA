@extends('layouts.app')
@section('titulo', $vehiculo->exists ? 'Editar vehículo '.$vehiculo->placa : 'Nuevo vehículo')
@section('contenido')
<div class="caja">
<form method="post" action="{{ $vehiculo->exists ? route('vehiculos.update', $vehiculo) : route('vehiculos.store') }}" class="form">
    @csrf
    @if($vehiculo->exists) @method('put') @endif
    <label>Placa (formato boliviano, se normaliza: «1234-abc» → 1234ABC)
        <input name="placa" value="{{ old('placa', $vehiculo->placa) }}" required maxlength="10" style="text-transform:uppercase"></label>
    <label>Propietario <input name="propietario" value="{{ old('propietario', $vehiculo->propietario) }}" required></label>
    <label>Categoría
        <select name="categoria">@foreach($categorias as $k => $n)<option value="{{ $k }}" @selected(old('categoria', $vehiculo->categoria) === $k)>{{ $n }}</option>@endforeach</select></label>
    <label>Ejes <input type="number" name="ejes" min="2" max="9" value="{{ old('ejes', $vehiculo->ejes) }}" required></label>
    <label>Saldo (Bs) <input type="number" step="0.01" min="0" name="saldo" value="{{ old('saldo', $vehiculo->saldo) }}" required></label>
    <label style="flex-direction:row;display:flex;gap:8px;align-items:center"><input type="checkbox" name="activo" value="1" @checked(old('activo', $vehiculo->activo))> Activo</label>
    <div><button class="btn">Guardar</button> <a class="btn sec" href="{{ route('vehiculos.index') }}">Cancelar</a></div>
</form>
</div>
@endsection
