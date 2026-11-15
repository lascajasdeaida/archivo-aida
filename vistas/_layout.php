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
    <a href="<?= App::url('vocabularios') ?>" class="<?= $sec === 'vocabularios' ? 'activo' : '' ?>">Vocabularios</a>
    <a href="<?= App::url('procesos') ?>" class="<?= $sec === 'procesos' ? 'activo' : '' ?>">Importaciones</a>
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
  <?php foreach (App::avisos() as [$tipo, $texto]): ?>
    <div class="aviso aviso-<?= e($tipo) ?>" role="status"><?= e($texto) ?></div>
  <?php endforeach ?>
  <?php if (!empty($_SESSION['conflicto'])): $cf = $_SESSION['conflicto']; unset($_SESSION['conflicto']); ?>
    <div class="aviso aviso-error">
      <b>No se aplicó: hay cambios posteriores sobre los mismos datos.</b>
      <ul><?php foreach ($cf['conflictos'] as $c): ?><li><?= e($c) ?></li><?php endforeach ?></ul>
      <?php if (App::puede('forzar')): ?>
        <form method="post" action="<?= isset($cf['proceso']) ? App::url('proceso_deshacer', ['id' => $cf['proceso']]) : App::url('deshacer') ?>">
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
/* Doble validación: el primer clic arma el botón y explica qué va a pasar;
   el segundo, dentro de 6 segundos, ejecuta. Se desarma solo. */
document.addEventListener('click', function (ev) {
  var b = ev.target.closest('[data-doble]');
  if (!b) return;
  if (b.dataset.armado === '1') { return; }
  ev.preventDefault();
  document.querySelectorAll('[data-armado="1"]').forEach(function (o) { if (o !== b) desarmar(o); });
  b.dataset.original = b.innerHTML;
  b.dataset.armado = '1';
  b.classList.add('armado');
  b.innerHTML = '⚠ ' + b.dataset.doble;
  b._t = setTimeout(function () { desarmar(b); }, 6000);
}, true);
function desarmar(b) {
  clearTimeout(b._t);
  if (b.dataset.armado !== '1') return;
  b.dataset.armado = '0';
  b.classList.remove('armado');
  b.innerHTML = b.dataset.original;
}
document.addEventListener('keydown', function (ev) {
  if (ev.key === 'Escape') document.querySelectorAll('[data-armado="1"]').forEach(desarmar);
});
</script>
</body>
</html>
