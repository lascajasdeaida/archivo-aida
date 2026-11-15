<?php
// Parcial: alertas abiertas de una caja o registro, con validación rápida. Recibe $alertas y $volver.
use Aida\Web\App;
if (!$alertas) return;
$validar = array_filter($alertas, fn($i) => $i['requiere_validacion']);
$info = array_filter($alertas, fn($i) => !$i['requiere_validacion']);
$cajaNum = isset($caja) ? (int)$caja['numero'] : null;
?>
<?php if (!$validar): ?>
  <p class="nota">ℹ︎ <?= count($info) ?> aviso<?= count($info) === 1 ? '' : 's' ?> informativo<?= count($info) === 1 ? '' : 's' ?>
    (<?= e(implode(', ', array_unique(array_map(fn($i) => mb_strtolower($i['_alerta']['titulo']), $info)))) ?>).
    <a href="<?= App::url('notificaciones', ['tab' => 'informativas', 'caja' => $cajaNum]) ?>">Ver</a></p>
<?php return; endif ?>
<section class="panel" id="alertas">
  <h2>🔔 Verificaciones pendientes de <?= isset($r) ? 'este registro' : 'esta caja' ?></h2>
  <?php if ($info): ?><p class="nota">Además hay <?= count($info) ?> aviso<?= count($info) === 1 ? '' : 's' ?> informativo<?= count($info) === 1 ? '' : 's' ?>. <a href="<?= App::url('notificaciones', ['tab' => 'informativas', 'caja' => $cajaNum]) ?>">Ver</a></p><?php endif ?>
  <?php foreach ($validar as $i): $al = $i['_alerta']; ?>
    <div class="leyenda <?= $i['requiere_validacion'] ? 'leyenda-warn' : '' ?>">
      <div>
        <b><?= e($al['titulo']) ?></b><?php if (!isset($r) && $i['registro']): ?> · <?= 'C' . (int)$i['caja'] . 'R' . (int)$i['registro'] ?><?php endif ?><br>
        <span><?= e($al['leyenda']) ?></span><br>
        <span class="nota"><?= e($i['detalle']) ?></span><br>
        <span class="nota">Qué hacer: <?= e($al['accion']) ?></span>
      </div>
      <?php if ($i['requiere_validacion'] && App::puede('validar')): ?>
      <form method="post" action="<?= App::url('validar') ?>" style="min-width:220px">
        <?= App::campoToken() ?>
        <input type="hidden" name="ids[]" value="<?= (int)$i['id'] ?>">
        <input type="hidden" name="volver" value="<?= e($volver) ?>">
        <input type="text" name="nota" placeholder="Qué decidiste" required>
        <button class="btn btn-chico btn-crear" style="margin-top:6px">✓ Validar</button>
      </form>
      <?php endif ?>
    </div>
  <?php endforeach ?>
</section>
