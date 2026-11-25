@extends('layouts.app')
@section('titulo', 'Casetas')
@section('contenido')
<div class="rejilla">
<div class="caja tabla-scroll">
<table>
    <thead><tr><th>ID</th><th>Código</th><th>Nombre</th><th>Ubicación</th><th>Activa</th><th class="num">Pasos</th><th></th></tr></thead>
    <tbody>
    @foreach($casetas as $c)
        <tr>
            <td>{{ $c->id }}</td>
            <td><input form="f-c-{{ $c->id }}" name="codigo" value="{{ $c->codigo }}" style="width:90px"></td>
            <td><input form="f-c-{{ $c->id }}" name="nombre" value="{{ $c->nombre }}"></td>
            <td><input form="f-c-{{ $c->id }}" name="ubicacion" value="{{ $c->ubicacion }}"></td>
            <td><input form="f-c-{{ $c->id }}" type="checkbox" name="activa" value="1" @checked($c->activa)></td>
            <td class="num">{{ $c->pasos_count }}</td>
            <td><form id="f-c-{{ $c->id }}" method="post" action="{{ route('admin.casetas.update', $c) }}">@csrf @method('put')<button class="btn chico">Guardar</button></form></td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
<div class="caja">
    <h2>Nueva caseta</h2>
    <form method="post" action="{{ route('admin.casetas.store') }}" class="form">@csrf
        <input name="codigo" placeholder="Código (C-03)" required>
        <input name="nombre" placeholder="Nombre" required>
        <input name="ubicacion" placeholder="Ubicación">
        <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="activa" value="1" checked> Activa</label>
        <button class="btn">Crear</button>
    </form>
</div>
</div>
@endsection
