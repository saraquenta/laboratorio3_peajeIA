/* Agente conversacional: burbuja flotante y pagina de chat. Usa la sesion web (cookie) + CSRF. */
(function () {
  const csrf = document.querySelector('meta[name="csrf-token"]').content;

  function iniciar(raiz) {
    const lista = raiz.querySelector('[data-chat="mensajes"]');
    const form = raiz.querySelector('[data-chat="form"]');
    const texto = raiz.querySelector('[data-chat="texto"]');
    const imagen = raiz.querySelector('[data-chat="imagen"]');
    if (!lista || !form) return;

    function agregar(quien, contenido, estado, sugerencias) {
      const d = document.createElement('div');
      d.className = 'msg ' + quien;
      if (estado) { const e = document.createElement('span'); e.className = 'estado'; e.textContent = estado; d.appendChild(e); }
      const t = document.createElement('span');
      // negritas **texto** sin inyectar HTML
      contenido.split(/(\*\*[^*]+\*\*)/).forEach(function (parte) {
        if (/^\*\*[^*]+\*\*$/.test(parte)) { const b = document.createElement('strong'); b.textContent = parte.slice(2, -2); t.appendChild(b); }
        else t.appendChild(document.createTextNode(parte));
      });
      d.appendChild(t);
      (sugerencias || []).forEach(function (s) {
        const b = document.createElement('button'); b.type = 'button'; b.className = 'btn chico sec sug'; b.textContent = s;
        b.onclick = function () { enviar(s); }; d.appendChild(document.createElement('br')); d.appendChild(b);
      });
      lista.appendChild(d); lista.scrollTop = lista.scrollHeight;
      return d;
    }

    async function enviar(mensaje, archivo) {
      if (!mensaje && !archivo) return;
      agregar('yo', archivo ? '📷 ' + (archivo.name || 'foto') + (mensaje ? ' — ' + mensaje : '') : mensaje);
      const espera = agregar('bot', 'Consultando…');
      const fd = new FormData();
      if (mensaje) fd.append('mensaje', mensaje);
      if (archivo) fd.append('imagen', archivo);
      try {
        const r = await fetch(window.PEAJE.chatUrl, { method: 'POST', body: fd, credentials: 'same-origin',
          headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
        const j = await r.json();
        espera.remove();
        if (!r.ok) { agregar('bot', j.message || 'Error ' + r.status); return; }
        agregar('bot', j.respuesta, j.estado, j.sugerencias);
      } catch (e) { espera.remove(); agregar('bot', 'No hay conexión con el servidor.'); }
    }

    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      const m = texto.value.trim(); texto.value = '';
      enviar(m, null);
    });
    if (imagen) imagen.addEventListener('change', function () { if (imagen.files[0]) enviar(texto.value.trim(), imagen.files[0]); imagen.value = ''; texto.value = ''; });
    agregar('bot', 'Hola 👋 Escribe una placa (ej. 1234ABC) o envía una foto. También puedo darte la recaudación de hoy o las alertas pendientes.');
  }

  document.querySelectorAll('.burbuja-panel, .chat-pagina').forEach(iniciar);
  const b = document.getElementById('burbuja'), p = document.getElementById('burbuja-panel');
  if (b && p) {
    b.onclick = function () { p.classList.toggle('oculto'); const t = p.querySelector('[data-chat="texto"]'); if (t) t.focus(); };
    document.getElementById('burbuja-cerrar').onclick = function () { p.classList.add('oculto'); };
  }
})();
