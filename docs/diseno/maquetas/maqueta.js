/* Maquetas del sitio público «Las cajas de Aída» — Diseño web.
   Parte del comportamiento de los wireframes de UX (docs/ux/wireframes/wf.js) con el marcado de los componentes
   de docs/diseno/componentes.md. Datos: docs/ux/wireframes/datos.js (extracto real de la base) y
   vocabulario-final.js (lista final propuesta por Datos y catálogo).
   No es el código de producción: sirve para ver el diseño con datos reales y para que Programación copie el HTML. */
(function () {
  'use strict';
  const D = window.AIDA;
  const V = window.AIDA_VOCAB;
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const num = n => Number(n).toLocaleString('es-AR');
  const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const norm = s => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  const slug = s => norm(s).replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  const params = new URLSearchParams(location.search);

  // ---------- Íconos: sprite SVG propio (trazo 1,75, caja 24×24) ----------
  const ICONOS = {
    buscar: '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21"/>',
    menu: '<path d="M4 7h16M4 12h16M4 17h16"/>',
    cerrar: '<path d="M6 6l12 12M18 6 6 18"/>',
    filtro: '<path d="M4 6h16M7 12h10M10 18h4"/>',
    abajo: '<path d="m6 9 6 6 6-6"/>',
    izq: '<path d="m15 6-6 6 6 6"/>',
    der: '<path d="m9 6 6 6-6 6"/>',
    flechaIzq: '<path d="M19 12H5M11 6l-6 6 6 6"/>',
    flechaDer: '<path d="M5 12h14M13 6l6 6-6 6"/>',
    candado: '<rect x="5" y="10.5" width="14" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/><path d="M12 14.5v2.5"/>',
    documento: '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 12.5h6M10 16h6"/>',
    ficha: '<rect x="3.5" y="5.5" width="17" height="13" rx="1.5"/><path d="M7 9.5h6M7 12.5h10M7 15.5h8"/>',
    caja: '<path d="M3.5 8.5h17v11.5h-17z"/><path d="M2.5 4.5h19v4h-19z"/><path d="M9.5 12.5h5"/>',
    descargar: '<path d="M12 4v11M7 10.5l5 5 5-5"/><path d="M5 19.5h14"/>',
    ver: '<path d="M2.5 12s3.5-6.5 9.5-6.5 9.5 6.5 9.5 6.5-3.5 6.5-9.5 6.5S2.5 12 2.5 12z"/><circle cx="12" cy="12" r="2.75"/>',
    mas: '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21M10.5 8v5M8 10.5h5"/>',
    menos: '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21M8 10.5h5"/>',
    ancho: '<path d="M3 12h18M7 8l-4 4 4 4M17 8l4 4-4 4"/>',
    pantalla: '<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>',
    copiar: '<rect x="8.5" y="8.5" width="11.5" height="11.5" rx="1.5"/><path d="M15.5 8.5V5.5A1.5 1.5 0 0 0 14 4H5.5A1.5 1.5 0 0 0 4 5.5V14a1.5 1.5 0 0 0 1.5 1.5h3"/>',
    enlace: '<path d="M10 14a4 4 0 0 0 5.66 0l3-3a4 4 0 0 0-5.66-5.66l-1 1"/><path d="M14 10a4 4 0 0 0-5.66 0l-3 3a4 4 0 0 0 5.66 5.66l1-1"/>',
    duda: '<circle cx="12" cy="12" r="9"/><path d="M9.6 9.3a2.5 2.5 0 0 1 4.86.83c0 1.67-2.46 2.2-2.46 3.6"/><path d="M12 17h.01"/>',
    check: '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
    alerta: '<path d="M12 3.5 2.5 20h19z"/><path d="M12 10v4.5M12 17.5h.01"/>',
    sobre: '<rect x="3" y="5.5" width="18" height="13" rx="1.5"/><path d="m3.5 6.5 8.5 7 8.5-7"/>',
    tiempo: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
    sello: '<rect x="3" y="7" width="18" height="14" rx="1"/><path d="M1.5 3h21v4h-21z"/><path d="M8 11h8v3.5H8z"/>',
  };
  function sprite() {
    return `<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">${Object.entries(ICONOS)
      .map(([k, d]) => `<symbol id="i-${k}" viewBox="0 0 24 24">${d}</symbol>`).join('')}</svg>`;
  }
  const ic = (k, extra = '') => `<svg class="icono ${extra}" aria-hidden="true" focusable="false"><use href="#i-${k}"/></svg>`;

  // ---------- Vocabulario final (propuesta de Datos y catálogo) ----------
  const vocab = new Map(); // clave 'tipo:13' → término
  const TIPOS = V.tipos.map(t => ({ ...t, faceta: 'tipo', slug: slug(t.nombre) }));
  const DESCR = V.descriptores.map(t => ({ ...t, faceta: 'descriptor', slug: slug(t.nombre) }));
  TIPOS.forEach(t => vocab.set('tipo:' + t.id, t));
  DESCR.forEach(t => vocab.set('descriptor:' + t.id, t));
  function remapear(r) {
    const tp = new Set(), ds = new Set();
    const poner = d => (d.v === 'tipo' ? tp : ds).add(d.id);
    r.tp.forEach(id => (V.mapa.tipo[id] || []).forEach(poner));
    r.ds.forEach(id => {
      const m = V.mapa.descriptor[id];
      if (m === 'novelas') {
        if (V.novelas.literatura.includes(r.c)) ds.add(66);
        if (V.novelas.periodismo.includes(r.c)) ds.add(901);
      } else (m || []).forEach(poner);
    });
    r.tp = [...tp].filter(id => vocab.has('tipo:' + id));
    r.ds = [...ds].filter(id => vocab.has('descriptor:' + id));
  }
  const nombreTipo = id => vocab.get('tipo:' + id)?.nombre;

  // ---------- Etapas del sitio actual ----------
  const ETAPAS = [
    { v: 'inicios', nombre: 'Inicios', desde: 1963, hasta: 1976 },
    { v: 'exilio', nombre: 'Exilio', desde: 1976, hasta: 1979 },
    { v: 'regreso', nombre: 'Regreso', desde: 1979, hasta: 2013 },
    { v: 'caos-creativo', nombre: 'Caos Creativo', desde: 1963, hasta: 2009 },
    { v: 'sobre-aida', nombre: 'Sobre Aída', desde: 1938, hasta: 2013 },
  ];
  // Hoy ninguna caja tiene etapa (tabla caja_periodo vacía). Para ver el diseño con datos, la maqueta SIMULA
  // la asignación de las tres etapas cronológicas por el año del documento. «?etapas=sin-asignar» muestra el estado real.
  const etapasAsignadas = params.get('etapas') !== 'sin-asignar';
  const SIMULADAS = ['inicios', 'exilio', 'regreso'];

  // ---------- Índices ----------
  const cajaPorNum = new Map(D.cajas.map(c => [c.n, c]));
  const registroPorCodigo = new Map(D.registros.map(r => [r.c, r]));
  D.registros.forEach(r => {
    remapear(r);
    r.dec = r.a ? String(Math.floor(r.a / 10) * 10) : 'sin-fecha';
    r.et = etapasAsignadas && r.a ? ETAPAS.filter(e => SIMULADAS.includes(e.v) && r.a >= e.desde && r.a <= e.hasta).map(e => e.v) : [];
    r.heno = norm([r.c, r.t, r.x, r.au, r.a, r.at, cajaPorNum.get(r.k)?.nombre,
      ...r.tp.map(nombreTipo), ...r.ds.map(i => vocab.get('descriptor:' + i)?.nombre)].join(' '));
    r.tnorm = norm(r.t);
  });
  TIPOS.forEach(t => t.n = D.registros.filter(r => r.tp.includes(t.id)).length);
  DESCR.forEach(t => t.n = D.registros.filter(r => r.ds.includes(t.id)).length);

  // ---------- Eventos para Estadísticas (los mismos de UX; acá solo se acumulan) ----------
  window.dataLayer = window.dataLayer || [];
  const evento = (nombre, datos = {}) => window.dataLayer.push({ event: nombre, ...datos });

  // ---------- Textos derivados de los datos ----------
  function titulo(r) {
    if (r.t) return { texto: r.t, sin: false };
    const tipos = r.tp.map(nombreTipo).filter(Boolean);
    return { texto: tipos.length ? tipos.join(' · ') : 'Documento sin título', sin: true };
  }
  function tituloHTML(r) {
    const t = titulo(r);
    return t.sin ? `<span class="sin-titulo">${esc(t.texto)}</span><span class="solo-lector"> (sin título)</span>` : esc(t.texto);
  }
  /* Año: limpio → número; con «?» → «1972 (sin confirmar)» marcado; rango («1981/82») → tal cual. */
  function anioInfo(r, texto) {
    const at = texto ?? r.at;
    if (r.a) return { texto: String(r.a), dudoso: false };
    if (!at || /^sin (dato|fecha)$/i.test(at.trim())) return { texto: 'Sin fecha', dudoso: false, sin: true };
    if (/\?/.test(at)) return { texto: at.replace(/[¿?()]/g, '').replace(/\s+/g, ' ').trim(), dudoso: true, original: at };
    return { texto: at, dudoso: false };
  }
  function anioHTML(r, texto) {
    const a = anioInfo(r, texto);
    if (a.dudoso) return `<span class="anio-dudoso">${esc(a.texto)} (sin confirmar)${ic('duda')}</span>`;
    return esc(a.texto);
  }
  function etiquetas(r, max = 2) {
    let h = r.d
      ? `<span class="etq etq--digital">${ic('documento')}Documento digital</span>`
      : `<span class="etq etq--ficha">${ic('ficha')}Solo ficha</span>`;
    if (r.r) h += `<span class="etq etq--pedido">${ic('candado')}Consulta a pedido</span>`;
    r.tp.slice(0, max).forEach(i => { h += `<span class="etq etq--tipo">${esc(nombreTipo(i))}</span>`; });
    return h;
  }
  function mini(r) {
    if (r.r) return `<div class="mini mini--pedido" aria-hidden="true">${ic('candado')}</div>`;
    if (r.d) return `<div class="mini mini--hoja" aria-hidden="true"></div>`;
    return `<div class="mini mini--vacia" aria-hidden="true">${ic('ficha')}</div>`;
  }
  function tarjeta(r, nivel = 3, etiqueta = 'li') {
    const c = cajaPorNum.get(r.k);
    const t = titulo(r);
    return `<${etiqueta} class="registro" data-codigo="${r.c}">
      ${mini(r)}
      <div class="registro__cuerpo">
        <h${nivel}><a href="ficha.html?c=${r.c}" data-evento-tarjeta>${tituloHTML(r)}</a></h${nivel}>
        <p class="registro__datos">${t.sin ? 'Sin título · ' : ''}<span class="codigo">${r.c}</span> · Caja ${r.k}: ${esc(c?.nombre)} · ${anioHTML(r)}</p>
        ${r.x ? `<p class="registro__extracto">${esc(r.x)}</p>` : ''}
        <div class="etqs">${etiquetas(r)}</div>
      </div></${etiqueta}>`;
  }
  function tarjetaCaja(c, nivel = 3) {
    const pct = c.regs ? Math.round(c.digit / c.regs * 100) : 0;
    const anios = c.desde ? c.desde + (c.hasta !== c.desde ? '–' + c.hasta : '') : '';
    return `<li><article class="caja">
      <p class="caja__num">Caja <b>${c.n}</b></p>
      <h${nivel}><a href="caja.html?n=${c.n}">${esc(c.nombre)}</a></h${nivel}>
      <p class="caja__datos">${num(c.regs)} documentos${anios ? ' · ' + anios : ''}${c.restr ? ` · ${c.restr} a pedido` : ''}</p>
      <div class="caja__pie"><div class="avance">
        <div class="avance__barra" role="img" aria-label="${pct} % digitalizado"><i style="width:${pct}%"></i></div>
        <span>${c.digit ? num(c.digit) + ' digitalizados' : 'Sin digitalizar todavía'}</span></div></div>
    </article></li>`;
  }

  // ---------- Partes comunes ----------
  function marcaHTML() {
    return `<svg class="marca__sello" viewBox="0 0 40 40" aria-hidden="true" focusable="false"><g fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round">
      <path d="M5 14h30v21H5z"/><path d="M3 8h34v6H3z"/><path d="M14 20h12v5H14z"/></g></svg>
      <span class="marca__texto">Las cajas <em>de Aída</em></span>`;
  }
  function comunes(pagina, notas = []) {
    document.body.insertAdjacentHTML('afterbegin', `${sprite()}
      <a class="saltar" href="#contenido">Saltar al contenido</a>
      <header class="cabecera"><div class="contenedor">
        <a class="marca" href="inicio.html" aria-label="Las cajas de Aída, inicio">${marcaHTML()}</a>
        <a class="cabecera__accion cabecera__buscar" href="explorar.html#q">${ic('buscar')}<span class="texto-largo">Buscar</span><span class="solo-lector">Buscar en el archivo</span></a>
        <button class="cabecera__accion menu-boton" type="button" aria-expanded="false" aria-controls="nav-principal">${ic('menu')}Menú</button>
        <nav class="nav-principal" id="nav-principal" aria-label="Principal"><ul>
          <li><a href="inicio.html" ${pagina === 'inicio' ? 'aria-current="page"' : ''}>Inicio</a></li>
          <li><a href="explorar.html" ${pagina === 'explorar' ? 'aria-current="page"' : ''}>Explorar el archivo</a></li>
          <li><a href="cajas.html" ${pagina === 'cajas' ? 'aria-current="page"' : ''}>Las 70 cajas</a></li>
          <li><a href="sobre.html" ${pagina === 'sobre' ? 'aria-current="page"' : ''}>Sobre el archivo</a></li>
        </ul></nav>
      </div></header>`);
    document.body.insertAdjacentHTML('beforeend', `
      <footer class="pie"><div class="contenedor">
        <span class="marca__texto">Las cajas <em style="color:var(--c-ambar)">de Aída</em></span>
        <p>Archivo Aída Bortnik</p>
        <ul><li><a href="sobre.html">Sobre el archivo</a></li><li><a href="sobre.html#citar">Cómo citar</a></li>
          <li><a href="sobre.html#condiciones">Condiciones de uso</a></li><li><a href="sobre.html#accesibilidad">Accesibilidad</a></li><li><a href="sobre.html#contacto">Contacto</a></li></ul>
      </div></footer>
      <details class="notas-maqueta"><summary>Notas de la maqueta</summary><div><ul>
        <li>Datos: extracto real de la base generado el ${esc(D.generado)} (<code>docs/ux/wireframes/datos.js</code>). Tipos y descriptores con la lista final propuesta por Datos y catálogo.</li>
        ${notas.map(n => `<li>${n}</li>`).join('')}
        <li><a href="index.html">Índice de maquetas</a> · <a href="componentes.html">Catálogo de componentes</a></li>
      </ul></div></details>`);
    const mb = $('.menu-boton');
    mb.addEventListener('click', () => {
      const abierto = mb.getAttribute('aria-expanded') !== 'true';
      mb.setAttribute('aria-expanded', abierto); $('#nav-principal').classList.toggle('abierta', abierto);
    });
    document.addEventListener('click', e => {
      const a = e.target.closest('a[data-evento-tarjeta]');
      if (a) evento('select_content', { content_type: 'registro', item_id: a.closest('[data-codigo]').dataset.codigo, origen: pagina });
    });
  }
  const migas = items => `<nav class="migas" aria-label="Estás en"><ol>${items.map((it, i) => i === items.length - 1
    ? `<li aria-current="page">${it[0]}</li>` : `<li><a href="${it[1]}">${it[0]}</a></li>`).join('')}</ol></nav>`;

  // =====================================================================
  // INICIO
  // =====================================================================
  function inicio() {
    comunes('inicio', [
      'Hero: kicker, título y frase son los del sitio actual. La ilustración es SVG propio (rollo de film, hoja mecanografiada con corrección a mano, teclas): no hay fotos.',
      'La línea de «qué es» del archivo que pide UX (§3, punto 1) queda para el texto del cliente; por ahora la frase del sitio cumple esa función.',
      etapasAsignadas ? 'Etapas: <b>conteos simulados</b>. Hoy ninguna caja tiene etapa. La maqueta asigna Inicios, Exilio y Regreso por el año del documento y deja Caos Creativo y Sobre Aída sin asignar, para mostrar los dos estados. <a href="inicio.html?etapas=sin-asignar#h-etapas">Ver el estado real (sin asignar)</a>.'
        : 'Etapas en su estado real: ninguna caja tiene etapa asignada. <a href="inicio.html#h-etapas">Ver con asignación simulada</a>.',
      'Si el cliente prefiere otro orden de bloques, cada sección es un componente independiente (ver componentes.md).',
    ]);
    const T = D.totales;
    $('#cifras').innerHTML = `
      <li><b>${num(T.cajas)}</b> cajas</li><li><b>${num(T.regs)}</b> documentos catalogados</li>
      <li><b>${num(T.digit)}</b> ya digitalizados</li><li><b>${T.desde}–${T.hasta}</b> años que abarca</li>`;
    // etapas
    const min = 1938, max = 2013, pos = a => ((a - min) / (max - min) * 100).toFixed(1);
    $('#etapas').innerHTML = ETAPAS.map(e => {
      const n = D.registros.filter(r => r.et.includes(e.v)).length;
      const asignada = n > 0;
      const nombre = asignada ? `<a href="explorar.html?etapa=${e.v}">${esc(e.nombre)}</a>` : esc(e.nombre);
      return `<li><article class="etapa ${asignada ? '' : 'etapa--sin-asignar'}">
        <h3>${nombre}</h3>
        <p class="etapa__anios">${e.desde}–${e.hasta}</p>
        <div class="tiempo" role="img" aria-label="De ${e.desde} a ${e.hasta}, dentro de 1938–2013"><i style="left:${pos(e.desde)}%;width:${pos(e.hasta) - pos(e.desde)}%"></i></div>
        <p class="etapa__n">${asignada ? `${num(n)} documentos` : 'Sin cajas asignadas'}</p>
      </article></li>`;
    }).join('');
    $('#por-tipo').innerHTML = [...TIPOS].sort((a, b) => b.n - a.n).slice(0, 10).map(v =>
      `<li><a class="acceso" href="explorar.html?tipo=${v.slug}">${esc(v.nombre)} <span class="n">${num(v.n)}</span></a></li>`).join('') +
      `<li><a class="acceso acceso--todos" href="explorar.html#faceta-tipo">Todos los tipos (${TIPOS.length})</a></li>`;
    const dec = {};
    D.registros.forEach(r => dec[r.dec] = (dec[r.dec] || 0) + 1);
    $('#por-decada').innerHTML = Object.keys(dec).sort().map(k =>
      `<li><a class="acceso" href="explorar.html?decada=${k}">${k === 'sin-fecha' ? 'Sin fecha' : 'Década de ' + k} <span class="n">${num(dec[k])}</span></a></li>`).join('');
    const vistos = new Set();
    const digit = D.registros.filter(r => r.d && !r.r && r.t && !vistos.has(r.k) && vistos.add(r.k)).slice(0, 6);
    $('#digitalizados').innerHTML = digit.map(r => tarjeta(r)).join('');
    $('#cajas-destacadas').innerHTML = D.cajas.slice(0, 9).map(c => tarjetaCaja(c)).join('');
    $('#form-inicio').addEventListener('submit', () => evento('search', { search_term: $('#q-inicio').value, origen: 'inicio' }));
  }

  // =====================================================================
  // LISTADO DE CAJAS
  // =====================================================================
  function cajas() {
    comunes('cajas', ['La tarjeta de caja imita el rótulo de una caja de archivo: pestaña superior, número en serif naranja y barra de avance de digitalización (verde = lo que ya se puede leer).']);
    const orden = $('#orden-cajas'), filtro = $('#filtro-cajas');
    const pintar = () => {
      const q = norm(filtro.value);
      let l = D.cajas.filter(c => !q || norm(c.nombre + ' ' + c.n).includes(q));
      if (orden.value === 'nombre') l = [...l].sort((a, b) => norm(a.nombre).localeCompare(norm(b.nombre)));
      if (orden.value === 'digit') l = [...l].sort((a, b) => b.digit - a.digit || a.n - b.n);
      $('#lista-cajas').innerHTML = l.map(c => tarjetaCaja(c, 2)).join('');
      $('#conteo-cajas').textContent = l.length === D.cajas.length ? `${l.length} cajas` : `${l.length} de ${D.cajas.length} cajas`;
    };
    filtro.addEventListener('input', pintar); orden.addEventListener('change', pintar); pintar();
  }

  // =====================================================================
  // EXPLORAR (búsqueda + filtros combinables) — misma lógica que el wireframe
  // =====================================================================
  const POR_PAGINA = 20;
  const FACETAS = [
    { clave: 'disponible', titulo: 'Disponibilidad', abierta: true, ordenFijo: true, opciones: () => [
      { v: 'digital', nombre: 'Con documento digital' }, { v: 'ficha', nombre: 'Solo ficha (sin digitalizar)' }],
      prueba: (r, v) => v === 'digital' ? !!r.d : !r.d },
    { clave: 'acceso', titulo: 'Acceso', abierta: false, ordenFijo: true, opciones: () => [
      { v: 'libre', nombre: 'Consulta libre' }, { v: 'a-pedido', nombre: 'Consulta a pedido' }],
      prueba: (r, v) => v === 'a-pedido' ? !!r.r : !r.r },
    { clave: 'tipo', titulo: 'Tipo de documento', abierta: true, buscar: true, grupos: true, opciones: () => TIPOS.map(t => ({ v: t.slug, nombre: t.nombre, id: t.id, grupo: t.grupo })),
      prueba: (r, v, o) => r.tp.includes(o.id) },
    { clave: 'decada', titulo: 'Año', abierta: true, rango: true, ordenFijo: true, opciones: () => {
      const s = new Set(D.registros.map(r => r.dec));
      return [...s].sort().map(k => ({ v: k, nombre: k === 'sin-fecha' ? 'Sin fecha' : 'Década de ' + k }));
    }, prueba: (r, v) => r.dec === v },
    { clave: 'etapa', titulo: 'Etapa', abierta: false, ordenFijo: true, etapa: true, opciones: () => ETAPAS.map(e => ({ v: e.v, nombre: `${e.nombre} (${e.desde}–${e.hasta})` })),
      prueba: (r, v) => r.et.includes(v) },
    { clave: 'caja', titulo: 'Caja / obra', abierta: false, buscar: true, ordenFijo: true, opciones: () => D.cajas.map(c => ({ v: String(c.n), nombre: `${c.n}. ${c.nombre}` })),
      prueba: (r, v) => String(r.k) === v },
    { clave: 'descriptor', titulo: 'Descriptores (temas)', abierta: false, buscar: true, grupos: true, opciones: () => DESCR.map(t => ({ v: t.slug, nombre: t.nombre, id: t.id, grupo: t.grupo })),
      prueba: (r, v, o) => r.ds.includes(o.id) },
  ];
  FACETAS.forEach(f => { f.lista = f.opciones(); f.porValor = new Map(f.lista.map(o => [o.v, o])); });

  function explorar() {
    comunes('explorar', [
      'Facetas en el orden de UX, con «Etapa» después de «Año» porque es la otra forma cronológica de recorrer el archivo (y la que usa el sitio actual).',
      'Tipos y descriptores se agrupan como en la lista final de Datos y catálogo (Escritura y obra, Prensa, Correspondencia…). Ordenados por cantidad cuando se ven todos o se busca adentro.',
      'Estados: <a href="explorar.html?q=xyzzy">sin resultados</a> · <a href="explorar.html?tipo=carta&amp;decada=1940">combinación vacía</a> · <a href="explorar.html?simular=cargando">cargando</a> · <a href="explorar.html?simular=error">error</a> · <a href="explorar.html?panel=abierto">panel abierto (celular)</a> · <a href="explorar.html?etapas=sin-asignar">etapas sin asignar</a>.',
    ]);
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
        if (s.size && ![...s].some(v => f.prueba(r, v, f.porValor.get(v)))) return false;
      }
      if (excepto !== 'decada' && (estado.desde || estado.hasta)) {
        if (!r.a) return false;
        if (estado.desde && r.a < estado.desde) return false;
        if (estado.hasta && r.a > estado.hasta) return false;
      }
      return true;
    }
    function puntaje(r) {
      const q = norm(estado.q); let p = 0;
      if (r.tnorm.includes(q)) p += 10;
      q.split(/\s+/).forEach(w => { if (r.tnorm.includes(w)) p += 3; });
      return p + (r.d ? 1 : 0);
    }
    function ordenar(l) {
      const o = estado.orden, porCaja = (a, b) => a.k - b.k || a.nr - b.nr;
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
      if (!etapasAsignadas) p.set('etapas', 'sin-asignar');
      const s = p.toString().replace(/%2C/g, ',');
      history.replaceState(null, '', (s ? '?' + s : location.pathname) + location.hash);
      sessionStorage.setItem('aida-volver', location.href);
    }
    function activos() {
      const l = [];
      if (estado.q) l.push({ f: 'q', faceta: 'Búsqueda', valor: `«${estado.q}»`, quitar: () => { estado.q = ''; $('#q').value = ''; }, v: estado.q });
      FACETAS.forEach(f => estado.sel[f.clave].forEach(v => l.push({ f: f.clave, faceta: f.titulo, valor: f.porValor.get(v).nombre, quitar: () => estado.sel[f.clave].delete(v), v })));
      if (estado.desde || estado.hasta) l.push({ f: 'rango', faceta: 'Años', valor: `${estado.desde || '…'}–${estado.hasta || '…'}`, quitar: () => { estado.desde = estado.hasta = null; }, v: `${estado.desde}-${estado.hasta}` });
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
        const sinAsignar = f.etapa && !D.registros.some(r => r.et.length);
        let ops = sinAsignar ? f.lista : f.lista.filter(o => cuenta.get(o.v) > 0 || estado.sel[f.clave].has(o.v));
        if (f.etapa && !sinAsignar) ops = f.lista.filter(o => D.registros.some(r => r.et.includes(o.v)) || estado.sel[f.clave].has(o.v));
        if (!f.ordenFijo) ops.sort((a, b) => cuenta.get(b.v) - cuenta.get(a.v));
        const busq = norm(valoresBusq[f.clave] || '');
        if (busq) ops = ops.filter(o => norm(o.nombre).includes(busq));
        const todas = f.rango || f.etapa || f.clave === 'disponible' || f.clave === 'acceso' || estado.abiertasMas.has(f.clave) || busq;
        const visibles = todas ? ops : ops.filter((o, i) => i < 8 || estado.sel[f.clave].has(o.v));
        // agrupar solo cuando se ven todas y sin búsqueda interna
        const agrupar = f.grupos && estado.abiertasMas.has(f.clave) && !busq;
        let lista = visibles;
        if (agrupar) { const g = [...new Set(f.lista.map(o => o.grupo))]; lista = g.flatMap(gr => visibles.filter(o => o.grupo === gr)); }
        const n = estado.sel[f.clave].size + (f.rango && (estado.desde || estado.hasta) ? 1 : 0);
        const max = Math.max(1, ...ops.map(o => cuenta.get(o.v)));
        const abierta = primera ? (f.abierta || n > 0 || location.hash === '#faceta-' + f.clave) : abiertos.has(f.clave);
        let grupoActual = null;
        return `<details class="faceta" data-clave="${f.clave}" id="faceta-${f.clave}" ${abierta ? 'open' : ''}>
          <summary><span>${f.titulo}</span>${n ? `<span class="faceta__n">${n}<span class="solo-lector"> elegido${n > 1 ? 's' : ''}</span></span>` : ''}${ic('abajo')}</summary>
          <fieldset><legend>${f.titulo}</legend>
          ${sinAsignar ? '<p class="faceta__aviso">Todavía no hay cajas asignadas a las etapas.</p>' : ''}
          ${f.rango ? `<div class="histo" aria-hidden="true">${ops.filter(o => o.v !== 'sin-fecha').map(o => `<span class="${estado.sel.decada.has(o.v) ? 'elegida' : ''}" style="height:${Math.round(cuenta.get(o.v) / max * 100)}%"></span>`).join('')}</div>` : ''}
          ${f.buscar ? `<label class="solo-lector" for="buscar-${f.clave}">Buscar en ${f.titulo}</label>
            <input class="buscar-en-faceta" id="buscar-${f.clave}" data-clave="${f.clave}" type="search" placeholder="Buscar ${f.titulo.toLowerCase().replace(/ \(.*\)/, '')}…" value="${esc(valoresBusq[f.clave] || '')}">` : ''}
          ${lista.map((o, i) => {
            const id = `f-${f.clave}-${i}`, c = cuenta.get(o.v);
            const cab = agrupar && o.grupo !== grupoActual ? (grupoActual = o.grupo, `<p class="grupo-opciones" aria-hidden="true">${esc(o.grupo)}</p>`) : '';
            return `${cab}<label class="opcion" for="${id}"><input type="checkbox" id="${id}" data-f="${f.clave}" value="${esc(o.v)}" ${estado.sel[f.clave].has(o.v) ? 'checked' : ''} ${sinAsignar ? 'disabled' : ''}>
              <span>${esc(o.nombre)}</span><span class="n">${sinAsignar ? '—' : num(c)}<span class="solo-lector"> resultados</span></span></label>`;
          }).join('') || '<p class="meta">Ninguna opción coincide.</p>'}
          ${!todas && ops.length > visibles.length ? `<button type="button" class="boton boton--fantasma boton--chico" data-mas="${f.clave}">Ver ${ops.length - visibles.length} más</button>` : ''}
          ${f.rango ? `<div class="rango"><label>Desde <input type="number" inputmode="numeric" id="desde" min="1940" max="2010" value="${estado.desde || ''}" placeholder="${D.totales.desde}"></label>
            <label>Hasta <input type="number" inputmode="numeric" id="hasta" min="1940" max="2010" value="${estado.hasta || ''}" placeholder="${D.totales.hasta}"></label>
            <button type="button" class="boton boton--secundario" id="aplicar-rango">Aplicar</button></div>
            <p class="meta" style="margin-top:8px">Los documentos «Sin fecha» quedan fuera al elegir un rango de años.</p>` : ''}
          </fieldset></details>`;
      }).join('');
      if (foco && document.getElementById(foco)) document.getElementById(foco).focus();
    }

    function pintarResultados(enfocarDesde) {
      const zona = $('#resultados');
      if (simular === 'error') {
        $('#conteo').textContent = 'Resultados';
        zona.innerHTML = `<div class="estado estado--error" role="alert"><h2>${ic('alerta')} No pudimos cargar los resultados</h2>
          <p>Hubo un problema de conexión con el archivo. Tus filtros siguen guardados.</p>
          <button class="boton boton--primario" type="button" onclick="location.search=''">Reintentar</button></div>`;
        return;
      }
      if (simular === 'cargando') {
        $('#conteo').textContent = `${num(D.registros.length)} documentos en el archivo`;
        zona.setAttribute('aria-busy', 'true');
        zona.innerHTML = '<p class="meta">Cargando resultados…</p><ul class="lista-registros">' + '<li class="esqueleto"></li>'.repeat(5) + '</ul>'; return;
      }
      const lista = ordenar(D.registros.filter(r => cumple(r)));
      const total = lista.length, ac = activos();
      $('#conteo').textContent = total === D.registros.length && !ac.length
        ? `${num(total)} documentos en el archivo` : `${num(total)} ${total === 1 ? 'resultado' : 'resultados'}`;
      const nf = ac.filter(a => a.f !== 'q').length;
      $('#boton-filtros-n').textContent = nf ? nf : '';
      $('#ver-n').textContent = num(total);
      $('#chips').innerHTML = ac.map((a, i) => `<li><button type="button" class="chip" data-quitar="${i}">${esc(a.faceta)}: <b>${esc(a.valor)}</b><span class="chip__x">${ic('cerrar')}</span><span class="solo-lector">, quitar filtro</span></button></li>`).join('') +
        (ac.length > 1 ? '<li><button type="button" class="boton boton--fantasma boton--chico" data-limpiar>Limpiar todo</button></li>' : '');
      $('#chips').hidden = !ac.length;
      $('#orden').querySelector('[value=relevancia]').disabled = !estado.q;
      $('#orden').value = estado.orden;
      if (!total) {
        zona.innerHTML = `<div class="estado" id="sin-resultados"><h2>No encontramos documentos con esa combinación</h2>
          <p>Probá quitar alguno de los filtros:</p><ul>${quitarSugerencias(ac)}</ul>
          <p>O buscá en <a href="explorar.html?q=${encodeURIComponent(estado.q)}">todo el archivo</a>. Muchos documentos no tienen título: buscar por tipo («carta», «guion») o por la obra suele dar más resultados.</p></div>`;
        return;
      }
      const hasta = Math.min(total, estado.pagina * POR_PAGINA);
      zona.removeAttribute('aria-busy');
      zona.innerHTML = `<ul class="lista-registros" id="lista">${lista.slice(0, hasta).map(r => tarjeta(r)).join('')}</ul>
        <div class="paginacion">
          <p class="meta" id="mostrando">Mostrando ${num(hasta)} de ${num(total)}</p>
          <progress max="${total}" value="${hasta}" aria-labelledby="mostrando"></progress>
          ${hasta < total ? `<a class="boton boton--secundario" id="mas" href="?pagina=${estado.pagina + 1}">Mostrar ${Math.min(POR_PAGINA, total - hasta)} más</a>` : '<p class="meta">Llegaste al final de la lista.</p>'}
        </div>`;
      if (enfocarDesde != null) { const li = $$('#lista > li')[enfocarDesde]; if (li) $('a', li).focus(); }
    }
    function quitarSugerencias(ac) {
      return ac.map((a, i) => {
        const guard = JSON.stringify([estado.q, Object.entries(estado.sel).map(([k, s]) => [k, [...s]]), estado.desde, estado.hasta]);
        a.quitar(); const n = D.registros.filter(r => cumple(r)).length;
        const g = JSON.parse(guard); estado.q = g[0]; g[1].forEach(([k, l]) => estado.sel[k] = new Set(l)); estado.desde = g[2]; estado.hasta = g[3]; $('#q').value = estado.q;
        return `<li><button type="button" class="boton boton--fantasma" data-quitar="${i}">Quitar «${esc(a.faceta)}: ${esc(a.valor)}»</button> → ${num(n)} resultados</li>`;
      }).join('');
    }
    let anuncio;
    function actualizar(motivo) {
      estado.pagina = motivo === 'mas' ? estado.pagina : 1;
      pintarFacetas(); pintarResultados(); aURL();
      clearTimeout(anuncio);
      anuncio = setTimeout(() => { $('#anuncio').textContent = $('#conteo').textContent; }, 600);
    }
    $('#form-buscar').addEventListener('submit', e => {
      e.preventDefault(); estado.q = $('#q').value.trim();
      const exacto = registroPorCodigo.get(estado.q.toUpperCase());
      if (exacto) { location.href = 'ficha.html?c=' + exacto.c; return; }
      estado.orden = estado.q ? 'relevancia' : (estado.orden === 'relevancia' ? 'caja' : estado.orden);
      evento('search', { search_term: estado.q, origen: 'explorar' }); actualizar(); $('#conteo').focus();
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
      if (m) { const k = m.dataset.mas; estado.abiertasMas.add(k); pintarFacetas(); const ops = $$(`#faceta-${k} input[type=checkbox]`); ops[Math.min(8, ops.length - 1)]?.focus(); }
      if (e.target.id === 'aplicar-rango') {
        estado.desde = parseInt($('#desde').value) || null; estado.hasta = parseInt($('#hasta').value) || null;
        if (estado.desde && estado.hasta && estado.desde > estado.hasta) [estado.desde, estado.hasta] = [estado.hasta, estado.desde];
        actualizar();
      }
    });
    document.addEventListener('click', e => {
      const q = e.target.closest('[data-quitar]');
      if (q) { const a = activos()[+q.dataset.quitar]; a.quitar(); actualizar(); ($('#chips button') || $('#conteo')).focus(); }
      if (e.target.closest('[data-limpiar]')) {
        FACETAS.forEach(f => estado.sel[f.clave].clear()); estado.desde = estado.hasta = null; estado.q = ''; $('#q').value = '';
        actualizar(); $('#conteo').focus();
      }
      if (e.target.closest('#mas')) {
        e.preventDefault(); const desde = estado.pagina * POR_PAGINA; estado.pagina++;
        pintarResultados(desde); aURL();
      }
    });
    $('#orden').addEventListener('change', e => { estado.orden = e.target.value; actualizar(); });

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
        const f = $$('button:not([disabled]), input:not([disabled]), summary, [href], select', panel).filter(x => x.offsetParent !== null);
        if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
        else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
      }
    });
    matchMedia('(max-width: 899px)').addEventListener('change', () => { if (!esMovil() && panel.classList.contains('abierto')) cerrarPanel(); });
    if (params.get('panel') === 'abierto' && esMovil()) setTimeout(abrirPanel, 0);
    if (location.hash === '#q') setTimeout(() => $('#q').focus(), 0);

    const pag = estado.pagina; pintarFacetas(); estado.pagina = pag; pintarResultados(); aURL();
  }

  // =====================================================================
  // CAJA
  // =====================================================================
  function caja() {
    const n = parseInt(params.get('n')) || 2;
    const c = cajaPorNum.get(n) || D.cajas[1];
    comunes('cajas', [
      'Probar: <a href="caja.html?n=2">Caja 2</a> · <a href="caja.html?n=43">Caja 43 (casi todo sin título)</a> · <a href="caja.html?n=57">Caja 57 (restringidos)</a> · <a href="caja.html?n=17">Caja 17 (obra en 4 cajas)</a> · <a href="caja.html?n=6">Caja 6 (año sin confirmar)</a>.',
      '«La misma obra sigue en otras cajas» se deduce por nombre parecido, como en el wireframe (D4 abierta).',
    ]);
    document.title = `Caja ${c.n}: ${c.nombre} · Las cajas de Aída`;
    const regs = D.registros.filter(r => r.k === c.n);
    const tipos = {};
    regs.forEach(r => r.tp.forEach(i => tipos[i] = (tipos[i] || 0) + 1));
    const base = norm(c.nombre).replace(/[^a-z ]/g, '').split(' ').filter(w => w.length > 3).slice(0, 2).join(' ');
    const hermanas = D.cajas.filter(o => o.n !== c.n && base && norm(o.nombre).replace(/[^a-z ]/g, '').includes(base));
    const ant = D.cajas[D.cajas.indexOf(c) - 1], sig = D.cajas[D.cajas.indexOf(c) + 1];
    $('#contenido').innerHTML = `<div class="contenedor">
      ${migas([['Inicio', 'inicio.html'], ['Las 70 cajas', 'cajas.html'], [`Caja ${c.n}`]])}
      <div class="cabeza-pagina">
        <p class="kicker">Caja ${c.n} de ${D.cajas.length}</p>
        <h1>${esc(c.nombre)}</h1>
        <ul class="datos-caja">
          <li><b>${regs.length}</b> documentos</li><li><b>${c.digit}</b> con documento digital</li>
          ${c.restr ? `<li><b>${c.restr}</b> de consulta a pedido</li>` : ''}
          <li>${c.desde ? `<b>${c.desde}${c.hasta !== c.desde ? '–' + c.hasta : ''}</b>` : 'Sin años cargados'}</li>
          <li><b>${regs.filter(r => !r.t).length}</b> sin título</li>
        </ul>
        ${c.resumen ? `<div class="texto-plegable plegado" id="resumen-caja"><div class="cuerpo" id="cuerpo-resumen"><p>${esc(c.resumen)}</p></div>
          <button type="button" class="boton boton--fantasma" aria-expanded="false" aria-controls="cuerpo-resumen" id="leer-mas">Leer más</button></div>` : ''}
        <form class="buscador" action="explorar.html" role="search" aria-label="Buscar en esta caja" style="margin-top:24px">
          <input type="hidden" name="caja" value="${c.n}">
          <div class="buscador__campo">${ic('buscar')}<label class="solo-lector" for="q-caja">Buscar en la caja ${c.n}</label>
          <input type="search" id="q-caja" name="q" placeholder="Buscar en esta caja…"></div><button class="boton boton--primario">Buscar</button>
        </form>
        <p style="margin-top:12px"><a href="explorar.html?caja=${c.n}">Ver esta caja en el buscador, con todos los filtros</a></p>
      </div>
      ${hermanas.length ? `<section class="seccion" aria-labelledby="h-hermanas" style="padding-top:16px"><div class="seccion-cabeza"><h2 id="h-hermanas">La misma obra sigue en otras cajas</h2></div>
        <ul class="accesos">${hermanas.map(o => `<li><a class="acceso" href="caja.html?n=${o.n}">${ic('caja')}Caja ${o.n}: ${esc(o.nombre)} <span class="n">${o.regs}</span></a></li>`).join('')}</ul></section>` : ''}
      <section class="seccion" aria-labelledby="h-docs">
        <div class="seccion-cabeza"><h2 id="h-docs">Documentos de la caja</h2><span class="meta">En el orden en que están en la caja</span></div>
        <ul class="accesos" aria-label="Filtrar esta caja por tipo" style="margin-bottom:16px">${Object.entries(tipos).sort((a, b) => b[1] - a[1]).slice(0, 6).map(([i, k]) =>
          `<li><a class="acceso" href="explorar.html?caja=${c.n}&tipo=${vocab.get('tipo:' + i).slug}">${esc(nombreTipo(+i))} <span class="n">${k}</span></a></li>`).join('')}</ul>
        <ol class="lista-registros" aria-label="Documentos de la caja ${c.n}">${regs.map(r => tarjeta(r)).join('')}</ol>
      </section>
      <nav class="vecinos" aria-label="Otras cajas">
        ${ant ? `<a href="caja.html?n=${ant.n}">${ic('flechaIzq')}<span><small>Caja anterior</small>Caja ${ant.n}: ${esc(ant.nombre)}</span></a>` : ''}
        ${sig ? `<a class="sig" href="caja.html?n=${sig.n}"><span><small>Caja siguiente</small>Caja ${sig.n}: ${esc(sig.nombre)}</span>${ic('flechaDer')}</a>` : ''}
      </nav></div>`;
    const lm = $('#leer-mas');
    if (lm) lm.addEventListener('click', () => {
      const ab = lm.getAttribute('aria-expanded') !== 'true';
      lm.setAttribute('aria-expanded', ab); lm.textContent = ab ? 'Leer menos' : 'Leer más';
      $('#resumen-caja').classList.toggle('plegado', !ab);
    });
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
    comunes('ficha', [
      'Variantes: <a href="ficha.html?c=C14R3">digitalizado (PDF de 105 MB)</a> · <a href="ficha.html?c=C6R1">digitalizado, año «1972?»</a> · <a href="ficha.html?c=C2R11">a pedido, sin título</a> · <a href="ficha.html?c=C14R12">sin título, solo ficha</a> · <a href="ficha.html?c=C2R7">sin título, digitalizado</a>.',
      'Visor: la hoja es un dibujo de la maqueta (no se suben PDF al repo). En producción el marco <code>.visor__marco</code> carga la versión web liviana del PDF; ver componentes.md.',
      'Solo C14R3, C2R11, C14R12 y C2R7 tienen ficha completa en el extracto; el resto muestra los campos del listado (autor, descripción corta).',
    ]);
    const t = titulo(r);
    document.title = `${t.texto} (${r.c}) · Las cajas de Aída`;
    const enCaja = D.registros.filter(x => x.k === r.k);
    const i = enCaja.indexOf(r), ant = enCaja[i - 1], sig = enCaja[i + 1];
    const archivos = (f.archivos || '').split(';').filter(Boolean).map(a => { const [n, b] = a.split('|'); return { n, mb: Math.round(b / 1e6) }; });
    const paginas = f.paginas || null;
    const volver = sessionStorage.getItem('aida-volver');
    const anioTxt = f.anio_texto ?? r.at;
    const ai = anioInfo({ a: f.anio || r.a, at: anioTxt });
    const fila = (dt, dd) => dd ? `<div><dt>${dt}</dt><dd>${dd}</dd></div>` : '';
    const vlinks = (ids, fac) => ids.map(id => vocab.get(fac + ':' + id)).filter(Boolean).map(v => `<a href="explorar.html?${fac}=${v.slug}">${esc(v.nombre)}</a>`).join(', ');
    let doc;
    if (r.r) {
      doc = `<h2 id="h-doc">Documento</h2>
        <div class="vista vista--pedido" aria-hidden="true">${ic('candado')}</div>
        <div class="aviso aviso--pedido" role="note">${ic('candado')}<p><b>Consulta a pedido</b>La ficha es pública, pero el documento se consulta pidiéndolo al archivo. ${r.d ? 'Está digitalizado.' : 'Todavía no está digitalizado.'}</p></div>
        <div class="acciones"><a class="boton boton--primario" href="consulta.html?c=${r.c}" id="pedir">${ic('sobre')}Pedir consulta de este documento</a></div>
        <p class="meta" style="margin:0">Te respondemos por correo. No hace falta crear una cuenta.</p>`;
    } else if (r.d) {
      doc = `<h2 id="h-doc">Documento</h2>
        <div class="vista vista--hoja" aria-hidden="true"></div>
        <div class="acciones"><a class="boton boton--primario" href="#visor" id="abrir-visor" aria-controls="visor" aria-expanded="false">${ic('ver')}Ver documento${paginas ? ` <span class="peso">(${paginas} págs.)</span>` : ''}</a>
        ${archivos.length ? archivos.map(a => `<a class="boton boton--secundario" href="#" data-descarga="${esc(a.n)}">${ic('descargar')}Descargar PDF <span class="peso">(${num(a.mb)} MB)</span></a>`).join('')
          : `<a class="boton boton--secundario" href="#" data-descarga="${r.c}.pdf">${ic('descargar')}Descargar PDF</a>`}</div>`;
    } else {
      doc = `<h2 id="h-doc">Documento</h2>
        <div class="vista vista--vacia" aria-hidden="true">${ic('ficha')}</div>
        <div class="aviso aviso--ficha" role="note">${ic('ficha')}<p><b>Todavía no está digitalizado</b>El archivo se digitaliza por etapas; por ahora podés ver la ficha del catálogo.</p></div>
        <div class="acciones"><a class="boton boton--secundario" href="consulta.html?c=${r.c}&motivo=original">${ic('sobre')}Consultar por este documento</a></div>`;
    }
    const citaAnio = ai.sin ? 's. f.' : ai.dudoso ? `[${ai.texto}?]` : ai.texto;
    $('#contenido').innerHTML = `<div class="contenedor">
      ${migas([['Inicio', 'inicio.html'], [`Caja ${c.n}: ${esc(c.nombre)}`, `caja.html?n=${c.n}`], [r.c]])}
      ${volver ? `<a class="volver" href="${esc(volver)}">${ic('flechaIzq')}Volver a los resultados</a>` : ''}
      <div class="cabeza-pagina">
        <p class="kicker">Caja ${c.n} · Registro ${r.nr} · Código <span class="codigo">${r.c}</span></p>
        <h1>${tituloHTML(r)}</h1>
        ${t.sin ? '<p class="meta">Este documento no tiene título en el catálogo; se lo nombra por su tipo.</p>' : ''}
        <div class="etqs">${etiquetas(r, 5)}${ai.dudoso ? `<span class="etq etq--duda">${ic('duda')}Año sin confirmar</span>` : ''}</div>
      </div>
      <div class="ficha">
        <aside class="ficha__documento documento" aria-labelledby="h-doc">${doc}</aside>
        <div>
          <h2>Ficha del catálogo</h2>
          <dl class="catalogo">
            ${fila('Título', r.t ? esc(f.titulo || r.t) : '<span class="sin-titulo">Sin título</span>')}
            ${fila('Tipo de documento', vlinks(r.tp, 'tipo'))}
            ${fila('Autor/es', esc(f.autor || r.au))}
            ${fila('Fecha', esc(f.fecha_texto))}
            ${fila('Año', r.a ? `<a href="explorar.html?decada=${r.dec}">${r.a}</a>` : ai.sin ? '' : anioHTML({ a: null, at: anioTxt }) + (ai.dudoso ? `<br><span class="meta">En el catálogo: «${esc(ai.original)}»</span>` : ''))}
            ${fila('Lugar', esc(f.lugar))}
            ${fila('Páginas', esc(f.paginas))}
            ${fila('Descriptores', vlinks(r.ds, 'descriptor'))}
            ${fila('ISBN', esc(f.isbn))}
            ${fila('Caja', `<a href="caja.html?n=${c.n}">Caja ${c.n}: ${esc(c.nombre)}</a>`)}
            ${fila('Acceso', r.r ? 'Consulta a pedido' : 'Libre')}
          </dl>
          <h2 style="margin-top:40px">Descripción</h2>
          <p>${esc(f.resumen || r.x || 'Sin descripción.')}</p>
          ${f.obs_editorial ? `<h3>Observaciones</h3><p>${esc(f.obs_editorial)}</p>` : ''}
          <h2 id="citar" style="margin-top:40px">Cómo citar</h2>
          <p class="cita" id="cita">Bortnik, Aída. ${esc(t.sin ? '[' + t.texto + ']' : t.texto)}. ${esc(citaAnio)}. Archivo «Las cajas de Aída», caja ${c.n}, ${r.c}. https://lascajasdeaida.com.ar/registro/${r.c}</p>
          <div class="acciones"><button class="boton boton--secundario" type="button" id="copiar-cita">${ic('copiar')}Copiar cita</button><button class="boton boton--secundario" type="button" id="compartir">${ic('enlace')}Copiar enlace</button></div>
          <p role="status" class="meta" id="copiado"></p>
        </div>
      </div>
      <section id="visor" class="visor" hidden aria-label="Visor del documento ${r.c}">
        <div class="visor__barra" role="toolbar" aria-label="Controles del visor">
          <div class="grupo">
            <button class="visor__boton" type="button" data-pag="-1" aria-label="Página anterior" disabled>${ic('izq')}</button>
            <label class="visor__pagina"><span>Página</span> <input type="number" id="pag-actual" value="1" min="1" ${paginas ? `max="${paginas}"` : ''}></label>${paginas ? `<span class="visor__pagina">de ${paginas}</span>` : ''}
            <button class="visor__boton" type="button" data-pag="1" aria-label="Página siguiente">${ic('der')}</button>
          </div>
          <div class="grupo">
            <button class="visor__boton" type="button" data-zoom="-1" aria-label="Alejar">${ic('menos')}</button>
            <button class="visor__boton" type="button" data-zoom="1" aria-label="Acercar">${ic('mas')}</button>
            <button class="visor__boton" type="button" data-zoom="0" aria-label="Ajustar al ancho">${ic('ancho')}</button>
            <button class="visor__boton" type="button" id="pantalla" aria-label="Pantalla completa">${ic('pantalla')}</button>
          </div>
          <span class="separador"></span>
          <div class="grupo">
            <a class="visor__boton" href="#" data-descarga="${r.c}.pdf" aria-label="Descargar PDF${archivos[0] ? ` (${num(archivos[0].mb)} MB)` : ''}">${ic('descargar')}</a>
            <button class="visor__boton visor__boton--texto" type="button" id="cerrar-visor">${ic('cerrar')}Cerrar</button>
          </div>
        </div>
        <div class="visor__zona" tabindex="0" aria-label="Página del documento; con las flechas se cambia de página">
          <div class="visor__hoja" id="hoja" role="img" aria-label="Página 1 del documento ${r.c}">
            <div class="pliego"></div>
            <svg class="anotacion" viewBox="0 0 120 60" aria-hidden="true"><path d="M4 40c14-22 26-24 30-10s14 10 24-6 22-14 28 2 14 8 30-8" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><path d="M10 52h70" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            <span class="folio" id="folio">1</span>
          </div>
        </div>
        <p class="visor__pie"><kbd>←</kbd> <kbd>→</kbd> página · <kbd>+</kbd> <kbd>−</kbd> zoom · <kbd>Esc</kbd> cerrar</p>
      </section>
      <nav class="vecinos" aria-label="Documentos vecinos en la caja">
        ${ant ? `<a href="ficha.html?c=${ant.c}">${ic('flechaIzq')}<span><small>Anterior · ${ant.c}</small>${esc(titulo(ant).texto.slice(0, 60))}</span></a>` : ''}
        ${sig ? `<a class="sig" href="ficha.html?c=${sig.c}"><span><small>Siguiente · ${sig.c}</small>${esc(titulo(sig).texto.slice(0, 60))}</span>${ic('flechaDer')}</a>` : ''}
      </nav></div>`;
    evento('ver_registro', { codigo: r.c, caja: r.k, acceso: r.r ? 'a_pedido' : 'libre', digitalizado: r.d ? 'si' : 'no', con_titulo: t.sin ? 'no' : 'si' });

    const av = $('#abrir-visor'), visor = $('#visor'), hoja = $('#hoja'), pa = $('#pag-actual');
    let zoom = 640;
    const irA = p => {
      const maxp = paginas || 999; p = Math.max(1, Math.min(maxp, p)); pa.value = p;
      $('#folio').textContent = p; hoja.setAttribute('aria-label', `Página ${p} del documento ${r.c}`);
      $('[data-pag="-1"]').disabled = p <= 1; $('[data-pag="1"]').disabled = p >= maxp;
      history.replaceState(null, '', location.search + '#p=' + p);
    };
    const zoomA = z => { zoom = z; hoja.style.width = z ? `min(${z}px, ${z > 640 ? 'none' : '100%'})` : '100%'; hoja.style.maxWidth = 'none'; };
    function abrirVisor() { visor.hidden = false; av?.setAttribute('aria-expanded', 'true'); pa.focus(); evento('ver_documento', { codigo: r.c, caja: r.k }); }
    if (av) {
      av.addEventListener('click', e => { e.preventDefault(); abrirVisor(); visor.scrollIntoView({ block: 'start' }); });
      $('#cerrar-visor').addEventListener('click', () => { if (document.fullscreenElement) document.exitFullscreen(); visor.hidden = true; av.setAttribute('aria-expanded', 'false'); av.focus(); });
      visor.addEventListener('click', e => {
        const b = e.target.closest('[data-pag]'); if (b) irA(+pa.value + +b.dataset.pag);
        const z = e.target.closest('[data-zoom]'); if (z) zoomA(+z.dataset.zoom === 0 ? 0 : Math.max(320, Math.min(1400, (zoom || 640) + 160 * +z.dataset.zoom)));
      });
      pa.addEventListener('change', () => irA(+pa.value));
      $('#pantalla').addEventListener('click', () => visor.requestFullscreen?.());
      visor.addEventListener('keydown', e => {
        if (e.target === pa && /^[0-9]$/.test(e.key)) return;
        if (e.key === 'Escape') $('#cerrar-visor').click();
        if (e.key === 'ArrowRight' && e.target !== pa) irA(+pa.value + 1);
        if (e.key === 'ArrowLeft' && e.target !== pa) irA(+pa.value - 1);
        if (e.key === '+') zoomA((zoom || 640) + 160);
        if (e.key === '-') zoomA(Math.max(320, (zoom || 640) - 160));
      });
      const m = location.hash.match(/^#p=(\d+)/);
      if (m || params.get('visor') === 'abierto') { abrirVisor(); irA(m ? +m[1] : 1); }
    }
    $$('[data-descarga]').forEach(b => b.addEventListener('click', e => { e.preventDefault(); evento('file_download', { file_name: b.dataset.descarga, file_extension: 'pdf', codigo: r.c, caja: r.k }); }));
    $('#copiar-cita').addEventListener('click', () => { navigator.clipboard?.writeText($('#cita').textContent); $('#copiado').textContent = 'Cita copiada.'; });
    $('#compartir').addEventListener('click', () => { navigator.clipboard?.writeText('https://lascajasdeaida.com.ar/registro/' + r.c); $('#copiado').textContent = 'Enlace copiado.'; });
  }

  // =====================================================================
  // CONSULTA A PEDIDO
  // =====================================================================
  function consulta() {
    const cod = (params.get('c') || 'C2R11').toUpperCase();
    const r = registroPorCodigo.get(cod) || registroPorCodigo.get('C2R11');
    const c = cajaPorNum.get(r.k);
    comunes('consulta', [
      'Probá enviar el formulario vacío para ver el resumen de errores y los errores por campo. Con todo completo se ve la confirmación.',
      'Plazo de respuesta y modalidad de consulta: a definir por el cliente (D3).',
    ]);
    $('#migas').innerHTML = migas([['Inicio', 'inicio.html'], [`Caja ${c.n}`, `caja.html?n=${c.n}`], [r.c, `ficha.html?c=${r.c}`], ['Consulta a pedido']]);
    $('#doc-pedido').innerHTML = `<p class="kicker">Documento pedido</p>${tarjeta(r, 2, 'div')}`;
    if (params.get('motivo') === 'original') $('#h-consulta').textContent = 'Consultar por un documento';
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
        err.innerHTML = bien ? '' : ic('alerta') + esc(msj); err.hidden = bien;
        if (!bien) errores.push([id, msj]);
      });
      const res = $('#resumen-errores');
      if (errores.length) {
        res.hidden = false;
        res.innerHTML = `<h2 tabindex="-1">Revisá ${errores.length === 1 ? 'un dato' : errores.length + ' datos'} antes de enviar</h2><ul>${errores.map(([id, m]) => `<li><a href="#${id}">${m}</a></li>`).join('')}</ul>`;
        $('h2', res).focus(); return;
      }
      $('#zona-form').innerHTML = `<div class="confirmacion" role="status"><h2 tabindex="-1" id="ok">${ic('check')}Recibimos tu pedido</h2>
        <p>Número de consulta: <span class="numero-consulta">AB-2026-0042</span>. Te mandamos una copia a tu correo.</p>
        <p>El equipo del archivo te va a responder en un plazo de <b>[a definir]</b> días hábiles.</p>
        <div class="acciones"><a class="boton boton--secundario" href="ficha.html?c=${r.c}">${ic('flechaIzq')}Volver al documento</a><a class="boton boton--fantasma" href="explorar.html">Seguir explorando</a></div></div>`;
      $('#ok').focus();
      evento('consulta_enviada', { codigo: r.c, caja: r.k, motivo: $('#motivo')?.value });
    });
  }

  function sobre() { comunes('sobre', ['Los textos institucionales los provee el cliente: los recuadros punteados marcan dónde van. Lo demás sale del sitio actual, de los wireframes de UX y de los datos (cifras).']);
    const T = D.totales;
    $$('[data-cifra]').forEach(el => { el.textContent = num(T[el.dataset.cifra]); });
  }

  /* Catálogo de componentes: pinta ejemplos con datos reales */
  function componentes() {
    comunes('', ['Cada ejemplo usa el mismo marcado que las pantallas. Los estados hover y activo se ven pasando el mouse o con <code>:hover</code> forzado en las herramientas del navegador; el foco, con Tab.']);
    const ej = c => registroPorCodigo.get(c);
    $('#ej-registros').innerHTML = ['C14R3', 'C2R11', 'C14R12', 'C6R1'].map(c => tarjeta(ej(c))).join('');
    $('#ej-cajas').innerHTML = [1, 43, 57].map(n => tarjetaCaja(cajaPorNum.get(n))).join('');
    $$('[data-icono]').forEach(el => { el.innerHTML = ic(el.dataset.icono) + `<code>${el.dataset.icono}</code>`; });
    $$('[data-ic]').forEach(el => el.insertAdjacentHTML('afterbegin', ic(el.dataset.ic)));
  }

  window.MAQ = { inicio, cajas, explorar, caja, ficha, consulta, sobre, componentes, ic, ICONOS };
})();
