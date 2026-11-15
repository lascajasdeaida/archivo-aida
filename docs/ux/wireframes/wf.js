/* Wireframes del sitio público «Las cajas de Aída».
   Lógica mínima para que los wireframes sean navegables con los datos reales de datos.js.
   No es el código de producción: muestra comportamiento (filtros combinables, conteos, estados, foco). */
(function () {
  'use strict';
  const D = window.AIDA;
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const num = n => n.toLocaleString('es-AR');
  const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const norm = s => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  const slug = s => norm(s).replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  const params = new URLSearchParams(location.search);

  // ---------- Índices ----------
  const cajaPorNum = new Map(D.cajas.map(c => [c.n, c]));
  const vocab = new Map();
  function indexar(lista, clave) {
    const usados = new Set();
    lista.forEach(v => {
      let s = slug(v.nombre) || 'termino';
      if (usados.has(s)) s += '-' + v.id; // términos casi iguales sin curar («Asistencia a la producción.» / sin punto)
      usados.add(s); v.slug = s; v.faceta = clave; vocab.set(v.id, v);
    });
  }
  indexar(D.tipos, 'tipo'); indexar(D.descriptores, 'descriptor');
  const registroPorCodigo = new Map(D.registros.map(r => [r.c, r]));
  D.registros.forEach(r => {
    r.dec = r.a ? String(Math.floor(r.a / 10) * 10) : 'sin-fecha';
    r.heno = norm([r.c, r.t, r.x, r.au, r.a, r.at, cajaPorNum.get(r.k)?.nombre,
      ...r.tp.map(i => vocab.get(i)?.nombre), ...r.ds.map(i => vocab.get(i)?.nombre)].join(' '));
    r.tnorm = norm(r.t);
  });

  // ---------- Eventos para Estadísticas (GA4) ----------
  window.dataLayer = window.dataLayer || [];
  function evento(nombre, datos = {}) {
    window.dataLayer.push({ event: nombre, ...datos });
    const ol = $('#registro-eventos');
    if (ol) {
      const li = document.createElement('li');
      li.textContent = nombre + ' ' + JSON.stringify(datos);
      ol.prepend(li);
      $('#eventos-cuenta').textContent = ol.children.length;
    }
  }

  // ---------- Título visible: los registros sin título muestran su tipo ----------
  function titulo(r) {
    if (r.t) return { texto: r.t, sin: false };
    const tipos = r.tp.map(i => vocab.get(i)?.nombre).filter(Boolean);
    return { texto: tipos.length ? tipos.join(' · ') : 'Documento sin título', sin: true };
  }
  function tituloHTML(r) {
    const t = titulo(r);
    return t.sin ? `<span class="sin-titulo">${esc(t.texto)}</span> <span class="solo-lector">(sin título)</span>` : esc(t.texto);
  }
  function anio(r) { return r.a ?? r.at ?? 'Sin fecha'; }
  function etiquetas(r, max = 2) {
    let h = r.d ? '<span class="etq digital">Documento digital</span>' : '<span class="etq solo-ficha">Solo ficha</span>';
    if (r.r) h += ' <span class="etq pedido">Consulta a pedido</span>';
    r.tp.slice(0, max).forEach(i => { if (vocab.get(i)) h += ` <span class="etq tipo">${esc(vocab.get(i).nombre)}</span>`; });
    return h;
  }
  function tarjeta(r, nivel = 3) {
    const c = cajaPorNum.get(r.k);
    const t = titulo(r);
    return `<li class="tarjeta" data-codigo="${r.c}">
      <div class="mini wf-caja" aria-hidden="true">${r.d ? (r.r ? 'A pedido' : 'Miniatura') : 'Sin imagen'}</div>
      <div>
        <h${nivel}><a href="ficha.html?c=${r.c}" data-evento-tarjeta>${tituloHTML(r)}</a></h${nivel}>
        <p class="meta">${t.sin ? 'Sin título · ' : ''}${r.c} · Caja ${r.k}: ${esc(c?.nombre)} · ${esc(anio(r))}</p>
        ${r.x ? `<p class="extracto">${esc(r.x)}</p>` : ''}
        <div class="etqs">${etiquetas(r)}</div>
      </div></li>`;
  }

  // ---------- Partes comunes ----------
  function comunes(pagina, urlDefinitiva) {
    document.body.insertAdjacentHTML('afterbegin', `
      <a class="saltar" href="#contenido">Saltar al contenido</a>
      <aside class="wf-rotulo" aria-label="Notas del wireframe">Wireframe · <a href="index.html">Índice de pantallas</a> · URL definitiva: <code>${esc(urlDefinitiva)}</code></aside>
      <header class="cabecera"><div class="contenedor">
        <a class="marca" href="inicio.html"><span class="wf-caja" aria-hidden="true">logo</span>Las cajas de Aída</a>
        <a class="boton sec" href="explorar.html" aria-label="Buscar en el archivo">Buscar</a>
        <button class="boton sec menu-boton" type="button" aria-expanded="false" aria-controls="nav-principal">Menú</button>
        <nav class="nav-principal" id="nav-principal" aria-label="Principal"><ul>
          <li><a href="inicio.html" ${pagina === 'inicio' ? 'aria-current="page"' : ''}>Inicio</a></li>
          <li><a href="explorar.html" ${pagina === 'explorar' ? 'aria-current="page"' : ''}>Explorar el archivo</a></li>
          <li><a href="cajas.html" ${pagina === 'cajas' ? 'aria-current="page"' : ''}>Las 70 cajas</a></li>
          <li><a href="sobre.html" ${pagina === 'sobre' ? 'aria-current="page"' : ''}>Sobre el archivo</a></li>
        </ul></nav>
      </div></header>`);
    document.body.insertAdjacentHTML('beforeend', `
      <footer class="pie"><div class="contenedor">
        <p>Archivo Aída Bortnik · Las cajas de Aída. Datos del wireframe: extracto de la base generado el ${esc(D.generado)}.</p>
        <ul><li><a href="sobre.html">Sobre el archivo</a></li><li><a href="sobre.html#citar">Cómo citar</a></li>
          <li><a href="sobre.html#accesibilidad">Accesibilidad</a></li><li><a href="sobre.html#contacto">Contacto</a></li></ul>
      </div></footer>
      <details class="eventos"><summary>Eventos GA4 (<span id="eventos-cuenta">0</span>)</summary><ol id="registro-eventos"></ol></details>`);
    const mb = $('.menu-boton');
    mb.addEventListener('click', () => {
      const abierto = mb.getAttribute('aria-expanded') !== 'true';
      mb.setAttribute('aria-expanded', abierto); $('#nav-principal').classList.toggle('abierto', abierto);
    });
    document.addEventListener('click', e => {
      const a = e.target.closest('a[data-evento-tarjeta]');
      if (a) evento('select_content', { content_type: 'registro', item_id: a.closest('[data-codigo]').dataset.codigo, origen: pagina });
    });
  }

  // =====================================================================
  // INICIO
  // =====================================================================
  function inicio() {
    comunes('inicio', '/');
    const T = D.totales;
    $('#cifras').innerHTML = `
      <li><b>${num(T.cajas)}</b> cajas</li><li><b>${num(T.regs)}</b> documentos catalogados</li>
      <li><b>${num(T.digit)}</b> ya digitalizados</li><li><b>${T.desde}–${T.hasta}</b> años que abarca</li>`;
    $('#por-tipo').innerHTML = D.tipos.slice(0, 10).map(v =>
      `<li><a href="explorar.html?tipo=${v.slug}">${esc(v.nombre)} <span class="n">${num(v.n)}</span></a></li>`).join('') +
      `<li><a href="explorar.html#faceta-tipo">Todos los tipos (${D.tipos.length})</a></li>`;
    const dec = {};
    D.registros.forEach(r => dec[r.dec] = (dec[r.dec] || 0) + 1);
    $('#por-decada').innerHTML = Object.keys(dec).sort().map(k =>
      `<li><a href="explorar.html?decada=${k}">${k === 'sin-fecha' ? 'Sin fecha' : 'Década de ' + k} <span class="n">${num(dec[k])}</span></a></li>`).join('');
    const vistos = new Set();
    const digit = D.registros.filter(r => r.d && !r.r && r.t && !vistos.has(r.k) && vistos.add(r.k)).slice(0, 6);
    $('#digitalizados').innerHTML = digit.map(r => tarjeta(r)).join('');
    $('#cajas-destacadas').innerHTML = D.cajas.slice(0, 9).map(tarjetaCaja).join('');
    $('#form-inicio').addEventListener('submit', () => evento('search', { search_term: $('#q-inicio').value, origen: 'inicio' }));
  }

  function tarjetaCaja(c) {
    const pct = c.regs ? Math.round(c.digit / c.regs * 100) : 0;
    return `<li class="tarjeta-caja"><span class="num">Caja ${c.n}</span>
      <a href="caja.html?n=${c.n}">${esc(c.nombre)}</a>
      <div class="meta">${num(c.regs)} documentos${c.desde ? ' · ' + c.desde + (c.hasta !== c.desde ? '–' + c.hasta : '') : ''}</div>
      <div class="meta">${c.digit ? num(c.digit) + ' digitalizados' : 'Sin digitalizar todavía'}</div>
      <div class="barra" role="img" aria-label="${pct}% digitalizado"><i style="width:${pct}%"></i></div></li>`;
  }

  // =====================================================================
  // LISTADO DE CAJAS
  // =====================================================================
  function cajas() {
    comunes('cajas', '/cajas');
    const orden = $('#orden-cajas');
    const filtro = $('#filtro-cajas');
    const pintar = () => {
      const q = norm(filtro.value);
      let l = D.cajas.filter(c => !q || norm(c.nombre + ' ' + c.n).includes(q));
      if (orden.value === 'nombre') l = [...l].sort((a, b) => norm(a.nombre).localeCompare(norm(b.nombre)));
      if (orden.value === 'digit') l = [...l].sort((a, b) => b.digit - a.digit || a.n - b.n);
      $('#lista-cajas').innerHTML = l.map(tarjetaCaja).join('');
      $('#conteo-cajas').textContent = l.length === D.cajas.length ? `${l.length} cajas` : `${l.length} de ${D.cajas.length} cajas`;
    };
    filtro.addEventListener('input', pintar); orden.addEventListener('change', pintar); pintar();
  }

  // =====================================================================
  // EXPLORAR (búsqueda + filtros combinables)
  // =====================================================================
  const POR_PAGINA = 20;
  const FACETAS = [
    { clave: 'disponible', titulo: 'Disponibilidad', abierta: true, opciones: () => [
      { v: 'digital', nombre: 'Con documento digital' }, { v: 'ficha', nombre: 'Solo ficha (sin digitalizar)' }],
      prueba: (r, v) => v === 'digital' ? !!r.d : !r.d, ordenFijo: true },
    { clave: 'acceso', titulo: 'Acceso', abierta: false, opciones: () => [
      { v: 'libre', nombre: 'Consulta libre' }, { v: 'a-pedido', nombre: 'Consulta a pedido' }],
      prueba: (r, v) => v === 'a-pedido' ? !!r.r : !r.r, ordenFijo: true },
    { clave: 'tipo', titulo: 'Tipo de documento', abierta: true, buscar: true, opciones: () => D.tipos.map(t => ({ v: t.slug, nombre: t.nombre, id: t.id })),
      prueba: (r, v, o) => r.tp.includes(o.id) },
    { clave: 'decada', titulo: 'Año', abierta: true, rango: true, opciones: () => {
      const s = new Set(D.registros.map(r => r.dec));
      return [...s].sort().map(k => ({ v: k, nombre: k === 'sin-fecha' ? 'Sin fecha' : 'Década de ' + k }));
    }, prueba: (r, v) => r.dec === v, ordenFijo: true },
    { clave: 'caja', titulo: 'Caja / obra', abierta: false, buscar: true, opciones: () => D.cajas.map(c => ({ v: String(c.n), nombre: `${c.n}. ${c.nombre}` })),
      prueba: (r, v) => String(r.k) === v, ordenFijo: true },
    { clave: 'descriptor', titulo: 'Descriptores (temas)', abierta: false, buscar: true, opciones: () => D.descriptores.map(t => ({ v: t.slug, nombre: t.nombre, id: t.id })),
      prueba: (r, v, o) => r.ds.includes(o.id) },
  ];
  FACETAS.forEach(f => { f.lista = f.opciones(); f.porValor = new Map(f.lista.map(o => [o.v, o])); });

  function explorar() {
    comunes('explorar', '/explorar');
    const estado = {
      q: params.get('q') || '',
      sel: Object.fromEntries(FACETAS.map(f => [f.clave, new Set((params.get(f.clave) || '').split(',').filter(v => f.porValor.has(v)))])),
      desde: parseInt(params.get('desde')) || null, hasta: parseInt(params.get('hasta')) || null,
      orden: params.get('orden') || (params.get('q') ? 'relevancia' : 'caja'),
      pagina: Math.max(1, parseInt(params.get('pagina')) || 1),
      abiertasMas: new Set(),
    };
    const simular = params.get('simular');
    $('#q').value = estado.q;

    function cumple(r, excepto) {
      if (estado.q) { for (const p of norm(estado.q).split(/\s+/).filter(Boolean)) if (!r.heno.includes(p)) return false; }
      for (const f of FACETAS) {
        if (f.clave === excepto) continue;
        const s = estado.sel[f.clave];
        if (s.size && ![...s].some(v => f.prueba(r, v, f.porValor.get(v)))) return false; // OR dentro de la faceta
      }
      if (excepto !== 'decada' && (estado.desde || estado.hasta)) {
        if (!r.a) return false;
        if (estado.desde && r.a < estado.desde) return false;
        if (estado.hasta && r.a > estado.hasta) return false;
      }
      return true; // AND entre facetas
    }
    function puntaje(r) {
      const q = norm(estado.q); let p = 0;
      if (r.tnorm.includes(q)) p += 10;
      q.split(/\s+/).forEach(w => { if (r.tnorm.includes(w)) p += 3; });
      return p + (r.d ? 1 : 0);
    }
    function ordenar(l) {
      const o = estado.orden;
      const porCaja = (a, b) => a.k - b.k || a.nr - b.nr;
      if (o === 'relevancia' && estado.q) return l.map(r => [puntaje(r), r]).sort((a, b) => b[0] - a[0] || porCaja(a[1], b[1])).map(x => x[1]);
      if (o === 'anio-asc') return [...l].sort((a, b) => (a.a ?? 9999) - (b.a ?? 9999) || porCaja(a, b));
      if (o === 'anio-desc') return [...l].sort((a, b) => (b.a ?? 0) - (a.a ?? 0) || porCaja(a, b));
      if (o === 'digital') return [...l].sort((a, b) => b.d - a.d || porCaja(a, b));
      return [...l].sort(porCaja);
    }
    function aURL() {
      const p = new URLSearchParams();
      if (estado.q) p.set('q', estado.q);
      FACETAS.forEach(f => { if (estado.sel[f.clave].size) p.set(f.clave, [...estado.sel[f.clave]].join(',')); });
      if (estado.desde) p.set('desde', estado.desde);
      if (estado.hasta) p.set('hasta', estado.hasta);
      if (estado.orden !== (estado.q ? 'relevancia' : 'caja')) p.set('orden', estado.orden);
      if (estado.pagina > 1) p.set('pagina', estado.pagina);
      const s = p.toString().replace(/%2C/g, ',');
      history.replaceState(null, '', s ? '?' + s : location.pathname);
      $('.wf-rotulo code').textContent = '/explorar' + (s ? '?' + s : '');
      sessionStorage.setItem('aida-volver', location.href);
    }
    function activos() {
      const l = [];
      if (estado.q) l.push({ etiqueta: `Búsqueda: «${estado.q}»`, quitar: () => { estado.q = ''; $('#q').value = ''; }, f: 'q', v: estado.q });
      FACETAS.forEach(f => estado.sel[f.clave].forEach(v => l.push({ etiqueta: `${f.titulo}: ${f.porValor.get(v).nombre}`, quitar: () => estado.sel[f.clave].delete(v), f: f.clave, v })));
      if (estado.desde || estado.hasta) l.push({ etiqueta: `Años: ${estado.desde || '…'}–${estado.hasta || '…'}`, quitar: () => { estado.desde = estado.hasta = null; }, f: 'rango', v: `${estado.desde}-${estado.hasta}` });
      return l;
    }

    function pintarFacetas() {
      const cont = $('#facetas');
      const abiertos = new Set($$('.faceta[open]', cont).map(d => d.dataset.clave));
      const primera = !cont.children.length;
      const valoresBusq = Object.fromEntries($$('.buscar-en-faceta', cont).map(i => [i.dataset.clave, i.value]));
      const foco = document.activeElement && cont.contains(document.activeElement) ? document.activeElement.id : null;
      cont.innerHTML = FACETAS.map(f => {
        const base = D.registros.filter(r => cumple(r, f.clave));
        const cuenta = new Map();
        f.lista.forEach(o => cuenta.set(o.v, base.filter(r => f.prueba(r, o.v, o)).length));
        let ops = f.lista.filter(o => cuenta.get(o.v) > 0 || estado.sel[f.clave].has(o.v));
        if (!f.ordenFijo) ops.sort((a, b) => cuenta.get(b.v) - cuenta.get(a.v));
        const busq = norm(valoresBusq[f.clave] || '');
        if (busq) ops = ops.filter(o => norm(o.nombre).includes(busq));
        const todas = f.rango || f.clave === 'disponible' || f.clave === 'acceso' || f.abiertaMas || estado.abiertasMas.has(f.clave) || busq;
        const visibles = todas ? ops : ops.filter((o, i) => i < 8 || estado.sel[f.clave].has(o.v));
        const n = estado.sel[f.clave].size + (f.rango && (estado.desde || estado.hasta) ? 1 : 0);
        const max = Math.max(1, ...ops.map(o => cuenta.get(o.v)));
        const abierta = primera ? (f.abierta || n > 0) : abiertos.has(f.clave);
        return `<details class="faceta" data-clave="${f.clave}" id="faceta-${f.clave}" ${abierta ? 'open' : ''}>
          <summary>${f.titulo}${n ? ` <span class="solo-lector">,</span> (${n} elegido${n > 1 ? 's' : ''})` : ''}</summary>
          <fieldset><legend>${f.titulo}</legend>
          ${f.rango ? `<div class="histo" aria-hidden="true">${ops.filter(o => o.v !== 'sin-fecha').map(o => `<span style="height:${Math.round(cuenta.get(o.v) / max * 100)}%" title="${o.v}"></span>`).join('')}</div>` : ''}
          ${f.buscar && f.lista.length > 10 ? `<label class="solo-lector" for="buscar-${f.clave}">Buscar en ${f.titulo}</label>
            <input class="buscar-en-faceta" id="buscar-${f.clave}" data-clave="${f.clave}" type="search" placeholder="Buscar ${f.titulo.toLowerCase()}…" value="${esc(valoresBusq[f.clave] || '')}">` : ''}
          ${visibles.map((o, i) => {
            const id = `f-${f.clave}-${i}`, c = cuenta.get(o.v);
            return `<label class="opcion ${c ? '' : 'cero'}" for="${id}"><input type="checkbox" id="${id}" data-f="${f.clave}" value="${esc(o.v)}" ${estado.sel[f.clave].has(o.v) ? 'checked' : ''}>
              <span>${esc(o.nombre)}</span><span class="n" aria-label="${c} resultados">${num(c)}</span></label>`;
          }).join('') || '<p class="meta">Ninguna opción coincide.</p>'}
          ${!todas && ops.length > visibles.length ? `<button type="button" class="boton link" data-mas="${f.clave}" aria-controls="faceta-${f.clave}">Ver ${ops.length - visibles.length} más</button>` : ''}
          ${f.rango ? `<div class="rango"><label>Desde <input type="number" inputmode="numeric" id="desde" min="1940" max="2010" value="${estado.desde || ''}" placeholder="${D.totales.desde}"></label>
            <label>Hasta <input type="number" inputmode="numeric" id="hasta" min="1940" max="2010" value="${estado.hasta || ''}" placeholder="${D.totales.hasta}"></label>
            <button type="button" class="boton sec" id="aplicar-rango">Aplicar</button></div>
            <p class="meta">Los documentos «Sin fecha» quedan fuera al elegir un rango de años.</p>` : ''}
          </fieldset></details>`;
      }).join('');
      if (foco && document.getElementById(foco)) document.getElementById(foco).focus();
    }

    function pintarResultados(enfocarDesde) {
      const zona = $('#resultados');
      if (simular === 'error') {
        zona.innerHTML = `<div class="estado" role="alert"><h2>No pudimos cargar los resultados</h2>
          <p>Hubo un problema de conexión con el archivo. Tus filtros siguen guardados.</p>
          <button class="boton" type="button" onclick="location.search=''">Reintentar</button></div>`;
        evento('error_resultados', { tipo: 'conexion' }); return;
      }
      if (simular === 'cargando') {
        zona.setAttribute('aria-busy', 'true');
        zona.innerHTML = '<p class="meta">Cargando resultados…</p><ul class="lista-resultados">' + '<li class="esqueleto"></li>'.repeat(5) + '</ul>'; return;
      }
      const lista = ordenar(D.registros.filter(r => cumple(r)));
      const total = lista.length;
      $('#conteo').textContent = total === D.registros.length && !activos().length
        ? `${num(total)} documentos en el archivo` : `${num(total)} ${total === 1 ? 'resultado' : 'resultados'}`;
      $('#boton-filtros-n').textContent = activos().filter(a => a.f !== 'q').length ? `(${activos().filter(a => a.f !== 'q').length})` : '';
      $('#ver-n').textContent = num(total);
      // chips
      const ac = activos();
      $('#chips').innerHTML = ac.map((a, i) => `<li><button type="button" class="chip" data-quitar="${i}">${esc(a.etiqueta)} <span aria-hidden="true">✕</span><span class="solo-lector">, quitar filtro</span></button></li>`).join('') +
        (ac.length > 1 ? '<li><button type="button" class="boton link" data-limpiar>Limpiar todo</button></li>' : '');
      $('#chips').hidden = !ac.length;
      $('#orden').querySelector('[value=relevancia]').disabled = !estado.q;
      $('#orden').value = estado.orden;

      if (!total) {
        zona.innerHTML = `<div class="estado" id="sin-resultados"><h2>No encontramos documentos con esa combinación</h2>
          <p>Probá quitar alguno de los filtros:</p><ul>${quitarSugerencias(ac)}</ul>
          <p>O buscá en <a href="explorar.html?q=${encodeURIComponent(estado.q)}">todo el archivo</a>. Muchos documentos no tienen título: buscar por tipo («carta», «guion») o por la obra suele dar más resultados.</p></div>`;
        evento('busqueda_sin_resultados', { search_term: estado.q, filtros: ac.filter(a => a.f !== 'q').map(a => a.f + ':' + a.v).join('|') });
        return;
      }
      const hasta = Math.min(total, estado.pagina * POR_PAGINA);
      zona.removeAttribute('aria-busy');
      zona.innerHTML = `<ul class="lista-resultados" id="lista">${lista.slice(0, hasta).map(r => tarjeta(r)).join('')}</ul>
        <div class="paginacion">
          <p class="meta" id="mostrando">Mostrando ${num(hasta)} de ${num(total)}</p>
          <progress max="${total}" value="${hasta}" aria-labelledby="mostrando"></progress>
          ${hasta < total ? `<a class="boton sec" id="mas" href="?pagina=${estado.pagina + 1}">Mostrar ${Math.min(POR_PAGINA, total - hasta)} más</a>` : '<p class="meta">Llegaste al final de la lista.</p>'}
        </div>`;
      if (enfocarDesde != null) {
        const li = $$('#lista > li')[enfocarDesde];
        if (li) $('a', li).focus();
      }
    }
    function quitarSugerencias(ac) {
      return ac.map((a, i) => {
        // cuántos habría quitando ese filtro
        const guard = JSON.stringify([estado.q, [...Object.entries(estado.sel)].map(([k, s]) => [k, [...s]]), estado.desde, estado.hasta]);
        a.quitar(); const n = D.registros.filter(r => cumple(r)).length;
        const g = JSON.parse(guard); estado.q = g[0]; g[1].forEach(([k, l]) => estado.sel[k] = new Set(l)); estado.desde = g[2]; estado.hasta = g[3]; $('#q').value = estado.q;
        return `<li><button type="button" class="boton link" data-quitar="${i}">Quitar «${esc(a.etiqueta)}»</button> → ${num(n)} resultados</li>`;
      }).join('');
    }

    let anuncio;
    function actualizar(motivo) {
      estado.pagina = motivo === 'mas' ? estado.pagina : 1;
      pintarFacetas(); pintarResultados(); aURL();
      clearTimeout(anuncio);
      anuncio = setTimeout(() => { $('#anuncio').textContent = $('#conteo').textContent; }, 600);
    }

    // eventos de interfaz
    $('#form-buscar').addEventListener('submit', e => {
      e.preventDefault(); estado.q = $('#q').value.trim();
      estado.orden = estado.q ? 'relevancia' : (estado.orden === 'relevancia' ? 'caja' : estado.orden);
      evento('search', { search_term: estado.q, origen: 'explorar' }); actualizar();
      $('#conteo').focus();
    });
    $('#facetas').addEventListener('change', e => {
      const i = e.target.closest('input[type=checkbox]'); if (!i) return;
      const s = estado.sel[i.dataset.f];
      if (i.checked) { s.add(i.value); evento('filtro_aplicado', { filtro: i.dataset.f, valor: i.value }); }
      else { s.delete(i.value); evento('filtro_quitado', { filtro: i.dataset.f, valor: i.value }); }
      actualizar();
    });
    $('#facetas').addEventListener('input', e => { if (e.target.matches('.buscar-en-faceta')) pintarFacetas(); });
    $('#facetas').addEventListener('click', e => {
      const m = e.target.closest('[data-mas]');
      if (m) { estado.abiertasMas.add(m.dataset.mas); const k = m.dataset.mas; pintarFacetas(); const ops = $$(`#faceta-${k} input[type=checkbox]`); ops[Math.min(8, ops.length - 1)]?.focus(); }
      if (e.target.id === 'aplicar-rango') {
        estado.desde = parseInt($('#desde').value) || null; estado.hasta = parseInt($('#hasta').value) || null;
        if (estado.desde && estado.hasta && estado.desde > estado.hasta) [estado.desde, estado.hasta] = [estado.hasta, estado.desde];
        evento('filtro_aplicado', { filtro: 'anio', valor: `${estado.desde || ''}-${estado.hasta || ''}` }); actualizar();
      }
    });
    document.addEventListener('click', e => {
      const q = e.target.closest('[data-quitar]');
      if (q) {
        const a = activos()[+q.dataset.quitar]; a.quitar(); evento('filtro_quitado', { filtro: a.f, valor: String(a.v) }); actualizar();
        ($('#chips button') || $('#conteo')).focus();
      }
      if (e.target.closest('[data-limpiar]')) {
        FACETAS.forEach(f => estado.sel[f.clave].clear()); estado.desde = estado.hasta = null; estado.q = ''; $('#q').value = '';
        evento('filtros_limpiados'); actualizar(); $('#conteo').focus();
      }
      if (e.target.id === 'mas') {
        e.preventDefault(); const desde = estado.pagina * POR_PAGINA; estado.pagina++;
        evento('cargar_mas', { pagina: estado.pagina }); pintarResultados(desde); aURL();
      }
    });
    $('#orden').addEventListener('change', e => { estado.orden = e.target.value; evento('orden_cambiado', { orden: estado.orden }); actualizar(); });

    // Panel de filtros en celular: diálogo modal con foco atrapado
    const panel = $('#panel-filtros'), abrir = $('#boton-filtros');
    const esMovil = () => matchMedia('(max-width: 899px)').matches;
    function abrirPanel() {
      panel.classList.add('abierto'); document.body.classList.add('panel-abierto');
      panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-modal', 'true');
      abrir.setAttribute('aria-expanded', 'true'); $('.cerrar-panel', panel).focus(); evento('panel_filtros_abierto');
    }
    function cerrarPanel() {
      panel.classList.remove('abierto'); document.body.classList.remove('panel-abierto');
      panel.setAttribute('role', 'region'); panel.removeAttribute('aria-modal');
      abrir.setAttribute('aria-expanded', 'false'); abrir.focus();
    }
    abrir.addEventListener('click', abrirPanel);
    $$('.cerrar-panel, #ver-resultados', panel).forEach(b => b.addEventListener('click', cerrarPanel));
    panel.addEventListener('keydown', e => {
      if (!panel.classList.contains('abierto')) return;
      if (e.key === 'Escape') cerrarPanel();
      if (e.key === 'Tab') {
        const f = $$('button, input, summary, [href], select', panel).filter(x => x.offsetParent !== null);
        if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
        else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
      }
    });
    matchMedia('(max-width: 899px)').addEventListener('change', () => { if (!esMovil() && panel.classList.contains('abierto')) cerrarPanel(); });
    if (params.get('panel') === 'abierto' && esMovil()) setTimeout(abrirPanel, 0);

    $('#orden').value = estado.orden;
    const pag = estado.pagina; pintarFacetas(); estado.pagina = pag; pintarResultados(); aURL();
    if (estado.q) evento('search', { search_term: estado.q, origen: 'url' });
  }

  // =====================================================================
  // CAJA
  // =====================================================================
  function caja() {
    const n = parseInt(params.get('n')) || 2;
    const c = cajaPorNum.get(n) || D.cajas[1];
    comunes('cajas', '/caja/' + c.slug);
    document.title = `Caja ${c.n}: ${c.nombre} · Las cajas de Aída`;
    const regs = D.registros.filter(r => r.k === c.n);
    const tipos = {};
    regs.forEach(r => r.tp.forEach(i => tipos[i] = (tipos[i] || 0) + 1));
    const base = norm(c.nombre).replace(/[^a-z ]/g, '').split(' ').filter(w => w.length > 3).slice(0, 2).join(' ');
    const hermanas = D.cajas.filter(o => o.n !== c.n && base && norm(o.nombre).replace(/[^a-z ]/g, '').includes(base));
    const ant = D.cajas[D.cajas.indexOf(c) - 1], sig = D.cajas[D.cajas.indexOf(c) + 1];
    $('#contenido').innerHTML = `
      <nav class="migas" aria-label="Estás en"><ol><li><a href="inicio.html">Inicio</a></li><li><a href="cajas.html">Las 70 cajas</a></li><li aria-current="page">Caja ${c.n}</li></ol></nav>
      <div class="cabeza-pagina">
        <p class="dato-num">Caja ${c.n} de ${D.cajas.length}</p>
        <h1>${esc(c.nombre)}</h1>
        <ul class="datos-caja">
          <li><b>${regs.length}</b> documentos</li><li><b>${c.digit}</b> con documento digital</li>
          ${c.restr ? `<li><b>${c.restr}</b> de consulta a pedido</li>` : ''}
          <li>${c.desde ? `Años ${c.desde}${c.hasta !== c.desde ? '–' + c.hasta : ''}` : 'Sin años cargados'}</li>
          <li>${regs.filter(r => !r.t).length} sin título</li>
        </ul>
        ${c.resumen ? `<div class="texto-plegable plegado" id="resumen-caja"><div class="cuerpo" id="cuerpo-resumen"><p>${esc(c.resumen)}</p></div>
          <button type="button" class="boton link" aria-expanded="false" aria-controls="cuerpo-resumen" id="leer-mas">Leer más</button></div>`
          : '<p class="wf-nota">Esta caja no tiene texto de presentación («Edición de catálogo») cargado. Se muestra solo la lista.</p>'}
        <form class="buscador" action="explorar.html" role="search" aria-label="Buscar en esta caja">
          <input type="hidden" name="caja" value="${c.n}">
          <label class="solo-lector" for="q-caja">Buscar en la caja ${c.n}</label>
          <input type="search" id="q-caja" name="q" placeholder="Buscar en esta caja…"><button class="boton">Buscar</button>
        </form>
        <p style="margin-top:8px"><a href="explorar.html?caja=${c.n}">Ver esta caja en el buscador, con todos los filtros</a></p>
      </div>
      ${hermanas.length ? `<section class="seccion" aria-labelledby="h-hermanas"><h2 id="h-hermanas">La misma obra sigue en otras cajas</h2>
        <ul class="accesos">${hermanas.map(o => `<li><a href="caja.html?n=${o.n}">Caja ${o.n}: ${esc(o.nombre)} <span class="n">${o.regs}</span></a></li>`).join('')}</ul>
        <p class="wf-nota">Hoy se deduce por nombre parecido. Propuesta: agrupar cajas por obra en el backoffice (decisión abierta D4).</p></section>` : ''}
      <section class="seccion" aria-labelledby="h-docs">
        <div class="seccion-cabeza"><h2 id="h-docs">Documentos de la caja</h2><span class="meta">En el orden en que están en la caja</span></div>
        <ul class="accesos" aria-label="Filtrar esta caja por tipo" style="margin-bottom:12px">${Object.entries(tipos).sort((a, b) => b[1] - a[1]).slice(0, 6).map(([i, k]) =>
          `<li><a href="explorar.html?caja=${c.n}&tipo=${vocab.get(+i).slug}">${esc(vocab.get(+i).nombre)} <span class="n">${k}</span></a></li>`).join('')}</ul>
        <ol class="lista-resultados" aria-label="Documentos de la caja ${c.n}">${regs.map(r => tarjeta(r)).join('')}</ol>
      </section>
      <nav class="vecinos" aria-label="Otras cajas">
        ${ant ? `<a href="caja.html?n=${ant.n}">← Caja ${ant.n}: ${esc(ant.nombre)}</a>` : '<span></span>'}
        ${sig ? `<a href="caja.html?n=${sig.n}">Caja ${sig.n}: ${esc(sig.nombre)} →</a>` : ''}
      </nav>`;
    const lm = $('#leer-mas');
    if (lm) lm.addEventListener('click', () => {
      const ab = lm.getAttribute('aria-expanded') !== 'true';
      lm.setAttribute('aria-expanded', ab); lm.textContent = ab ? 'Leer menos' : 'Leer más';
      $('#resumen-caja').classList.toggle('plegado', !ab);
    });
    $('.wf-rotulo').insertAdjacentHTML('beforeend', ` · Probar: <a href="caja.html?n=2">Caja 2</a> · <a href="caja.html?n=43">Caja 43 (casi todo sin título)</a> · <a href="caja.html?n=57">Caja 57 (restringidos)</a> · <a href="caja.html?n=17">Caja 17 (obra en 4 cajas)</a>`);
    evento('ver_caja', { caja: c.n });
  }

  // =====================================================================
  // FICHA + VISOR
  // =====================================================================
  function ficha() {
    const cod = (params.get('c') || 'C14R3').toUpperCase();
    const r = registroPorCodigo.get(cod) || registroPorCodigo.get('C14R3');
    const f = D.fichas[r.c] || {};
    const c = cajaPorNum.get(r.k);
    comunes('ficha', '/registro/' + r.c);
    const t = titulo(r);
    document.title = `${t.texto} (${r.c}) · Las cajas de Aída`;
    const enCaja = D.registros.filter(x => x.k === r.k);
    const i = enCaja.indexOf(r), ant = enCaja[i - 1], sig = enCaja[i + 1];
    const archivos = (f.archivos || '').split(';').filter(Boolean).map(a => { const [n, b] = a.split('|'); return { n, mb: Math.round(b / 1e6) }; });
    const volver = sessionStorage.getItem('aida-volver');
    const fila = (dt, dd) => dd ? `<dt>${dt}</dt><dd>${dd}</dd>` : '';
    const vlinks = (ids, fac) => ids.map(id => vocab.get(id)).filter(Boolean).map(v => `<a href="explorar.html?${fac}=${v.slug}">${esc(v.nombre)}</a>`).join(', ');
    let doc;
    if (r.r) {
      doc = `<h2 id="h-doc">Documento</h2><div class="aviso" role="note"><b>Consulta a pedido.</b> La ficha es pública, pero el documento se consulta pidiéndolo al archivo.
        ${r.d ? 'Está digitalizado.' : 'Todavía no está digitalizado.'}</div>
        <div class="vista wf-caja" aria-hidden="true">Sin vista previa</div>
        <div class="acciones"><a class="boton" href="consulta.html?c=${r.c}" id="pedir">Pedir consulta de este documento</a></div>
        <p class="meta">Te respondemos por correo. No hace falta crear una cuenta.</p>`;
    } else if (r.d) {
      const pesado = archivos.some(a => a.mb > 50);
      doc = `<h2 id="h-doc">Documento</h2>
        <div class="vista wf-caja" aria-hidden="true">Primera página (miniatura)</div>
        <div class="acciones"><a class="boton" href="#visor" id="abrir-visor" aria-controls="visor" aria-expanded="false">Ver documento${f.paginas ? ` (${f.paginas} págs.)` : ''}</a>
        ${archivos.map(a => `<a class="boton sec" href="#" data-descarga="${esc(a.n)}">Descargar PDF <span class="meta">(${num(a.mb)} MB)</span></a>`).join('')}</div>
        ${pesado ? '<p class="wf-nota">Archivo pesado: el visor tiene que usar la versión liviana para web (pendiente). La descarga avisa el peso antes.</p>' : ''}`;
    } else {
      doc = `<h2 id="h-doc">Documento</h2><div class="aviso" role="note"><b>Todavía no está digitalizado.</b> El archivo se digitaliza por etapas; por ahora podés ver la ficha del catálogo.</div>
        <div class="vista wf-caja" aria-hidden="true">Sin imagen</div>
        <div class="acciones"><a class="boton sec" href="consulta.html?c=${r.c}&motivo=original">Consultar por este documento</a></div>`;
    }
    $('#contenido').innerHTML = `
      <nav class="migas" aria-label="Estás en"><ol><li><a href="inicio.html">Inicio</a></li><li><a href="caja.html?n=${c.n}">Caja ${c.n}: ${esc(c.nombre)}</a></li><li aria-current="page">${r.c}</li></ol></nav>
      ${volver ? `<p><a href="${esc(volver)}">← Volver a los resultados</a></p>` : ''}
      <div class="cabeza-pagina">
        <p class="dato-num">Caja ${c.n} · Registro ${r.nr} · Código ${r.c}</p>
        <h1>${tituloHTML(r)}</h1>
        ${t.sin ? '<p class="meta">Este documento no tiene título en el catálogo; se lo nombra por su tipo.</p>' : ''}
        <div class="etqs">${etiquetas(r, 5)}</div>
      </div>
      <div class="ficha">
        <div>
          <h2>Ficha del catálogo</h2>
          <dl>
            ${fila('Título', r.t ? esc(f.titulo || r.t) : '<span class="sin-titulo">Sin título</span>')}
            ${fila('Tipo de documento', vlinks(r.tp, 'tipo'))}
            ${fila('Autor/es', esc(f.autor))}
            ${fila('Fecha', esc(f.fecha_texto))}
            ${fila('Año', r.a ? `<a href="explorar.html?decada=${r.dec}">${r.a}</a>` : esc(f.anio_texto || r.at))}
            ${fila('Lugar', esc(f.lugar))}
            ${fila('Páginas', esc(f.paginas))}
            ${fila('Descriptores', vlinks(r.ds, 'descriptor'))}
            ${fila('ISBN', esc(f.isbn))}
            ${fila('Caja', `<a href="caja.html?n=${c.n}">Caja ${c.n}: ${esc(c.nombre)}</a>`)}
            ${fila('Acceso', r.r ? 'Consulta a pedido' : 'Libre')}
          </dl>
          <h2 style="margin-top:24px">Descripción</h2>
          <div class="texto-plegable"><p>${esc(f.resumen || r.x || 'Sin descripción.')}</p></div>
          ${f.obs_editorial ? `<h3>Observaciones</h3><p>${esc(f.obs_editorial)}</p>` : ''}
          <h2 id="citar" style="margin-top:24px">Cómo citar</h2>
          <p id="cita">Bortnik, Aída. ${esc(t.sin ? '[' + t.texto + ']' : t.texto)}. ${esc(f.anio || f.anio_texto || r.a || r.at || 's. f.')}. Archivo «Las cajas de Aída», caja ${c.n}, ${r.c}. https://lascajasdeaida.com.ar/registro/${r.c}</p>
          <div class="acciones"><button class="boton sec" type="button" id="copiar-cita">Copiar cita</button><button class="boton sec" type="button" id="compartir">Copiar enlace</button></div>
          <p role="status" class="meta" id="copiado"></p>
        </div>
        <aside class="documento" aria-labelledby="h-doc">${doc}</aside>
      </div>
      <section id="visor" class="visor" hidden aria-label="Visor del documento ${r.c}">
        <div class="visor-barra" role="toolbar" aria-label="Controles del visor" aria-controls="hoja">
          <button class="boton sec" type="button" data-pag="-1" aria-label="Página anterior">‹</button>
          <label>Página <input type="number" id="pag-actual" value="1" min="1" max="${f.paginas || 1}"></label><span>de ${f.paginas || '?'}</span>
          <button class="boton sec" type="button" data-pag="1" aria-label="Página siguiente">›</button>
          <button class="boton sec" type="button" aria-label="Alejar">−</button><button class="boton sec" type="button" aria-label="Acercar">+</button>
          <button class="boton sec" type="button">Ajustar al ancho</button><button class="boton sec" type="button">Pantalla completa</button>
          <button class="boton sec" type="button" id="cerrar-visor">Cerrar visor</button>
        </div>
        <div class="zona"><div class="hoja wf-caja" id="hoja" role="img" aria-label="Página 1 del documento">Página 1<br>(imagen de la versión web)</div></div>
        <p class="wf-nota">Teclado: ← → cambian de página, + y − zoom, Esc cierra. Sin OCR no hay texto accesible del documento: la ficha y la descripción cumplen esa función (decisión D8).</p>
      </section>
      <nav class="vecinos" aria-label="Documentos vecinos en la caja" style="margin-top:24px">
        ${ant ? `<a href="ficha.html?c=${ant.c}">← ${ant.c}: ${esc(titulo(ant).texto.slice(0, 50))}</a>` : '<span></span>'}
        ${sig ? `<a href="ficha.html?c=${sig.c}">${sig.c}: ${esc(titulo(sig).texto.slice(0, 50))} →</a>` : ''}
      </nav>`;
    $('.wf-rotulo').insertAdjacentHTML('beforeend', ` · Variantes: <a href="ficha.html?c=C14R3">digitalizado</a> · <a href="ficha.html?c=C2R11">a pedido, sin título</a> · <a href="ficha.html?c=C14R12">sin título, sin digitalizar</a> · <a href="ficha.html?c=C2R7">sin título, digitalizado</a>`);
    evento('ver_registro', { codigo: r.c, caja: r.k, tipo: vocab.get(r.tp[0])?.nombre || '', acceso: r.r ? 'a_pedido' : 'libre', digitalizado: r.d ? 'si' : 'no', con_titulo: t.sin ? 'no' : 'si' });
    const av = $('#abrir-visor'), visor = $('#visor');
    if (av) {
      av.addEventListener('click', e => {
        e.preventDefault(); visor.hidden = false; av.setAttribute('aria-expanded', 'true');
        $('#pag-actual').focus(); evento('ver_documento', { codigo: r.c, caja: r.k });
      });
      $('#cerrar-visor').addEventListener('click', () => { visor.hidden = true; av.setAttribute('aria-expanded', 'false'); av.focus(); });
      visor.addEventListener('keydown', e => {
        if (e.key === 'Escape') $('#cerrar-visor').click();
        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
          const p = $('#pag-actual'); p.value = Math.max(1, Math.min(+p.max, +p.value + (e.key === 'ArrowRight' ? 1 : -1)));
          $('#hoja').firstChild.textContent = 'Página ' + p.value; $('#hoja').setAttribute('aria-label', 'Página ' + p.value + ' del documento');
          evento('visor_pagina', { codigo: r.c, pagina: +p.value });
        }
      });
    }
    $$('[data-descarga]').forEach(b => b.addEventListener('click', e => {
      e.preventDefault(); evento('file_download', { file_name: b.dataset.descarga, file_extension: 'pdf', codigo: r.c, caja: r.k });
    }));
    $('#pedir')?.addEventListener('click', () => evento('consulta_iniciada', { codigo: r.c, origen: 'ficha' }));
    $('#copiar-cita').addEventListener('click', () => { navigator.clipboard?.writeText($('#cita').textContent); $('#copiado').textContent = 'Cita copiada.'; evento('cita_copiada', { codigo: r.c }); });
    $('#compartir').addEventListener('click', () => { navigator.clipboard?.writeText('https://lascajasdeaida.com.ar/registro/' + r.c); $('#copiado').textContent = 'Enlace copiado.'; evento('share', { method: 'copiar_enlace', content_type: 'registro', item_id: r.c }); });
  }

  // =====================================================================
  // CONSULTA A PEDIDO
  // =====================================================================
  function consulta() {
    const cod = (params.get('c') || 'C2R11').toUpperCase();
    const r = registroPorCodigo.get(cod) || registroPorCodigo.get('C2R11');
    const c = cajaPorNum.get(r.k);
    comunes('consulta', '/consulta/' + r.c);
    $('#doc-pedido').innerHTML = `<ul class="lista-resultados">${tarjeta(r, 2)}</ul>`;
    if (params.get('motivo') === 'original') $('#h-consulta').textContent = 'Consultar por un documento';
    let iniciada = false;
    $('#form-consulta').addEventListener('focusin', () => { if (!iniciada) { iniciada = true; evento('consulta_iniciada', { codigo: r.c, origen: 'formulario' }); } });
    $('#form-consulta').addEventListener('submit', e => {
      e.preventDefault();
      const errores = [];
      const reglas = [
        ['nombre', v => v.trim().length > 1, 'Escribí tu nombre y apellido.'],
        ['correo', v => /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v), 'Escribí un correo válido, por ejemplo nombre@dominio.com.'],
        ['motivo', v => !!v, 'Elegí para qué querés consultar el documento.'],
        ['mensaje', v => v.trim().length >= 10, 'Contanos en pocas palabras qué necesitás (al menos 10 caracteres).'],
        ['condiciones', (v, el) => el.checked, 'Tenés que aceptar las condiciones de uso para enviar el pedido.'],
      ];
      reglas.forEach(([id, ok, msj]) => {
        const el = $('#' + id), err = $('#' + id + '-error');
        const bien = ok(el.value, el);
        el.setAttribute('aria-invalid', bien ? 'false' : 'true');
        err.textContent = bien ? '' : msj; err.hidden = bien;
        if (!bien) errores.push([id, msj]);
      });
      const res = $('#resumen-errores');
      if (errores.length) {
        res.hidden = false;
        res.innerHTML = `<h2 tabindex="-1">Revisá ${errores.length === 1 ? 'un dato' : errores.length + ' datos'} antes de enviar</h2><ul>${errores.map(([id, m]) => `<li><a href="#${id}">${m}</a></li>`).join('')}</ul>`;
        $('h2', res).focus();
        evento('consulta_error_validacion', { codigo: r.c, campos: errores.map(x => x[0]).join(',') });
        return;
      }
      const motivo = $('#motivo').value;
      $('#zona-form').innerHTML = `<div class="estado" role="status"><h2 tabindex="-1" id="ok">Recibimos tu pedido</h2>
        <p>Número de consulta: <b>AB-2026-0042</b>. Te mandamos una copia a tu correo.</p>
        <p>El equipo del archivo te va a responder en un plazo de <b>[a definir]</b> días hábiles.</p>
        <p><a href="ficha.html?c=${r.c}">Volver al documento</a> · <a href="explorar.html">Seguir explorando</a></p></div>`;
      $('#ok').focus();
      evento('consulta_enviada', { codigo: r.c, caja: r.k, motivo }); // sin datos personales
    });
    $('.wf-rotulo').insertAdjacentHTML('beforeend', ' · Probá enviar el formulario vacío para ver los errores.');
  }

  function sobre() { comunes('sobre', '/sobre-el-archivo'); }

  window.WF = { inicio, cajas, explorar, caja, ficha, consulta, sobre };
})();
