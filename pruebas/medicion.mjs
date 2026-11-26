// Prueba de la medición (docs/medicion.md): captura cada push al dataLayer sin cargar GTM real.
//
//   Archivo:        php -S 127.0.0.1:8106 -t public bin/router.php   (config con 'analitica' => ['gtm' => 'GTM-M83WNDH5'],
//                   cajas 1, 2 y 3 publicadas, ids de Drive cargados, usuario del backoffice en AIDA_USUARIO / AIDA_CLAVE)
//   Institucional:  python3 -m http.server 8766   (en la carpeta sitio/ del institucional)
//   node pruebas/medicion.mjs [archivo|institucional]
//
// Los hosts de prueba y de producción se simulan con --host-resolver-rules: todos apuntan a 127.0.0.1.
import { createRequire } from 'module';
const { chromium } = createRequire(import.meta.url)('playwright'); // global: NODE_PATH=$(npm root -g)

const PRUEBA = 'lascajasdeaida.com.ar.vcct10010.avnam.net';
const PROD = 'lascajasdeaida.com.ar';
const ARCHIVO = Number(process.env.AIDA_PUERTO || 8106), INST = Number(process.env.INST_PUERTO || 8766);
const USUARIO = process.env.AIDA_USUARIO || 'qa-medicion@example.com', CLAVE = process.env.AIDA_CLAVE || 'clave-prueba-123';
const PII = ['Persona De Prueba', 'persona.prueba@example.com', 'Rosario', 'Centro Cultural Prueba', '1155551234', 'Texto privado'];

let fallas = 0, ok = 0;
const check = (cond, msg, extra) => { if (cond) { ok++; console.log('  ok   ' + msg); } else { fallas++; console.log('  FALLA ' + msg + (extra !== undefined ? ' → ' + JSON.stringify(extra) : '')); } };

async function nuevo(browser, { permisos = [] } = {}) {
  const ctx = await browser.newContext({ permissions: permisos });
  const pushes = [], gtm = [];
  await ctx.addInitScript(() => {
    // Stub: el dataLayer registra cada push en la consola (sobrevive a la navegación)
    const dl = []; const push = dl.push.bind(dl);
    dl.push = (...xs) => { xs.forEach(x => { try { console.log('DL ' + JSON.stringify(x)); } catch (e) {} }); return push(...xs); };
    Object.defineProperty(window, 'dataLayer', { configurable: true, get: () => dl, set: () => {} });
    // Enlaces que no son páginas del sitio (correo, redes, archivo): se miden pero no se navega
    window.addEventListener('click', e => {
      const a = e.target.closest && e.target.closest('a[href]');
      if (a && a.dataset.navegar === undefined && /^(mailto:|tel:|https?:\/\/(?!127\.)|\/archivo\/)/i.test(a.getAttribute('href'))) e.preventDefault();
    }, true);
  });
  await ctx.route(/googletagmanager\.com/, r => { gtm.push(r.request().url()); r.fulfill({ status: 200, contentType: 'text/javascript', body: '/* GTM simulado */' }); });
  await ctx.route(/fonts\.(googleapis|gstatic)\.com|drive\.google\.com|instagram\.com|facebook\.com/, r => r.abort());
  const page = await ctx.newPage();
  page.on('console', m => { const t = m.text(); if (t.startsWith('DL ')) pushes.push(JSON.parse(t.slice(3))); });
  page.on('pageerror', e => { fallas++; console.log('  FALLA error de JS: ' + e.message); });
  return { ctx, page, pushes, gtm };
}
const eventos = (p, nombre) => p.filter(x => x.event === nombre);
const contexto = p => p.filter(x => x.seccion !== undefined && x.event === undefined);
const sinPII = (p, donde) => { const t = JSON.stringify(p); const hay = PII.filter(x => t.includes(x)); check(!hay.length && !/@/.test(t), 'sin datos personales en ' + donde, hay.length ? hay : p.filter(x => /@/.test(JSON.stringify(x)))); };
const espera = ms => new Promise(r => setTimeout(r, ms));

async function archivo(browser) {
  const base = `http://${PRUEBA}:${ARCHIVO}`;
  console.log('\nArchivo, host de prueba (' + base + ')');
  const { ctx, page, pushes, gtm } = await nuevo(browser, { permisos: ['clipboard-read', 'clipboard-write'] });
  await ctx.route(/archivo\.php\?id=\d+&descargar=1/, r => r.fulfill({ status: 204, body: '' }));

  // Inicio + búsqueda desde el inicio
  await page.goto(base + '/');
  let c = contexto(pushes).at(-1);
  check(c && c.seccion === 'inicio' && c.entorno === 'prueba' && c.traffic_type === 'internal' && c.debug_mode === true
    && c.tipo_contenido === 'archivo' && c.content_group === 'Archivo' && c.rama === 'Archivo', 'inicio: contexto de prueba antes de GTM', c);
  check(pushes.findIndex(x => x.seccion) < pushes.findIndex(x => x.event === 'gtm.js'), 'el contexto va antes del evento gtm.js');
  check(gtm.length >= 1 && gtm[0].includes('GTM-M83WNDH5'), 'pide el contenedor GTM-M83WNDH5');
  await page.fill('#q-inicio', 'carta');
  await Promise.all([page.waitForURL(/explorar/), page.press('#q-inicio', 'Enter')]);
  await espera(300);
  let b = eventos(pushes, 'buscar_archivo');
  check(b.length === 1 && b[0].search_term === 'carta' && b[0].origen_busqueda === 'inicio' && b[0].resultados > 0, 'buscar_archivo desde el inicio, con resultados', b);
  check(contexto(pushes).at(-1).seccion === 'explorar', 'explorar: sección explorar');

  // Búsqueda mientras se escribe: un evento por término, no por tecla
  await page.fill('#q', '');
  await page.type('#q', 'guion', { delay: 120 });
  await espera(2300);
  b = eventos(pushes, 'buscar_archivo').filter(x => x.origen_busqueda === 'explorar_escritura');
  check(b.length === 1 && b[0].search_term === 'guion', 'buscar_archivo una sola vez al escribir “guion”', b);
  // Tipeo con pausas cortas (< 1,5 s): sigue siendo uno
  await page.fill('#q', '');
  await page.type('#q', 'zzqx', { delay: 400 });
  await espera(2300);
  b = eventos(pushes, 'buscar_archivo').filter(x => x.search_term && x.search_term.startsWith('zz'));
  const s = eventos(pushes, 'busqueda_sin_resultados');
  check(b.length === 1 && b[0].search_term === 'zzqx' && b[0].resultados === 0, 'sin resultados: un buscar_archivo con 0', b);
  check(s.length === 1 && s[0].search_term === 'zzqx', 'busqueda_sin_resultados una vez', s);
  // Confirmar con Enter el mismo término no lo duplica
  await page.press('#q', 'Enter'); await espera(800);
  check(eventos(pushes, 'buscar_archivo').filter(x => x.search_term === 'zzqx').length === 1, 'Enter sobre el mismo término no duplica');
  // Un correo o un teléfono escritos en el buscador no llegan al dataLayer
  await page.fill('#q', ''); await page.type('#q', 'persona.prueba@example.com 1155551234', { delay: 10 }); await espera(2300);
  b = eventos(pushes, 'buscar_archivo').at(-1);
  check(b && b.search_term === '[correo] [numero]', 'búsqueda con correo y teléfono: se limpian', b);

  // Filtros, orden, paginación, chips
  await page.fill('#q', ''); await page.press('#q', 'Enter'); await espera(800);
  const casilla = page.locator('#facetas .opcion input[type=checkbox]').first();
  const nombre = await casilla.getAttribute('name'), valor = await casilla.getAttribute('value');
  await casilla.check(); await espera(800);
  let f = eventos(pushes, 'aplicar_filtro').at(-1);
  check(f && f.filtro === nombre.replace('[]', '') && f.filtro_valor === valor, 'aplicar_filtro con faceta y valor', f);
  const chip = page.locator('#chips a[data-quitar]').first();
  if (await chip.count()) {
    await chip.click(); await espera(800);
    f = eventos(pushes, 'quitar_filtro').at(-1);
    check(f && f.filtro === nombre.replace('[]', '') && f.ubicacion_enlace === 'chip', 'quitar_filtro desde el chip', f);
  }
  await page.selectOption('#orden', 'anio-asc'); await espera(800);
  check(eventos(pushes, 'ordenar_resultados').at(-1)?.orden_resultados === 'anio-asc', 'ordenar_resultados');
  await page.click('#mas'); await espera(800);
  const pg = eventos(pushes, 'paginar_resultados').at(-1);
  check(pg && pg.pagina === 2 && pg.resultados > 20, 'paginar_resultados (Mostrar más)', pg);
  await page.locator('.registro h3 a, .registro h2 a').first().click();
  await page.waitForURL(/registro/);
  check(eventos(pushes, 'select_content').length === 1, 'select_content al elegir un resultado');
  // Volver a la misma búsqueda (atrás) no cuenta otra búsqueda
  const antes = eventos(pushes, 'buscar_archivo').length;
  await page.goBack(); await espera(500);
  check(eventos(pushes, 'buscar_archivo').length === antes, 'volver atrás no repite buscar_archivo');

  // Las cajas, una caja
  await page.goto(base + '/cajas');
  check(contexto(pushes).at(-1).seccion === 'cajas', 'cajas: sección cajas');
  await page.goto(base + '/caja/1');
  c = contexto(pushes).at(-1);
  const vc = eventos(pushes, 'ver_caja').at(-1);
  check(c.seccion === 'caja' && c.caja_numero === 1, 'caja: contexto con caja_numero', c);
  check(vc && vc.caja_numero === 1 && vc.documentos > 0, 'ver_caja', vc);
  // Buscar dentro de la caja
  await page.fill('#q-caja', 'carta');
  await Promise.all([page.waitForURL(/explorar/), page.press('#q-caja', 'Enter')]); await espera(300);
  b = eventos(pushes, 'buscar_archivo').at(-1);
  check(b.origen_busqueda === 'caja' && /caja:1/.test(b.filtros_activos), 'buscar_archivo desde la caja (con el filtro de la caja)', b);

  // Ficha con visor (Drive, 0,4 MB)
  await page.goto(base + '/registro/C1R33');
  c = contexto(pushes).at(-1);
  const vr = eventos(pushes, 'ver_registro').at(-1);
  check(c.seccion === 'registro' && c.registro_codigo === 'C1R33' && c.caja_numero === 1 && c.acceso_documento === 'libre' && c.digitalizado === 'si',
    'ficha: contexto con registro_codigo, caja, acceso', c);
  check(vr && vr.registro_codigo === 'C1R33' && !('titulo' in vr), 'ver_registro con el código, sin título', vr);
  await page.click('[data-visor]'); await espera(300);
  const va = eventos(pushes, 'ver_archivo').at(-1);
  check(va && va.registro_codigo === 'C1R33' && va.origen_archivo === 'drive' && va.peso_mb === 0.4 && va.archivo_parte === 1 && va.ubicacion_enlace === 'ficha', 'ver_archivo al abrir el visor', va);
  await page.click('[data-visor-descargar]'); await espera(300);
  let da = eventos(pushes, 'descargar_archivo').at(-1);
  check(da && da.ubicacion_enlace === 'visor' && da.registro_codigo === 'C1R33', 'descargar_archivo desde el visor', da);
  await page.click('.acciones-registro__lista a[href*="descargar=1"]'); await espera(300);
  da = eventos(pushes, 'descargar_archivo').at(-1);
  check(da && da.ubicacion_enlace === 'ficha', 'descargar_archivo desde la ficha', da);
  // Copiar necesita contexto seguro (https o localhost): en el host provisorio por http el portapapeles no existe
  await page.goto(`http://127.0.0.1:${ARCHIVO}/registro/C1R33`);
  check(contexto(pushes).at(-1).entorno === 'prueba', '127.0.0.1: entorno prueba');
  await page.click('[data-copiar="#cita"]'); await espera(300);
  await page.click('[data-copiar-texto]'); await espera(300);
  const cp = eventos(pushes, 'compartir_registro').map(x => x.metodo_compartir + ':' + x.registro_codigo);
  check(cp.join() === 'copiar_cita:C1R33,copiar_enlace:C1R33', 'compartir_registro: copiar cita y copiar enlace', cp);

  // Ficha solo descarga (más de 100 MB en Drive)
  await page.goto(base + '/registro/C1R15');
  await page.click('.acciones-registro__lista a[href*="descargar=1"]'); await espera(300);
  da = eventos(pushes, 'descargar_archivo').at(-1);
  check(da && da.registro_codigo === 'C1R15' && da.peso_mb > 100, 'descargar_archivo de un PDF de más de 100 MB', da);

  // Restringido → consulta a pedido
  await page.goto(base + '/registro/C2R11');
  check(contexto(pushes).at(-1).acceso_documento === 'a_pedido', 'restringido: acceso_documento a_pedido');
  await Promise.all([page.waitForURL(/consulta/), page.click('a[href*="consulta/C2R11"]')]);
  c = contexto(pushes).at(-1);
  check(c.seccion === 'consulta' && c.registro_codigo === 'C2R11' && c.tipo_consulta === 'a_pedido', 'consulta: contexto', c);
  // Envío con errores: solo los nombres de los campos
  await page.fill('#nombre', 'Persona De Prueba');
  check(eventos(pushes, 'iniciar_consulta').length === 1, 'iniciar_consulta al empezar el formulario');
  await espera(3200);
  await Promise.all([page.waitForLoadState('load'), page.click('#form-consulta button[type=submit], #form-consulta button:not([type])')]);
  await espera(300);
  const ec = eventos(pushes, 'error_consulta').at(-1);
  check(ec && /correo/.test(ec.campos_error), 'error_consulta con los campos (sin valores)', ec);
  await page.fill('#nombre', 'Persona De Prueba');
  await page.fill('#correo', 'persona.prueba@example.com');
  for (const sel of ['#uso', '#motivo']) if (await page.locator(sel).count()) {
    const tag = await page.locator(sel).evaluate(e => e.tagName);
    if (tag === 'SELECT') await page.selectOption(sel, { index: 1 }); else await page.fill(sel, 'Texto privado de la consulta para investigación');
  }
  for (const r of await page.locator('#form-consulta input[type=radio]').all()) { await r.check(); break; }
  if (await page.locator('#condiciones').count()) await page.check('#condiciones');
  await espera(3200);
  await Promise.all([page.waitForURL(/enviada/), page.click('#form-consulta button[type=submit], #form-consulta button:not([type])')]);
  await espera(300);
  const env = eventos(pushes, 'enviar_consulta');
  check(env.length === 1 && env[0].registro_codigo === 'C2R11' && env[0].tipo_consulta === 'a_pedido', 'enviar_consulta en la confirmación', env);
  await page.reload(); await espera(300);
  check(eventos(pushes, 'enviar_consulta').length === 1, 'recargar la confirmación no cuenta otra consulta');

  await page.goto(base + '/sobre');
  check(contexto(pushes).at(-1).seccion === 'sobre', 'sobre: sección sobre');
  await page.goto(base + '/no-existe');
  c = contexto(pushes).at(-1);
  check(c.seccion === 'error' && c.tipo_error === '404', '404: sección error', c);
  sinPII(pushes, 'el archivo (host de prueba)');
  check(!pushes.some(x => x.event === 'search' || x.event === 'filtro_aplicado' || x.event === 'cargar_mas'), 'ya no salen los nombres viejos (search, filtro_aplicado, cargar_mas)');
  const nombres = [...new Set(pushes.map(x => x.event).filter(Boolean))].sort();
  console.log('  eventos vistos: ' + nombres.join(', '));
  await ctx.close();

  // Producción simulada
  console.log('\nArchivo, host de producción simulado (http://' + PROD + ':' + ARCHIVO + ')');
  const pr = await nuevo(browser);
  await pr.page.goto(`http://${PROD}:${ARCHIVO}/registro/C1R33`);
  c = contexto(pr.pushes).at(-1);
  check(c.entorno === 'produccion' && !('traffic_type' in c) && !('debug_mode' in c), 'producción: entorno produccion, sin traffic_type ni debug_mode', c);
  await pr.page.goto(`http://www.${PROD}:${ARCHIVO}/`);
  check(contexto(pr.pushes).at(-1).entorno === 'produccion', 'producción: también con www');

  // Backoffice y vista previa: nada
  console.log('\nBackoffice y vista previa (producción simulada)');
  const bo0 = pr.pushes.length;
  await pr.page.goto(`http://${PROD}:${ARCHIVO}/admin/`);
  await pr.page.fill('input[name=email]', USUARIO); await pr.page.fill('input[name=clave]', CLAVE);
  await Promise.all([pr.page.waitForLoadState('load'), pr.page.press('input[name=clave]', 'Enter')]);
  for (const q of ['', '?r=cajas', '?r=vista_previa&caja=1']) await pr.page.goto(`http://${PROD}:${ARCHIVO}/admin/${q}`);
  // vista previa de un registro: se busca el id en la base por la ficha pública (enlace “Editar en el backoffice”)
  await pr.page.goto(`http://${PROD}:${ARCHIVO}/registro/C1R33`);
  const editar = await pr.page.locator('.barra-equipo a').getAttribute('href');
  c = contexto(pr.pushes).at(-1);
  check(c.entorno === 'produccion' && c.traffic_type === 'internal', 'sitio con sesión del backoffice: tráfico interno aun en producción', c);
  const idr = (editar.match(/id=(\d+)/) || [])[1];
  const n1 = pr.pushes.length, g1 = pr.gtm.length;
  await pr.page.goto(`http://${PROD}:${ARCHIVO}/admin/?r=vista_previa&registro=${idr}`);
  const vpVisor = pr.page.locator('[data-visor]').first();
  if (await vpVisor.count()) { await vpVisor.click(); await espera(300); }
  await pr.page.goto(`http://${PROD}:${ARCHIVO}/admin/?r=registro&id=${idr}`);
  const html = await pr.page.content();
  check(pr.pushes.length === n1 && pr.gtm.length === g1, 'backoffice y vista previa: ningún push ni pedido a GTM', pr.pushes.slice(n1));
  check(!/googletagmanager|data-medicion/.test(html), 'la ficha del backoffice no lleva GTM ni atributos de medición');
  const pushesBo = pr.pushes.slice(bo0).filter(x => !x.seccion && x.event !== 'gtm.js' && x.event !== 'ver_registro');
  check(pushesBo.length === 0, 'el backoffice no empuja eventos', pushesBo);
  await pr.ctx.close();
}

async function institucional(browser) {
  const paginas = ['index', 'catalogo', 'muestra', 'conservacion', 'digitalizacion', 'documental', 'contacto'];
  const base = `http://${PRUEBA}:${INST}`;
  console.log('\nSitio institucional, host de prueba (' + base + ')');
  const { ctx, page, pushes, gtm } = await nuevo(browser);
  for (const p of paginas) {
    await page.goto(`${base}/${p}.html`);
    const c = contexto(pushes).at(-1);
    check(c && c.seccion === (p === 'index' ? 'inicio' : p) && c.entorno === 'prueba' && c.traffic_type === 'internal' && c.debug_mode === true
      && c.tipo_contenido === 'sitio_institucional' && c.content_group === c.rama, `${p}: contexto de prueba (${c && c.rama})`, c);
  }
  check(gtm.length === paginas.length, 'GTM se pide en las 7 páginas');
  check(contexto(pushes).find(x => x.seccion === 'muestra').rama === 'Muestra', 'la rama de Muestra es “Muestra”');

  await page.goto(`${base}/index.html`);
  await page.locator('a[href="/archivo/"]').first().click().catch(() => {});
  await page.goto(`${base}/digitalizacion.html`);
  await page.locator('a[href="/archivo/"]').first().click();
  let e = eventos(pushes, 'clic_archivo').at(-1);
  check(e && e.destino_archivo === 'inicio' && e.ubicacion_enlace === 'cuerpo', 'clic_archivo al ir al archivo', e);
  await page.locator('.cab a[href*="instagram"]').first().click();
  e = eventos(pushes, 'clic_red_social').at(-1);
  check(e && e.red_social === 'Instagram' && e.ubicacion_enlace === 'cabecera', 'clic_red_social (Instagram, cabecera)', e);
  await page.click('.menu-boton');
  await Promise.all([page.waitForURL(/catalogo/), page.locator('#menu a[href="catalogo.html"]').click()]);
  e = eventos(pushes, 'clic_navegacion').at(-1);
  check(e && e.destino_seccion === 'catalogo' && e.ubicacion_enlace === 'cabecera', 'clic_navegacion desde el menú', e);
  const pdf = page.locator('a[href$="LAS_CAJAS_DE_AIDA.pdf"]').first();
  await pdf.evaluate(a => a.addEventListener('click', ev => ev.preventDefault()));
  await pdf.click();
  check(eventos(pushes, 'descarga_catalogo').length === 1, 'descarga_catalogo');
  await page.locator('a[href*="lanacion.com.ar"]').first().click();
  e = eventos(pushes, 'clic_externo').at(-1);
  check(e && e.dominio_destino === 'lanacion.com.ar', 'clic_externo (video de La Nación)', e);
  await page.locator('.pie a[href^="mailto:"]').first().click();
  check(eventos(pushes, 'clic_contacto').length >= 1, 'clic_contacto (correo del pie)');
  await page.goto(`${base}/index.html`);
  await page.locator('#donar a[href^="mailto:"]').first().click();
  check(eventos(pushes, 'clic_donacion').length >= 1, 'clic_donacion (Sumate)');
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight)); await espera(400);
  check(eventos(pushes, 'scroll_profundo').some(x => x.porcentaje_scroll === 100), 'scroll_profundo 100 %');

  // Formulario de la Muestra itinerante: tres vías
  const llenar = async () => {
    await page.goto(`${base}/muestra.html#llevar`);
    await page.fill('#form-muestra [name=espacio]', 'Centro Cultural Prueba');
    await page.fill('#form-muestra [name=ciudad]', 'Rosario');
    for (const [k, v] of [['fechas', 'marzo'], ['superficie', '200'], ['nombre', 'Persona De Prueba'], ['correo', 'persona.prueba@example.com'], ['telefono', '1155551234'], ['mensaje', 'Texto privado']])
      if (await page.locator(`#form-muestra [name=${k}]`).count()) await page.fill(`#form-muestra [name=${k}]`, v);
  };
  await llenar();
  await page.click('#form-muestra [type=submit]').catch(() => {}); await espera(500);
  await llenar();
  await page.evaluate(() => document.getElementById('form-muestra').setAttribute('data-endpoint', '/__muestra_ok'));
  await ctx.route(/__muestra_ok/, r => r.fulfill({ status: 200, body: 'ok' }));
  await page.click('#form-muestra [type=submit]'); await espera(500);
  await llenar();
  await page.evaluate(() => document.getElementById('form-muestra').setAttribute('data-endpoint', '/__muestra_falla'));
  await ctx.route(/__muestra_falla/, r => r.fulfill({ status: 500, body: 'x' }));
  await page.click('#form-muestra [type=submit]').catch(() => {}); await espera(500);
  const vias = eventos(pushes, 'enviar_formulario_muestra').map(x => x.via_envio);
  check(vias.join() === 'correo,servidor,correo_respaldo', 'enviar_formulario_muestra por correo, servidor y respaldo', vias);
  check(!eventos(pushes, 'consulta_muestra').length, 'ya no sale consulta_muestra (mandaba la ciudad)');
  sinPII(pushes, 'el sitio institucional');
  console.log('  eventos vistos: ' + [...new Set(pushes.map(x => x.event).filter(Boolean))].sort().join(', '));
  await ctx.close();

  console.log('\nSitio institucional, host de producción simulado');
  const pr = await nuevo(browser);
  for (const h of [PROD, 'www.' + PROD, 'lascajasdeaida.com']) {
    await pr.page.goto(`http://${h}:${INST}/index.html`);
    const c = contexto(pr.pushes).at(-1);
    check(c.entorno === 'produccion' && !('traffic_type' in c) && !('debug_mode' in c), `${h}: produccion, sin traffic_type ni debug_mode`, c);
  }
  await pr.ctx.close();
}

const que = process.argv[2];
const hosts = [PRUEBA, PROD, 'www.' + PROD, 'lascajasdeaida.com'].map(h => `MAP ${h} 127.0.0.1`).join(',');
const browser = await chromium.launch({ args: ['--host-resolver-rules=' + hosts] });
try {
  if (!que || que === 'archivo') await archivo(browser);
  if (!que || que === 'institucional') await institucional(browser);
} finally {
  await browser.close();
}
console.log(`\n${ok} ok, ${fallas} fallas`);
process.exit(fallas ? 1 : 0);
