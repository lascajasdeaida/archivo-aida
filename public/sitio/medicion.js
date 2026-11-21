/* Las cajas de Aída — medición del archivo (GTM / GA4). Detalle en docs/medicion.md.
   Lo carga vistas/publico/_sitio.php solo si config.php trae 'analitica' => ['gtm' => …] y solo en el sitio público
   (nunca en el backoffice ni en su vista previa). El contexto de la página (entorno, sección, registro…) ya lo empujó el
   <head> antes de GTM: window.aidaMedicion.
   Es el único lugar del archivo que escribe en el dataLayer: explorar.js y sitio.js llaman a window.aidaMedir(), que no
   existe si la medición está apagada. Nada que identifique personas: los textos libres se limpian acá. */
(function () {
  'use strict';
  const cfg = window.aidaMedicion;
  if (!cfg) return;
  window.dataLayer = window.dataLayer || [];

  // Búsquedas y otros textos libres: sin correos ni números largos (teléfono, DNI), máximo 100 caracteres (límite de GA4)
  function limpiar(v) {
    if (typeof v !== 'string') return v;
    return v.replace(/[^\s@]+@[^\s@]+/g, '[correo]')
      .replace(/\+?\d[\d\s().-]{6,}\d/g, m => (m.replace(/\D/g, '').length >= 7 ? '[numero]' : m))
      .replace(/\s+/g, ' ').trim().slice(0, 100);
  }

  function medir(evento, datos) {
    const carga = { event: evento };
    Object.entries(datos || {}).forEach(([k, v]) => {
      if (v === undefined || v === null || v === '') return;
      carga[k] = limpiar(v);
    });
    // El registro y la caja de la página van en todos los eventos de la ficha (si el evento no trae otros)
    ['registro_codigo', 'caja_numero'].forEach(k => { if (carga[k] === undefined && cfg.pagina[k] !== undefined) carga[k] = cfg.pagina[k]; });
    window.dataLayer.push(carga);
  }
  window.aidaMedir = medir;
  window.aidaMedir.limpiar = limpiar;

  // Búsquedas lanzadas desde otra página (inicio, caja, cabecera): Explorar las mide al cargar con su cantidad de resultados
  document.addEventListener('submit', e => {
    const f = e.target;
    if (!f.matches || !f.matches('form[role=search]') || f.id === 'form-explorar') return;
    const origen = f.dataset.eventoBusqueda || (f.querySelector('input[name=caja]') ? 'caja' : 'otra');
    try { sessionStorage.setItem('aida-busqueda-origen', origen); } catch (x) { /* sin sessionStorage */ }
  }, true);

  // Ver / descargar documento: al hacer clic, porque el archivo se entrega por redirección (Drive) y la página no la ve
  document.addEventListener('click', e => {
    const b = e.target.closest && e.target.closest('[data-medicion]');
    if (!b) return;
    let datos = {};
    try { datos = JSON.parse(b.dataset.medicionDatos || '{}'); } catch (x) { /* atributo roto: se manda sin datos */ }
    if (b.matches('[data-visor-descargar]')) {
      // El botón del visor descarga la parte que está abierta
      const abierta = document.querySelector('[data-visor][aria-expanded=true]');
      if (abierta) { try { datos = JSON.parse(abierta.dataset.medicionDatos || '{}'); } catch (x) { /* nada */ } }
      datos.ubicacion_enlace = 'visor';
    }
    medir(b.dataset.medicion, datos);
  }, true);

  document.addEventListener('DOMContentLoaded', () => {
    const v = cfg.vista;
    if (!v) return;
    if (v.evento === 'enviar_consulta') {
      // Una vez por envío: recargar la confirmación no cuenta otra consulta (el número queda solo en el navegador)
      const clave = 'aida-consulta-' + location.pathname + location.search;
      try { if (sessionStorage.getItem(clave)) return; sessionStorage.setItem(clave, '1'); } catch (x) { /* nada */ }
    }
    medir(v.evento, v.datos);
  });
})();
