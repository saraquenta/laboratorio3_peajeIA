@extends('layouts.app')
@section('titulo', 'Panel en tiempo real')
@section('acciones')
    <small style="color:var(--suave)"><span id="modo-rt">{{ $pusher ? 'Tiempo real: conectando a Pusher…' : 'Tiempo real: sondeo cada 3 s' }}</span> · <span id="actualizado"></span></small>
@endsection
@section('contenido')
@php($c = $datos['contadores'])
<div class="tarjetas">
    <div class="tarjeta"><small>Pasos hoy</small><strong id="c-pasos">{{ $c['pasos_hoy'] }}</strong></div>
    <div class="tarjeta"><small>Recaudación hoy</small><strong id="c-recaudacion">Bs {{ number_format($c['recaudacion_hoy'], 2) }}</strong></div>
    <div class="tarjeta"><small>Alertas pendientes</small><strong id="c-alertas" style="color:var(--mal)">{{ $c['alertas_pendientes'] }}</strong></div>
    <div class="tarjeta"><small>Pasajeros hoy</small><strong id="c-pasajeros">{{ $c['pasajeros_hoy'] }}</strong></div>
    <div class="tarjeta"><small>Recaudación total</small><strong id="c-total">Bs {{ number_format($c['recaudacion_total'], 2) }}</strong></div>
</div>
<div class="rejilla">
    <div class="caja">
        <h2>Pasos recientes <a href="{{ route('pasos.index') }}" style="font-size:13px">ver historial →</a></h2>
        <div class="tabla-scroll"><table>
            <thead><tr><th>Hora</th><th>Placa</th><th>Categoría</th><th class="num">Ejes</th><th class="num">Pasaj.</th><th class="num">Monto</th><th>Estado</th><th>Caseta</th><th>Evidencia</th></tr></thead>
            <tbody id="tabla-pasos"><tr><td colspan="9" class="vacio">Cargando…</td></tr></tbody>
        </table></div>
    </div>
    <div style="display:grid;gap:16px;align-content:start">
        <div class="caja">
            <h2>Alertas <a href="{{ route('alertas.index') }}" style="font-size:13px">gestionar →</a></h2>
            <div class="lista-alertas" id="lista-alertas"></div>
        </div>
        <div class="caja">
            <h2>Hoy por caseta</h2>
            <table><thead><tr><th>Caseta</th><th class="num">Pasos</th><th class="num">Recaudado</th></tr></thead><tbody id="por-caseta"></tbody></table>
        </div>
        <div class="caja">
            <h2>Probar con una foto</h2>
            <form method="post" action="{{ route('panel.simular') }}" enctype="multipart/form-data" class="form">
                @csrf
                <select name="caseta_id">@foreach($casetas as $cs)<option value="{{ $cs->id }}">{{ $cs->nombre }}</option>@endforeach</select>
                <input type="file" name="imagen" accept="image/*" required>
                <button class="btn">Procesar como paso</button>
                <small style="color:var(--suave)">Envía la imagen al endpoint de inferencia (INFERENCE_URL), igual que la app móvil.</small>
            </form>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>window.PANEL = { ultimoId: 0, ultimaAlerta: {{ $datos['ultima_alerta_id'] }}, pusher: @json($pusher) };</script>
<script src="{{ asset('js/panel.js') }}?v=1"></script>
@endpush
