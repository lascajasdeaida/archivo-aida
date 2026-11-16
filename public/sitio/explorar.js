/* Las cajas de Aída — Explorar con JS (docs/ux/sitio-publico.md §4). Sin este archivo la página funciona igual:
   el panel es un formulario GET, los chips y «Mostrar más» son enlaces. Con él:
   - los filtros, el orden y el rango se aplican sin recargar (explorar.json devuelve los fragmentos ya armados);
   - «Mostrar 20 más» agrega los resultados a la lista y lleva el foco al primero nuevo;
   - en celular, el panel de filtros es un diálogo a pantalla completa con el foco atrapado;
   - la URL se actualiza siempre (se puede compartir y «atrás» funciona). */
(function () {
  'use strict';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const norm = s => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  window.dataLayer = window.dataLayer || [];
  const evento = (nombre, datos = {}) => window.dataLayer.push({ event: nombre, ...datos });

  document.addEventListener('DOMContentLoaded', () => {
    const form = $('#form-explorar');
    if (!form) return;
    const jsonBase = form.dataset.json;
    const zonas = { facetas: $('#facetas'), chips: $('#chips'), resultados: $('#resultados') };
    const conteo = $('#conteo'), anuncio = $('#anuncio');
    let pedido = 0, espera;

    // Estado del formulario → parámetros de la URL (listas con coma, sin vacíos, como las arma el servidor)
    function parametros(extra = {}) {
      const p = new URLSearchParams(), listas = {};
      for (const [k, v] of new FormData(form)) {
        if (k === 'ruta' || v === '') continue;
        if (k.endsWith('[]')) (listas[k.slice(0, -2)] = listas[k.slice(0, -2)] || []).push(v);
        else p.set(k, v);
      }
      Object.entries(listas).forEach(([k, l]) => p.set(k, l.join(',')));
      const q = p.get('q') || '';
      if (p.get('orden') === (q ? 'relevancia' : 'caja') || (!q && p.get('orden') === 'relevancia')) p.delete('orden');
      Object.entries(extra).forEach(([k, v]) => v == null ? p.delete(k) : p.set(k, v));
      return p;
    }
    const conRuta = (base, p) => {
      const s = p.toString().replace(/%2C/g, ',');
      return s ? base + (base.includes('?') ? '&' : '?') + s : base;
    };

    function mostrarError() {
      zonas.resultados.innerHTML = '<div class="estado estado--error" role="alert"><h2>No pudimos cargar los resultados</h2>'
        + '<p>Hubo un problema de conexión con el archivo. Tus filtros siguen guardados.</p>'
        + '<button class="boton boton--primario" type="button" data-reintentar-js>Reintentar</button></div>';
      zonas.resultados.removeAttribute('aria-busy');
      evento('error_resultados', { tipo: 'conexion' });
    }

    // Pide la página 1 con los filtros actuales y reemplaza facetas, chips y resultados
    async function actualizar({ foco = null, url = null } = {}) {
      const p = url ? new URLSearchParams(url.split('?')[1] || '') : parametros();
      p.delete('ruta');
      const n = ++pedido;
      zonas.resultados.setAttribute('aria-busy', 'true');
      if (!zonas.resultados.querySelector('.lista-registros')) zonas.resultados.innerHTML = '<ul class="lista-registros" aria-hidden="true">' + '<li class="esqueleto"></li>'.repeat(6) + '</ul>';
      try {
        const r = await fetch(conRuta(jsonBase, p), { headers: { Accept: 'application/json' } });
        const d = await r.json();
        if (n !== pedido) return;          // llegó una respuesta más nueva
        if (!r.ok || d.error) throw new Error(d.error || r.status);
        pintar(d);
        history.replaceState({ explorar: true }, '', d.url);
        if (d.total === 0) evento('busqueda_sin_resultados', { search_term: p.get('q') || '', filtros: filtrosTexto(p) });
        if (foco) foco();
      } catch (e) {
        if (n === pedido) mostrarError();
      }
    }
    function filtrosTexto(p) {
      return [...p].filter(([k]) => !['q', 'orden', 'pagina'].includes(k)).map(([k, v]) => k + ':' + v).join('|');
    }

    function pintar(d) {
      const abiertas = new Set($$('.faceta[open]', zonas.facetas).map(x => x.dataset.clave));
      const busquedas = Object.fromEntries($$('.buscar-en-faceta', zonas.facetas).map(i => [i.dataset.clave, i.value]));
      const idFoco = document.activeElement && zonas.facetas.contains(document.activeElement) ? document.activeElement.id : null;
      zonas.facetas.innerHTML = d.facetas;
      $$('.faceta', zonas.facetas).forEach(f => { if (abiertas.has(f.dataset.clave)) f.open = true; });
      Object.entries(busquedas).forEach(([k, v]) => { const i = $('#buscar-' + k); if (i && v) { i.value = v; filtrarFaceta(i); } });
      if (idFoco && document.getElementById(idFoco)) document.getElementById(idFoco).focus();
      zonas.chips.innerHTML = d.chips;
      zonas.resultados.innerHTML = d.resultados;
      zonas.resultados.removeAttribute('aria-busy');
      conteo.textContent = d.conteo;
      $('#ver-n').textContent = Number(d.total).toLocaleString('es-AR');
      $('#boton-filtros-n').textContent = d.filtros || '';
      const rel = $('#orden option[value=relevancia]');
      if (rel) rel.disabled = !$('#q').value.trim();
      clearTimeout(espera);
      espera = setTimeout(() => { anuncio.textContent = d.conteo; }, 600);
    }

    // Checkboxes de facetas: al instante
    form.addEventListener('change', e => {
      const i = e.target;
      if (i.matches('.opcion input[type=checkbox]')) {
        evento(i.checked ? 'filtro_aplicado' : 'filtro_quitado', { filtro: i.name.replace('[]', ''), valor: i.value });
        actualizar();
      } else if (i.id === 'orden') {
        evento('orden_cambiado', { orden: i.value });
        actualizar();
      }
    });
    // Buscar dentro de una faceta (tipo, caja, descriptores): filtra las opciones en el cliente
    function filtrarFaceta(input) {
      const f = input.closest('.faceta'), t = norm(input.value);
      let n = 0;
      $$('.opcion', f).forEach(o => { const ok = !t || norm(o.textContent).includes(t); o.hidden = !ok; if (ok) n++; });
      const mas = $('.faceta-mas', f);
      if (mas) { if (t) mas.open = true; $('summary', mas).hidden = !!t; }
      $('.faceta-sin-coincidencias', f).hidden = n > 0;
    }
    form.addEventListener('input', e => {
      if (e.target.matches('.buscar-en-faceta')) filtrarFaceta(e.target);
      else if (e.target.id === 'q') buscarMientrasEscribe();
    });

    // Búsqueda mientras se escribe: espera 180 ms sin teclas (una consulta por pausa, no por letra). El código exacto
    // (C14R3) no se busca acá: Enter lleva directo a la ficha. El evento «search» de Estadísticas sale recién tras
    // 1,5 s quieto, una vez por término, para no contar cada letra.
    let tecleo, quieto, ultimoTermino = $('#q').value.trim();
    // Si la persona no eligió un orden, al buscar se ordena por relevancia y sin búsqueda por caja (los defaults del servidor)
    let ordenElegido = new URLSearchParams(location.search).has('orden');
    $('#orden').addEventListener('change', () => { ordenElegido = true; });
    function ordenPorDefecto() {
      const sel = $('#orden'), q = $('#q').value.trim();
      const rel = $('#orden option[value=relevancia]');
      if (rel) rel.disabled = !q;
      if (!ordenElegido || (!q && sel.value === 'relevancia')) sel.value = q ? 'relevancia' : 'caja';
    }
    function buscarMientrasEscribe() {
      clearTimeout(tecleo); clearTimeout(quieto);
      const q = $('#q').value.trim();
      if (/^c\d+(r\d*)?$/i.test(q) || (q.length === 1)) return;
      tecleo = setTimeout(() => {
        if (q === ultimoTermino) return;
        ultimoTermino = q;
        ordenPorDefecto();
        actualizar();
        quieto = setTimeout(() => { if (q) evento('search', { search_term: q, origen: 'explorar_en_vivo' }); }, 1500);
      }, 180);
    }
    form.addEventListener('keydown', e => {
      if (e.key === 'Enter' && e.target.matches('.buscar-en-faceta')) e.preventDefault();
      if (e.key === 'Enter' && e.target.matches('#desde, #hasta')) { e.preventDefault(); $('#aplicar-rango').click(); }
    });

    // Enviar: la búsqueda va al servidor tal cual (el código exacto, C14R3, lleva a la ficha); rango y «Ver N resultados» sin recargar
    form.addEventListener('submit', e => {
      const boton = e.submitter;
      if (boton && boton.id === 'ver-resultados') { e.preventDefault(); cerrarPanel(); return; }
      if (boton && boton.id === 'aplicar-rango') { e.preventDefault(); actualizar(); return; }
      const q = $('#q').value.trim();
      if (/^c\d+r\d+$/i.test(q)) { evento('search', { search_term: q, origen: 'explorar' }); return; } // navegación normal
      e.preventDefault();
      clearTimeout(tecleo); clearTimeout(quieto); ultimoTermino = q; ordenPorDefecto();
      evento('search', { search_term: q, origen: 'explorar' });
      actualizar({ foco: () => conteo.focus() });
    });

    // Chips, «Limpiar», «Quitar…» de sin resultados: enlaces que se resuelven sin recargar
    document.addEventListener('click', e => {
      const a = e.target.closest('#chips a, #sin-resultados a, [data-limpiar]');
      if (a && a.href && (a.closest('#form-explorar'))) {
        e.preventDefault();
        const u = new URL(a.href, location.href);
        const p = new URLSearchParams(u.search);
        $('#q').value = p.get('q') || '';
        if (a.matches('[data-limpiar-todo], [data-limpiar]')) evento('filtros_limpiados');
        else evento('filtro_quitado', {});
        actualizar({ url: u.search, foco: () => (($('#chips a') || conteo).focus()) });
        return;
      }
      if (e.target.closest('[data-reintentar-js]')) { actualizar(); return; }
      const mas = e.target.closest('#mas');
      if (mas) { e.preventDefault(); cargarMas(mas); }
    });

    async function cargarMas(boton) {
      const pagina = Number(boton.dataset.pagina);
      const primera = Number(($('#paginacion') || {}).dataset?.primera) || 1;
      const p = parametros({ pagina, primera: Math.max(1, primera) });
      boton.setAttribute('aria-disabled', 'true');
      boton.textContent = 'Cargando…';
      try {
        const r = await fetch(conRuta(jsonBase, p));
        const d = await r.json();
        if (!r.ok || d.error) throw new Error(d.error);
        const lista = $('#lista'), antes = lista.children.length;
        lista.insertAdjacentHTML('beforeend', d.items);
        $('#paginacion').outerHTML = d.paginacion;
        const primero = lista.children[antes];
        if (primero) $('a', primero).focus();
        const u = new URL(d.url, location.href);
        u.searchParams.set('primera', Math.max(1, primera));
        history.replaceState({ explorar: true }, '', u.pathname + u.search.replace(/%2C/g, ','));
        evento('cargar_mas', { pagina });
      } catch (e) {
        boton.removeAttribute('aria-disabled');
        boton.textContent = 'No se pudo cargar. Probá de nuevo';
        evento('error_resultados', { tipo: 'cargar_mas' });
      }
    }

    // Tarjeta clic → select_content
    document.addEventListener('click', e => {
      const a = e.target.closest('.registro h3 a, .registro h2 a');
      if (a) evento('select_content', { content_type: 'registro', item_id: a.closest('[data-codigo]').dataset.codigo, origen: 'explorar' });
    });
    // «Volver a los resultados» desde la ficha
    try { sessionStorage.setItem('aida-volver', location.href); } catch (e) { /* modo privado */ }
    addEventListener('popstate', () => location.reload());

    // ---- Panel de filtros en celular: diálogo modal con foco atrapado ----
    const panel = $('#panel-filtros'), abrir = $('#boton-filtros');
    const esMovil = () => matchMedia('(max-width: 899px)').matches;
    function abrirPanel() {
      panel.classList.add('abierto'); document.body.classList.add('panel-abierto');
      panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-modal', 'true'); panel.setAttribute('aria-labelledby', 'h-filtros-movil');
      abrir.setAttribute('aria-expanded', 'true'); $('.cerrar-panel', panel).focus(); evento('panel_filtros_abierto');
    }
    function cerrarPanel() {
      if (!panel.classList.contains('abierto')) return;
      panel.classList.remove('abierto'); document.body.classList.remove('panel-abierto');
      panel.setAttribute('role', 'region'); panel.removeAttribute('aria-modal'); panel.setAttribute('aria-labelledby', 'h-filtros');
      abrir.setAttribute('aria-expanded', 'false'); abrir.focus();
    }
    abrir.addEventListener('click', abrirPanel);
    $('.cerrar-panel', panel).addEventListener('click', cerrarPanel);
    panel.addEventListener('keydown', e => {
      if (!panel.classList.contains('abierto')) return;
      if (e.key === 'Escape') { e.preventDefault(); cerrarPanel(); }
      if (e.key === 'Tab') {
        const f = $$('button:not([disabled]), input:not([disabled]), summary, a[href], select', panel).filter(x => x.offsetParent !== null);
        if (!f.length) return;
        if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
        else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
      }
    });
    matchMedia('(max-width: 899px)').addEventListener('change', () => { if (!esMovil()) cerrarPanel(); });
    if (location.hash === '#q') setTimeout(() => $('#q').focus(), 0);
    if (/^#faceta-/.test(location.hash)) { const f = $(location.hash); if (f) f.open = true; }
  });
})();
