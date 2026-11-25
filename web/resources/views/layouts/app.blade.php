<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Panel') · Peaje Inteligente</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
</head>
<body>
<div class="app">
    <aside class="lateral">
        <div class="marca"><span class="logo">🛣️</span><div><strong>Peaje Inteligente</strong><small>IAM-811 · Hito final</small></div></div>
        <nav>
            @php($r = request()->route()?->getName() ?? '')
            <a href="{{ route('panel') }}" class="{{ str_starts_with($r, 'panel') ? 'activo' : '' }}">📊 Panel en vivo</a>
            <a href="{{ route('vehiculos.index') }}" class="{{ str_starts_with($r, 'vehiculos') ? 'activo' : '' }}">🚗 Vehículos</a>
            <a href="{{ route('pasos.index') }}" class="{{ str_starts_with($r, 'pasos') ? 'activo' : '' }}">🧾 Pasos</a>
            <a href="{{ route('alertas.index') }}" class="{{ str_starts_with($r, 'alertas') ? 'activo' : '' }}">🚨 Alertas <span class="pildora" id="nav-alertas"></span></a>
            <a href="{{ route('chat') }}" class="{{ $r === 'chat' ? 'activo' : '' }}">💬 Agente</a>
            <a href="{{ route('estado') }}" class="{{ str_starts_with($r, 'estado') ? 'activo' : '' }}">🩺 Estado</a>
            @if(auth()->user()->esAdmin())
                <div class="sep">Administración</div>
                <a href="{{ route('admin.tarifas') }}" class="{{ $r === 'admin.tarifas' ? 'activo' : '' }}">💲 Tarifas</a>
                <a href="{{ route('admin.casetas') }}" class="{{ $r === 'admin.casetas' ? 'activo' : '' }}">🏁 Casetas</a>
                <a href="{{ route('admin.usuarios') }}" class="{{ $r === 'admin.usuarios' ? 'activo' : '' }}">👥 Lista blanca</a>
            @endif
        </nav>
        <div class="usuario">
            @if(auth()->user()->avatar_url)<img src="{{ auth()->user()->avatar_url }}" alt="" referrerpolicy="no-referrer">@endif
            <div><strong>{{ auth()->user()->nombre ?? auth()->user()->email_google }}</strong><small>{{ auth()->user()->rol }}</small></div>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="btn-link" title="Cerrar sesión">⏻</button></form>
        </div>
    </aside>
    <main class="contenido">
        <header class="cabecera"><h1>@yield('titulo')</h1><div>@yield('acciones')</div></header>
        @if(session('ok'))<div class="aviso ok">{{ session('ok') }}</div>@endif
        @if(session('error'))<div class="aviso error">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="aviso error">{{ implode(' · ', $errors->all()) }}</div>@endif
        @yield('contenido')
    </main>
</div>

{{-- Burbuja del agente conversacional (disponible en todas las paginas para usuarios autenticados) --}}
@if(($r ?? '') !== 'chat')
<button id="burbuja" class="burbuja" title="Agente de placas">💬</button>
<div id="burbuja-panel" class="burbuja-panel oculto">
    <div class="burbuja-cab"><strong>Agente de placas</strong><button class="btn-link" id="burbuja-cerrar">✕</button></div>
    <div class="chat-mensajes" data-chat="mensajes"></div>
    <form class="chat-form" data-chat="form">
        <label class="adjuntar" title="Foto de placa">📷<input type="file" accept="image/*" data-chat="imagen" hidden></label>
        <input type="text" data-chat="texto" placeholder="Ej: 1234ABC" autocomplete="off">
        <button class="btn">Enviar</button>
    </form>
</div>
@endif
<script>window.PEAJE = { chatUrl: @json(route('chat.mensaje')), datosUrl: @json(route('panel.datos')) };</script>
<script src="{{ asset('js/chat.js') }}?v=1"></script>
@stack('scripts')
</body>
</html>
