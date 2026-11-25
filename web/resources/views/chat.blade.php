@extends('layouts.app')
@section('titulo', 'Agente conversacional')
@section('acciones')<small style="color:var(--suave)">Responde solo con datos reales de la base de datos</small>@endsection
@section('contenido')
<div class="caja chat-pagina">
    <div class="chat-mensajes" data-chat="mensajes"></div>
    <form class="chat-form" data-chat="form">
        <label class="adjuntar" title="Enviar foto de placa">📷<input type="file" accept="image/*" data-chat="imagen" hidden></label>
        <input type="text" data-chat="texto" placeholder="Escribe una placa (1234ABC), «recaudación de hoy» o «alertas»" autocomplete="off" autofocus>
        <button class="btn">Enviar</button>
    </form>
</div>
<p style="color:var(--suave);font-size:13px">Estados posibles: <strong>Registrada y con paso</strong> · <strong>Registrada sin paso</strong> · <strong>Identificada no registrada</strong> (posible evasión) · <strong>No existe</strong>. Corrige confusiones de OCR (O↔0, I↔1, B↔8, S↔5) y sugiere «¿Quisiste decir…?».</p>
@endsection
