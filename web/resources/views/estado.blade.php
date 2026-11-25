@extends('layouts.app')
@section('titulo', 'Estado del sistema')
@section('acciones')<button class="btn sec" id="verificar">↻ Verificar ahora</button>@endsection
@section('contenido')
@php($inf = $inferencia)
<div class="tarjetas">
    <div class="tarjeta"><small>Inferencia (INFERENCE_URL)</small>
        <strong id="inf-estado"><span class="punto {{ $inf['en_linea'] ? 'on' : 'off' }}"></span>{{ $inf['en_linea'] ? 'En línea' : 'Sin respuesta' }}</strong>
        <small id="inf-lat">{{ $inf['latencia_ms'] ? $inf['latencia_ms'].' ms' : '' }}</small></div>
    <div class="tarjeta"><small>Base de datos</small><strong><span class="punto {{ $bd ? 'on' : 'off' }}"></span>{{ $bd ? 'OK' : 'Error' }}</strong><small>{{ config('database.default') }}</small></div>
    <div class="tarjeta"><small>Tiempo real</small><strong style="font-size:16px">{{ $tiempoReal }}</strong></div>
    <div class="tarjeta"><small>Google OAuth</small><strong style="font-size:16px"><span class="punto {{ $google ? 'on' : 'off' }}"></span>{{ $google ? 'Configurado' : 'Falta configurar' }}</strong></div>
    <div class="tarjeta"><small>Agente</small><strong style="font-size:16px">{{ $agenteLlm ? 'BD + '.$agenteLlm : 'BD + plantillas (determinista)' }}</strong></div>
    @php($conectado = $esp32 && now()->diffInSeconds($esp32['fecha'], true) < 15)
    <div class="tarjeta"><small>Hardware (ESP32)</small>
        <strong style="font-size:16px"><span class="punto {{ $conectado ? 'on' : 'off' }}"></span>{{ ! $hardware ? 'Sin HARDWARE_TOKEN' : ($conectado ? 'Conectado' : 'Sin conexión') }}</strong>
        <small>@if($esp32)Última consulta {{ \Illuminate\Support\Carbon::parse($esp32['fecha'])->diffForHumans() }}{{ $esp32['rssi'] !== null ? ' · RSSI '.$esp32['rssi'].' dBm' : '' }}@endif</small></div>
</div>
<div class="rejilla">
    <div class="caja">
        <h2>Servicio de inferencia</h2>
        <dl class="dl">
            <dt>URL</dt><dd><code id="inf-url">{{ $inf['url'] ?: '(no configurada)' }}</code> <small>origen: {{ $origenUrl }}</small></dd>
            <dt>Token</dt><dd>{{ $tokenConfigurado ? 'configurado' : 'no configurado' }}</dd>
        </dl>
        <details open style="margin-top:12px"><summary>Respuesta de /health</summary>
            <pre id="inf-detalle" style="white-space:pre-wrap;font-size:12px">{{ is_string($inf['detalle']) ? $inf['detalle'] : json_encode($inf['detalle'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details>
    </div>
    @if(auth()->user()->esAdmin())
    <div class="caja">
        <h2>Configurar inferencia</h2>
        <form method="post" action="{{ route('estado.inferencia') }}" class="form">
            @csrf
            <label>INFERENCE_URL (RunPod o Colab)<input type="url" name="inference_url" value="{{ $inf['url'] }}" placeholder="https://POD_ID-8000.proxy.runpod.net"></label>
            <label>INFERENCE_TOKEN (vacío = no cambiar)<input type="password" name="inference_token" autocomplete="off"></label>
            <button class="btn">Guardar</button>
            <small style="color:var(--suave)">Se guarda en la base de datos y tiene prioridad sobre el .env. Útil cuando cambia la URL de Colab o del Pod.</small>
        </form>
    </div>
    @endif
</div>
@endsection
@push('scripts')
<script>
document.getElementById('verificar').onclick = async function () {
    this.disabled = true;
    try {
        const r = await fetch(@json(route('estado.verificar')), { headers: { Accept: 'application/json' } });
        const j = await r.json();
        document.getElementById('inf-estado').innerHTML = '<span class="punto ' + (j.en_linea ? 'on' : 'off') + '"></span>' + (j.en_linea ? 'En línea' : 'Sin respuesta');
        document.getElementById('inf-lat').textContent = j.latencia_ms ? j.latencia_ms + ' ms' : '';
        document.getElementById('inf-detalle').textContent = typeof j.detalle === 'string' ? j.detalle : JSON.stringify(j.detalle, null, 2);
    } finally { this.disabled = false; }
};
</script>
@endpush
