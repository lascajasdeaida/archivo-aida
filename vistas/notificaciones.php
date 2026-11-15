<?php use Aida\Web\App; use Aida\Alertas;
$titulo = 'Verificaciones';
$volver = '?' . ($_SERVER['QUERY_STRING'] ?? 'r=notificaciones');
$base = ['tab' => $tab] + array_filter(['caja' => $_GET['caja'] ?? null, 'codigo' => $_GET['codigo'] ?? null]);
?>
<div class="cabecera">
  <div>
    <p class="eyebrow">Notificaciones</p>
    <h1>Verificaciones</h1>
    <p class="nota">Lo que surge de cada importación del Excel y de cada escaneo de archivos. Las pendientes necesitan una decisión.</p>
  </div>
</div>

<nav class="tabs">
  <a href="<?= App::url('notificaciones', ['tab' => 'pendientes']) ?>" class="<?= $tab === 'pendientes' ? 'activo' : '' ?>">Pendientes <b class="contador"><?= (int)$cont['pendientes'] ?></b></a>
  <a href="<?= App::url('notificaciones', ['tab' => 'informativas']) ?>" class="<?= $tab === 'informativas' ? 'activo' : '' ?>">Informativas (<?= (int)$cont['informativas'] ?>)</a>
  <a href="<?= App::url('notificaciones', ['tab' => 'resueltas']) ?>" class="<?= $tab === 'resueltas' ? 'activo' : '' ?>">Resueltas (<?= (int)$cont['resueltas'] ?>)</a>
</nav>

<form method="get" class="filtros">
  <input type="hidden" name="r" value="notificaciones"><input type="hidden" name="tab" value="<?= e($tab) ?>">
  <label>Caja<input type="number" name="caja" min="1" value="<?= e($_GET['caja'] ?? '') ?>"></label>
  <label>Tipo<select name="codigo"><option value="">Todos</option>
    <?php foreach ($tipos as $t): ?><option value="<?= e($t['codigo']) ?>" <?= ($_GET['codigo'] ?? '') === $t['codigo'] ? 'selected' : '' ?>><?= e(Alertas::de($t['codigo'])['titulo']) ?> (<?= (int)$t['n'] ?>)</option><?php endforeach ?>
  </select></label>
  <button class="btn">Filtrar</button>
  <?php if (!empty($_GET['caja']) || !empty($_GET['codigo'])): ?><a class="btn" href="<?= App::url('notificaciones', ['tab' => $tab]) ?>">Limpiar</a><?php endif ?>
</form>

<?php if (!$items): ?>
  <section class="panel"><p><?= $tab === 'pendientes' ? '✓ No hay verificaciones pendientes.' : 'No hay nada para mostrar.' ?></p></section>
<?php else: ?>
<form method="post" action="<?= App::url('validar') ?>">
  <?= App::campoToken() ?>
  <input type="hidden" name="volver" value="<?= e($volver) ?>">
  <?php foreach ($items as $i): $al = Alertas::de($i['codigo']); $donde = $i['caja'] ? 'C' . (int)$i['caja'] . ($i['registro'] ? 'R' . (int)$i['registro'] : '') : ''; ?>
    <article class="tarjeta <?= $tab === 'resueltas' ? 'resuelta' : ($i['requiere_validacion'] ? '' : 'info') ?>">
      <div><?php if ($tab === 'pendientes' && App::puede('validar')): ?><input type="checkbox" name="ids[]" value="<?= (int)$i['id'] ?>" aria-label="Elegir"><?php endif ?></div>
      <div>
        <h3><?= e($al['titulo']) ?>
          <?php if ($donde): ?> ·
            <?php if ($i['registro_id']): ?><a href="<?= App::url('registro', ['id' => $i['registro_id']]) ?>"><?= $donde ?></a>
            <?php elseif ($i['caja_id']): ?><a href="<?= App::url('caja', ['id' => $i['caja_id']]) ?>"><?= $donde ?></a>
            <?php else: ?><?= $donde ?><?php endif ?>
          <?php endif ?>
        </h3>
        <div class="det"><?= e($al['leyenda']) ?></div>
        <div class="nota"><?= e($i['detalle']) ?></div>
        <div class="accion">Qué hacer: <?= e($al['accion']) ?></div>
        <div class="nota">Surgió en <?= $i['proceso_tipo'] === 'escaneo' ? 'el escaneo' : 'la importación' ?> #<?= (int)$i['proceso_id'] ?> · <?= fecha($i['iniciado_en']) ?></div>
        <?php if ($tab === 'resueltas'): ?>
          <div class="nota">✓ <?= e($i['resolucion']) ?> — <?= e($i['resuelta_por_nombre'] ?: $i['resuelta_por_email'] ?: 'automático') ?>, <?= fecha($i['resuelta_en']) ?></div>
        <?php endif ?>
      </div>
    </article>
  <?php endforeach ?>
  <?php if ($tab === 'pendientes' && App::puede('validar')): ?>
    <div class="lote">
      <label class="check"><input type="checkbox" onclick="document.querySelectorAll('input[name=&quot;ids[]&quot;]').forEach(c=>c.checked=this.checked)"> Todas</label>
      <input type="text" name="nota" placeholder="Qué decidiste (queda registrado con tu usuario)" required>
      <button class="btn btn-primario">Validar las elegidas</button>
    </div>
  <?php endif ?>
</form>
<?php endif ?>
