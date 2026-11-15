/* Las cajas de Aída — comportamiento del sitio público (vistas/publico/).
   Todo funciona sin JS (los enlaces son reales); esto suma el menú en celular, «Leer más», el visor y «Copiar». */
(function () {
  'use strict';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));

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

    // Copiar cita / enlace
    const aviso = $('#copiado');
    $$('[data-copiar], [data-copiar-texto]').forEach(b => b.addEventListener('click', () => {
      const texto = b.dataset.copiarTexto || ($(b.dataset.copiar) || {}).textContent || '';
      const ok = () => { if (aviso) aviso.textContent = b.dataset.copiado || 'Copiado.'; };
      if (navigator.clipboard) navigator.clipboard.writeText(texto.trim()).then(ok, () => { if (aviso) aviso.textContent = 'No se pudo copiar: seleccioná el texto y copialo a mano.'; });
    }));
  });
})();
