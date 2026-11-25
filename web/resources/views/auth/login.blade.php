<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · Peaje Inteligente</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
</head>
<body>
<div class="login">
    <div class="caja">
        <div style="font-size:44px">🛣️</div>
        <h1 style="margin:8px 0 4px">Peaje Inteligente</h1>
        <p style="color:var(--suave);margin-top:0">Sistema de control de peaje · IAM-811</p>
        @if(session('error'))<div class="aviso error">{{ session('error') }}</div>@endif
        <a class="google" href="{{ route('google.redirect') }}">
            <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.6 5.4 2.7 13.3l7.9 6.1C12.5 13.6 17.8 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.7 6c4.5-4.2 6.9-10.3 6.9-17.7z"/><path fill="#FBBC05" d="M10.6 28.6c-.5-1.4-.8-3-.8-4.6s.3-3.2.8-4.6l-7.9-6.1C1 16.6 0 20.2 0 24s1 7.4 2.7 10.7l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.7-6c-2.2 1.5-5 2.3-8.2 2.3-6.2 0-11.5-4.1-13.4-9.9l-7.9 6.1C6.6 42.6 14.6 48 24 48z"/></svg>
            Ingresar con Google
        </a>
        @unless($googleConfigurado)
            <p style="color:var(--aviso);font-size:13px">Falta configurar GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET en el .env.</p>
        @endunless
        <p style="color:var(--suave);font-size:13px;margin-top:18px">Solo cuentas autorizadas en la lista blanca. No existe inicio de sesión con contraseña.</p>
        @if($devLogin)
            <hr style="border-color:var(--borde);margin:18px 0">
            <form method="post" action="{{ route('dev.login') }}" class="form" style="margin:auto">
                @csrf
                <small style="color:var(--aviso)">Modo desarrollo local (APP_ENV=local, AUTH_DEV_LOGIN=true). Deshabilitado en producción.</small>
                <input type="email" name="email" placeholder="correo de la lista blanca" required>
                <button class="btn sec">Entrar (solo pruebas locales)</button>
            </form>
        @endif
    </div>
</div>
</body>
</html>
