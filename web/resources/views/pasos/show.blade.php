@extends('layouts.app')
@section('titulo', 'Paso #'.$paso->id)
@section('acciones')<a class="btn sec" href="{{ route('pasos.index') }}">← Volver</a>@endsection
@section('contenido')
<div class="rejilla">
    <div class="caja">
        <h2>Imagen de evidencia</h2>
        @if($paso->imagen_url)<img class="evidencia" src="{{ $paso->imagen_url }}" alt="Evidencia del paso">@else<div class="vacio">Sin imagen</div>@endif
    </div>
    <div class="caja">
        <h2>Datos del paso</h2>
        <dl class="dl">
            <dt>Fecha</dt><dd>{{ $paso->created_at->format('d/m/Y H:i:s') }}</dd>
            <dt>Placa</dt><dd><span class="placa">{{ $paso->placa }}</span> <small>(OCR: «{{ $paso->placa_leida }}», {{ number_format(($paso->confianza_ocr ?? 0) * 100, 0) }}%)</small></dd>
            <dt>Propietario</dt><dd>{{ $paso->vehiculo->propietario ?? 'No registrada (posible evasión)' }}</dd>
            <dt>Categoría</dt><dd>{{ $paso->categoria }}</dd>
            <dt>Ejes</dt><dd>{{ $paso->ejes }} <small>({{ $paso->datos_inferencia['ejes_fuente'] ?? '—' }})</small></dd>
            <dt>Pasajeros visibles</dt><dd>{{ $paso->pasajeros ?? '—' }}</dd>
            <dt>Monto</dt><dd>Bs {{ number_format($paso->monto, 2) }}</dd>
            <dt>Estado</dt><dd>{!! $paso->estado === 'cobrado' ? '<span class="etq ok">Cobrado</span>' : '<span class="etq mal">Alerta</span>' !!}</dd>
            <dt>Caseta</dt><dd>{{ $paso->caseta->nombre ?? '' }}</dd>
            <dt>Operador</dt><dd>{{ $paso->usuario->email_google ?? '—' }}</dd>
            <dt>Latencia</dt><dd>{{ $paso->latencia_ms }} ms</dd>
        </dl>
        @foreach($paso->alertas as $a)
            <div class="alerta-item" style="margin-top:12px"><strong>{{ \App\Models\Alerta::TIPOS[$a->tipo] ?? $a->tipo }}</strong><small>{{ $a->mensaje }} · {{ $a->estado }}</small></div>
        @endforeach
        @if($paso->datos_inferencia)
            <details style="margin-top:12px"><summary>JSON de inferencia</summary><pre style="white-space:pre-wrap;font-size:12px">{{ json_encode($paso->datos_inferencia, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>
        @endif
    </div>
</div>
@endsection
