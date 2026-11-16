<?php use Aida\Web\App; $u = App::usuario(); $pend = $u ? App::pendientes() : 0; $sec = $_GET['r'] ?? 'cajas'; ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($titulo ?? 'Backoffice') ?> · Las cajas de Aída</title>
<link rel="stylesheet" href="estilo.css">
</head>
<body>
<header class="barra">
  <a class="marca" href="<?= App::url('cajas') ?>"><span class="sello">Archivo</span> Las cajas de Aída</a>
  <nav>
    <a href="<?= App::url('cajas') ?>" class="<?= in_array($sec, ['cajas', 'caja', 'registro', 'caja_nueva', 'registro_nuevo'], true) ? 'activo' : '' ?>">Cajas</a>
    <a href="<?= App::url('vocabularios') ?>" class="<?= in_array($sec, ['vocabularios', 'vocab_curar', 'vocab_informe', 'curaduria_deshacer'], true) ? 'activo' : '' ?>">Vocabularios</a>
    <a href="<?= App::url('procesos') ?>" class="<?= in_array($sec, ['procesos', 'importar', 'proceso_deshacer', 'correccion'], true) ? 'activo' : '' ?>">Importaciones</a>
    <?php if ($u && App::puede('migraciones')): ?><a href="<?= App::url('base') ?>" class="<?= $sec === 'base' ? 'activo' : '' ?>">Base de datos</a><?php endif ?>
    <a href="<?= App::url('notificaciones') ?>" class="campana <?= $sec === 'notificaciones' ? 'activo' : '' ?>" title="Verificaciones pendientes">
      🔔 <span>Verificaciones</span><?php if ($pend): ?> <b class="contador"><?= $pend ?></b><?php endif ?>
    </a>
  </nav>
  <?php if ($u): ?>
  <form method="post" action="<?= App::url('salir') ?>" class="usuario">
    <?= App::campoToken() ?>
    <span><?= e($u['nombre'] ?: $u['email']) ?> · <?= e(App::ROLES[$u['rol']] ?? $u['rol']) ?></span>
    <button class="link">Salir</button>
  </form>
  <?php endif ?>
</header>

<main class="contenido">
  <?php if ($u && App::puede('migraciones') && $sec !== 'base'):
      try { $migPend = count((new \Aida\Migrador(aida_db()))->pendientes()); } catch (\Throwable $e) { $migPend = 0; }
      if ($migPend): ?>
    <div class="aviso aviso-error" role="alert"><b>La base tiene <?= $migPend ?> <?= $migPend === 1 ? 'actualización' : 'actualizaciones' ?> pendiente<?= $migPend === 1 ? '' : 's' ?>.</b>
      Algunas pantallas pueden fallar hasta aplicarlas. <a href="<?= App::url('base') ?>">Ir a Base de datos</a></div>
  <?php endif; endif ?>
  <?php foreach (App::avisos() as [$tipo, $texto]): ?>
    <div class="aviso aviso-<?= e($tipo) ?>" role="status"><?= e($texto) ?></div>
  <?php endforeach ?>
  <?php if (!empty($_SESSION['conflicto'])): $cf = $_SESSION['conflicto']; unset($_SESSION['conflicto']); ?>
    <div class="aviso aviso-error">
      <b>No se aplicó: hay cambios posteriores sobre los mismos datos.</b>
      <ul><?php foreach ($cf['conflictos'] as $c): ?><li><?= e($c) ?></li><?php endforeach ?></ul>
      <?php if (App::puede('forzar')): ?>
        <form method="post" action="<?= isset($cf['curaduria']) ? App::url('curaduria_deshacer', ['id' => $cf['curaduria']]) : (isset($cf['proceso']) ? App::url('proceso_deshacer', ['id' => $cf['proceso']]) : App::url('deshacer')) ?>">
          <?= App::campoToken() ?>
          <?php if (isset($cf['cambio'])): ?><input type="hidden" name="cambio" value="<?= (int)$cf['cambio'] ?>"><?php endif ?>
          <input type="hidden" name="forzar" value="1">
          <input type="hidden" name="volver" value="<?= e($_SERVER['REQUEST_URI'] ? '?' . ($_SERVER['QUERY_STRING'] ?? '') : '') ?>">
          <p class="nota">Podés deshacer primero el cambio más nuevo, o forzar: los campos en conflicto vuelven igual al valor anterior.</p>
          <button class="btn btn-peligro" data-doble="Los campos en conflicto se pisan. ¿Confirmar?">Forzar de todos modos</button>
        </form>
      <?php endif ?>
    </div>
  <?php endif ?>
  <?= $contenido ?>
</main>
<footer class="pie">Archivo Aída Bortnik · backoffice · P3DESIGN</footer>
<script>
/* Doble validación: el primer clic arma el botón ("Confirmar") y al lado explica qué va a pasar;
   el segundo, dentro de 10 segundos, ejecuta. Se desarma solo o con Esc. */
document.addEventListener('click', function (ev) {
  var b = ev.target.closest('[data-doble]');
  if (!b) return;
  if (b.dataset.armado === '1') { return; }
  ev.preventDefault();
  document.querySelectorAll('[data-armado="1"]').forEach(function (o) { if (o !== b) desarmar(o); });
  b.dataset.original = b.innerHTML;
  b.dataset.armado = '1';
  b.classList.add('armado');
  b.innerHTML = 'Confirmar';
  var s = document.createElement('span');
  s.className = 'aviso-doble'; s.setAttribute('role', 'alert');
  s.textContent = '⚠ ' + b.dataset.doble.replace(/\s*¿Confirmar\?$/, '') + ' Hacé clic de nuevo para confirmar.';
  b.insertAdjacentElement('afterend', s); b._aviso = s;
  b._t = setTimeout(function () { desarmar(b); }, 10000);
}, true);
function desarmar(b) {
  clearTimeout(b._t);
  if (b.dataset.armado !== '1') return;
  b.dataset.armado = '0';
  b.classList.remove('armado');
  b.innerHTML = b.dataset.original;
  if (b._aviso) { b._aviso.remove(); b._aviso = null; }
}
document.addEventListener('keydown', function (ev) {
  if (ev.key === 'Escape') document.querySelectorAll('[data-armado="1"]').forEach(desarmar);
});
/* Tablas anchas: se envuelven para desplazarse en pantallas chicas */
document.querySelectorAll('.contenido table').forEach(function (t) {
  if (t.closest('.historial, aside, .tabla-scroll')) return;
  var w = document.createElement('div'); w.className = 'tabla-scroll'; w.tabIndex = 0; w.setAttribute('role', 'region'); w.setAttribute('aria-label', 'Tabla (desplazable)');
  t.parentNode.insertBefore(w, t); w.appendChild(t);
});
/* Vista previa de PDF: el iframe se crea recién al pedirla, para no bajar el archivo con cada ficha */
(function () {
  var panel = document.getElementById('vista-previa');
  if (!panel) return;
  document.querySelectorAll('[data-vista]').forEach(function (b) {
    b.addEventListener('click', function () {
      var f = panel.querySelector('iframe');
      if (!f) { f = document.createElement('iframe'); f.title = 'Vista previa del documento'; panel.appendChild(f); }
      if (f.getAttribute('src') !== b.dataset.vista) f.src = b.dataset.vista;
      panel.querySelector('.nombre').textContent = b.dataset.nombre;
      panel.hidden = false; panel.focus(); panel.scrollIntoView({behavior: 'smooth', block: 'start'});
    });
  });
  panel.querySelector('.cerrar').addEventListener('click', function () {
    var f = panel.querySelector('iframe'); if (f) f.remove();
    panel.hidden = true;
  });
})();
/* Selector de términos con casillas y chips */
document.querySelectorAll('.selector-terminos').forEach(function (sel) {
  var cajas = sel.querySelectorAll('.lista input[type=checkbox]');
  var zona = sel.querySelector('.elegidos');
  function pintar() {
    zona.innerHTML = '';
    var n = 0;
    cajas.forEach(function (c) {
      if (!c.checked) return; n++;
      var chip = document.createElement('span'); chip.className = 'chip-termino';
      chip.textContent = c.value;
      if (!c.disabled) {
        var x = document.createElement('button'); x.type = 'button'; x.textContent = '×';
        x.setAttribute('aria-label', 'Quitar ' + c.value);
        x.onclick = function () { c.checked = false; pintar(); };
        chip.appendChild(x);
      }
      zona.appendChild(chip);
    });
    if (!n) { zona.innerHTML = '<span class="vacio">Ninguno elegido</span>'; }
  }
  cajas.forEach(function (c) { c.addEventListener('change', pintar); });
  var q = sel.querySelector('.buscar');
  if (q) q.addEventListener('input', function () {
    var t = q.value.toLowerCase();
    sel.querySelectorAll('.lista label').forEach(function (l) { l.hidden = t && l.textContent.toLowerCase().indexOf(t) < 0; });
  });
  pintar();
});
</script>
</body>
</html>
