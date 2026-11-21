<?php use Aida\Web\App; $titulo = 'Base de datos'; ?>
<div class="cabecera">
  <div>
    <p class="eyebrow">Administración</p>
    <h1>Base de datos</h1>
    <p class="nota">Cambios de estructura que trae cada actualización del sistema. Se aplican una sola vez y en orden.</p>
  </div>
</div>
<?php if ($pendientes): ?>
  <section class="panel confirmar">
    <h2>Hay <?= count($pendientes) ?> <?= count($pendientes) === 1 ? 'actualización' : 'actualizaciones' ?> pendiente<?= count($pendientes) === 1 ? '' : 's' ?></h2>
    <p>Hasta aplicarlas, algunas pantallas pueden fallar. Conviene hacer un backup antes. Cada una se puede volver a correr sin riesgo si se corta.</p>
    <form method="post" action="<?= App::url('base') ?>" class="acciones">
      <?= App::campoToken() ?>
      <button class="btn btn-primario" data-doble="Se modifica la estructura de la base.">Aplicar ahora</button>
    </form>
  </section>
<?php else: ?>
  <section class="panel"><p>✓ La base está al día.</p></section>
<?php endif ?>
<?php if ($modificadas): ?>
  <div class="aviso aviso-error">Estas migraciones cambiaron después de aplicadas (no deberían editarse): <?= e(implode(', ', $modificadas)) ?>.</div>
<?php endif ?>
<table>
  <thead><tr><th>Versión</th><th>Archivo</th><th>Estado</th></tr></thead>
  <tbody>
  <?php foreach ($archivos as $v => $f): ?>
    <tr><td><?= e($v) ?></td><td><?= e(basename($f)) ?></td>
      <td><?= isset($aplicadas[$v]) ? '<span class="chip chip-ok">Aplicada ' . fecha($aplicadas[$v]['aplicada_en']) . '</span>' : '<span class="chip chip-warn">Pendiente</span>' ?></td></tr>
  <?php endforeach ?>
  </tbody>
</table>
