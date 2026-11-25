<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso denegado · Peaje Inteligente</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
</head>
<body>
<div class="login">
    <div class="caja">
        <div style="font-size:44px">⛔</div>
        <h1>Acceso denegado</h1>
        <p>La cuenta <strong>{{ $email ?: 'de Google' }}</strong> no está autorizada en la lista blanca del sistema o fue desactivada.</p>
        <p style="color:var(--suave)">Pide a un administrador que agregue tu correo en <em>Administración → Lista blanca</em>.</p>
        <a class="btn" href="{{ route('login') }}">Volver</a>
    </div>
</div>
</body>
</html>
