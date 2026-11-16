/* Las cajas de Aída — comportamiento del archivo (vistas/publico/).
   Todo funciona sin JS (los enlaces son reales); esto suma el atajo “/”, el menú en celular, “Leer más”, el visor y “Copiar”.
   El archivo no tipea títulos (lenguaje de sistema, 16/11/2026): el cursor de la máquina queda solo en el buscador (CSS). */
(function () {
  'use strict';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));

  // Atajo “/”: lleva al buscador de la página (Explorar, inicio, caja o Las cajas) o, si no hay, a Explorar.
  // No actúa mientras se escribe en un campo ni con modificadores.
  document.addEventListener('keydown', e => {
    if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey || e.defaultPrevented) return;
    const t = e.target;
    if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
    const campo = $$('#q, #q-inicio, #q-caja, #filtro-cajas').find(c => c.offsetParent !== null);
    e.preventDefault();
    if (campo) { campo.focus(); campo.select(); }
    else { const ir = $('.cabecera__buscar'); if (ir) location.href = ir.href; }
  });

  document.addEventListener('DOMContentLoaded', () => {
    // Menú en celular
    const mb = $('.menu-boton'), nav = $('#nav-principal');
    if (mb && nav) mb.addEventListener('click', () => {
      const ab = mb.getAttribute('aria-expanded') !== 'true';
      mb.setAttribute('aria-expanded', String(ab));
      nav.classList.toggle('abierta', ab);
    });

    // Texto plegado a 4 líneas: el botón aparece solo si hay más texto
    $$('[data-plegable]').forEach(pl => {
      const b = $('[data-leer-mas]', pl), cuerpo = $('.cuerpo', pl);
      if (!b || !cuerpo) return;
      if (cuerpo.scrollHeight <= cuerpo.clientHeight + 2) { pl.classList.remove('plegado'); return; }
      b.hidden = false;
      b.addEventListener('click', () => {
        const ab = b.getAttribute('aria-expanded') !== 'true';
        b.setAttribute('aria-expanded', String(ab));
        b.textContent = ab ? 'Leer menos' : 'Leer más';
        pl.classList.toggle('plegado', !ab);
      });
    });

    // Visor: el iframe se crea recién al abrirlo (los PDF pesan hasta 780 MB)
    const visor = $('#visor');
    if (visor) {
      const zona = $('[data-visor-zona]', visor), desc = $('[data-visor-descargar]', visor);
      let origen = null;
      const cerrar = () => {
        if (document.fullscreenElement) document.exitFullscreen();
        visor.hidden = true;
        zona.textContent = '';
        if (origen) { origen.setAttribute('aria-expanded', 'false'); origen.focus(); }
      };
      $$('[data-visor]').forEach(b => b.addEventListener('click', e => {
        e.preventDefault();
        $$('[data-visor]').forEach(o => o.setAttribute('aria-expanded', 'false'));
        origen = b;
        zona.textContent = '';
        const f = document.createElement('iframe');
        f.className = 'visor__marco';
        f.src = b.dataset.visor;
        f.title = 'Documento ' + b.dataset.visorTitulo;
        zona.appendChild(f);
        if (desc && b.dataset.descarga) desc.href = b.dataset.descarga;
        visor.hidden = false;
        b.setAttribute('aria-expanded', 'true');
        visor.scrollIntoView({ block: 'start' });
        $('[data-visor-cerrar]', visor).focus();
      }));
      $('[data-visor-cerrar]', visor).addEventListener('click', cerrar);
      $('[data-visor-pantalla]', visor).addEventListener('click', () => visor.requestFullscreen && visor.requestFullscreen());
      visor.addEventListener('keydown', e => { if (e.key === 'Escape') cerrar(); });
    }

    // Estadísticas (docs/medicion.md): solo si medicion.js está cargado (sitio público con GTM configurado). Nunca datos personales.
    const evento = (nombre, datos = {}) => { if (window.aidaMedir) window.aidaMedir(nombre, datos); };

    // Foco en el resumen de errores (formulario de consulta) o en la confirmación
    const enfocar = $('[data-enfocar]') || $('#ok');
    if (enfocar) enfocar.focus();
    const fc = $('#form-consulta');
    if (fc) {
      let iniciada = false;
      // Solo qué campos fallaron (sus nombres), nunca lo que se escribió
      fc.addEventListener('focusin', () => { if (!iniciada) { iniciada = true; evento('iniciar_consulta', { registro_codigo: fc.dataset.codigo }); } });
      const errores = $$('#resumen-errores li a').map(a => a.getAttribute('href').slice(1));
      if (errores.length) evento('error_consulta', { registro_codigo: fc.dataset.codigo, campos_error: errores.join(',') });
    }
    // La consulta enviada (enviar_consulta) la mide medicion.js con los datos que manda el servidor en la confirmación

    // Las cajas: el filtro por nombre o número se aplica mientras se escribe (sin JS, con el botón “Aplicar”)
    const fcajas = $('[data-filtro-cajas]');
    if (fcajas) {
      const norm = t => String(t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
      const input = $('#filtro-cajas', fcajas), items = $$('#lista-cajas > li'), cont = $('#conteo-cajas');
      const total = items.length;
      input.addEventListener('input', () => {
        const q = norm(input.value.trim());
        let n = 0;
        items.forEach(li => { const ok = !q || norm(li.textContent).includes(q); li.hidden = !ok; if (ok) n++; });
        cont.textContent = n === total ? total + (total === 1 ? ' caja' : ' cajas') : n + ' de ' + total + ' cajas';
      });
      $('#orden-cajas', fcajas).addEventListener('change', () => fcajas.submit());
    }

    // “Volver a los resultados” en la ficha si se llegó desde Explorar
    const volver = $('[data-volver]');
    if (volver) {
      try {
        const u = sessionStorage.getItem('aida-volver');
        if (u && document.referrer && document.referrer.split('#')[0] === u.split('#')[0]) { volver.querySelector('a').href = u; volver.hidden = false; }
      } catch (e) { /* sin sessionStorage */ }
    }

    // Copiar cita / enlace
    const aviso = $('#copiado');
    $$('[data-copiar], [data-copiar-texto]').forEach(b => b.addEventListener('click', () => {
      const texto = b.dataset.copiarTexto || ($(b.dataset.copiar) || {}).textContent || '';
      const ok = () => {
        if (aviso) aviso.textContent = b.dataset.copiado || 'Copiado.';
        evento('compartir_registro', { metodo_compartir: b.dataset.copiar === '#cita' ? 'copiar_cita' : 'copiar_enlace' });
      };
      if (navigator.clipboard) navigator.clipboard.writeText(texto.trim()).then(ok, () => { if (aviso) aviso.textContent = 'No se pudo copiar: seleccioná el texto y copialo a mano.'; });
    }));
  });
})();
