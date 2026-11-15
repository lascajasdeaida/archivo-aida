/* Vista previa del backoffice: las fichas y las cajas navegan dentro de la vista previa (sus enlaces ya apuntan
   a ?r=vista_previa). Las páginas del sitio que todavía no existen (inicio, explorar, consulta…) se avisan acá
   en vez de llevar a un 404. */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    const estado = document.querySelector('[data-vp-estado]');
    const avisar = () => {
      if (!estado) return;
      estado.textContent = '';
      setTimeout(() => { estado.textContent = 'Esa página del sitio todavía no está hecha: en la vista previa solo se navega entre fichas y cajas.'; }, 50);
      estado.scrollIntoView({ block: 'nearest' });
    };
    const interna = a => {
      const u = new URL(a.href, location.href);
      if (u.origin !== location.origin) return false;
      if (u.pathname === location.pathname && u.search === location.search) return true; // ancla en la página
      return /\/admin\/(index\.php)?$/.test(u.pathname) || /\/archivo\.php$/.test(u.pathname);
    };
    document.addEventListener('click', e => {
      const a = e.target.closest('a[href]');
      if (!a || a.closest('.vp-barra') || interna(a)) return;
      e.preventDefault();
      avisar();
    });
    document.addEventListener('submit', e => { e.preventDefault(); avisar(); });
  });
})();
