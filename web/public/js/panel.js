/*
 * Panel en tiempo real sin recargar la pagina.
 *  - Con Pusher configurado: se suscribe al canal "peaje" (eventos paso.registrado / alerta.creada).
 *  - Siempre: sondeo de respaldo cada 3 s (cada 15 s si Pusher esta conectado). Funciona en hosting compartido.
 */
(function () {
  const cfg = window.PANEL || {};
  let ultimoId = cfg.ultimoId || 0;
  let ultimaAlerta = cfg.ultimaAlerta || 0;
  let intervalo = 3000, temporizador = null;
  const bs = new Intl.NumberFormat('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const hora = (iso) => iso ? new Date(iso).toLocaleTimeString('es-BO', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '';

  function ponerContador(id, valor, moneda) {
    const el = $(id); if (!el) return;
    const txt = moneda ? 'Bs ' + bs.format(valor) : String(valor);
    if (el.textContent !== txt) { el.textContent = txt; el.closest('.tarjeta')?.classList.remove('flash'); void el.offsetWidth; el.closest('.tarjeta')?.classList.add('flash'); }
  }

  function filaPaso(p, nuevo) {
    const estado = p.estado === 'cobrado' ? '<span class="etq ok">Cobrado</span>' : '<span class="etq mal">Alerta</span>';
    const img = p.imagen_url ? `<img class="miniatura" src="${esc(p.imagen_url)}" alt="evidencia" loading="lazy">` : '';
    return `<tr class="${nuevo ? 'nuevo' : ''}"><td>${hora(p.fecha)}</td><td><a href="/pasos/${p.id}"><span class="placa">${esc(p.placa || '—')}</span></a>
      <br><small>${esc(p.propietario || (p.registrado ? '' : 'No registrada'))}</small></td><td>${esc(p.categoria || '')}</td>
      <td class="num">${p.ejes ?? ''}</td><td class="num">${p.pasajeros ?? ''}</td><td class="num">Bs ${bs.format(p.monto)}</td>
      <td>${estado}</td><td>${esc(p.caseta || '')}</td><td>${img}</td></tr>`;
  }

  function render(d) {
    const c = d.contadores;
    ponerContador('c-pasos', c.pasos_hoy); ponerContador('c-recaudacion', c.recaudacion_hoy, true);
    ponerContador('c-alertas', c.alertas_pendientes); ponerContador('c-pasajeros', c.pasajeros_hoy);
    ponerContador('c-total', c.recaudacion_total, true);
    const nav = $('nav-alertas'); if (nav) nav.textContent = c.alertas_pendientes || '';
    const tb = $('tabla-pasos');
    if (tb) tb.innerHTML = d.pasos.length ? d.pasos.map((p) => filaPaso(p, p.id > ultimoId && ultimoId > 0)).join('') : '<tr><td colspan="9" class="vacio">Sin pasos todavía</td></tr>';
    const la = $('lista-alertas');
    if (la) la.innerHTML = d.alertas.length ? d.alertas.map((a) => `<div class="alerta-item"><strong><span class="placa">${esc(a.placa || '—')}</span> ${esc(a.tipo_texto)}</strong>
      <small>${esc(a.caseta || '')} · ${hora(a.fecha)}</small></div>`).join('') : '<div class="vacio">Sin alertas pendientes ✅</div>';
    const pc = $('por-caseta');
    if (pc) pc.innerHTML = d.por_caseta.map((x) => `<tr><td>${esc(x.nombre)}</td><td class="num">${x.pasos}</td><td class="num">Bs ${bs.format(x.monto)}</td></tr>`).join('');
    if (d.ultima_alerta_id > ultimaAlerta && ultimaAlerta > 0) sonar();
    ultimoId = d.ultimo_id; ultimaAlerta = d.ultima_alerta_id;
    const u = $('actualizado'); if (u) u.textContent = 'Actualizado ' + hora(d.generado);
  }

  function sonar() {
    try { const ctx = new (window.AudioContext || window.webkitAudioContext)(); const o = ctx.createOscillator(); o.frequency.value = 880;
      o.connect(ctx.destination); o.start(); setTimeout(() => { o.stop(); ctx.close(); }, 180); } catch (e) { /* sin audio */ }
  }

  async function refrescar() {
    try {
      const r = await fetch(window.PEAJE.datosUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      if (r.ok) render(await r.json());
    } catch (e) { /* reintenta en el siguiente ciclo */ }
  }

  function programar() { clearInterval(temporizador); temporizador = setInterval(refrescar, intervalo); }

  if (cfg.pusher && cfg.pusher.key) {
    const s = document.createElement('script');
    s.src = 'https://js.pusher.com/8.4.0/pusher.min.js';
    s.onload = function () {
      const csrf = document.querySelector('meta[name="csrf-token"]').content;
      const pusher = new window.Pusher(cfg.pusher.key, { cluster: cfg.pusher.cluster,
        channelAuthorization: { endpoint: cfg.pusher.authEndpoint, transport: 'ajax', headers: { 'X-CSRF-TOKEN': csrf } } });
      const canal = pusher.subscribe('private-peaje');
      canal.bind('paso.registrado', refrescar);
      canal.bind('alerta.creada', refrescar);
      pusher.connection.bind('state_change', (st) => {
        const modo = $('modo-rt');
        if (modo) modo.textContent = st.current === 'connected' ? 'Tiempo real: Pusher (WebSocket)' : 'Tiempo real: sondeo 3 s';
        intervalo = st.current === 'connected' ? 15000 : 3000; programar();
      });
    };
    document.head.appendChild(s);
  }
  refrescar();
  programar();
})();
