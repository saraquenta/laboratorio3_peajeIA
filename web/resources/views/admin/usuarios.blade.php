@extends('layouts.app')
@section('titulo', 'Lista blanca (Google)')
@section('contenido')
<div class="rejilla">
<div class="caja tabla-scroll">
<table>
    <thead><tr><th>Correo de Google</th><th>Nombre</th><th>Rol</th><th>Activo</th><th>Último acceso</th><th></th></tr></thead>
    <tbody>
    @foreach($usuarios as $u)
        <tr>
            <td><input form="f-u-{{ $u->id }}" type="email" name="email_google" value="{{ $u->email_google }}" style="min-width:240px"></td>
            <td><input form="f-u-{{ $u->id }}" name="nombre" value="{{ $u->nombre }}"></td>
            <td><select form="f-u-{{ $u->id }}" name="rol"><option value="administrador" @selected($u->rol === 'administrador')>administrador</option><option value="operador" @selected($u->rol === 'operador')>operador</option></select></td>
            <td><input form="f-u-{{ $u->id }}" type="checkbox" name="activo" value="1" @checked($u->activo)></td>
            <td><small>{{ $u->ultimo_acceso?->format('d/m/Y H:i') ?? 'nunca' }}</small></td>
            <td><form id="f-u-{{ $u->id }}" method="post" action="{{ route('admin.usuarios.update', $u) }}">@csrf @method('put')<button class="btn chico">Guardar</button></form></td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
<div class="caja">
    <h2>Autorizar correo</h2>
    <form method="post" action="{{ route('admin.usuarios.store') }}" class="form">@csrf
        <input type="email" name="email_google" placeholder="correo@gmail.com" required>
        <input name="nombre" placeholder="Nombre (opcional)">
        <select name="rol"><option value="operador">operador</option><option value="administrador">administrador</option></select>
        <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="activo" value="1" checked> Activo</label>
        <button class="btn">Agregar a la lista blanca</button>
        <small style="color:var(--suave)">El usuario entra con «Ingresar con Google». Si su correo no está aquí o está inactivo, verá «Acceso denegado».</small>
    </form>
</div>
</div>
@endsection
