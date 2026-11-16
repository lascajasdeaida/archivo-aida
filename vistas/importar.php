<?php use Aida\Web\App; $titulo = 'Importar el Excel';
$mb = fn(int $b) => number_format($b / 1048576, $b < 10485760 ? 1 : 0, ',', '.') . ' MB';
$cajasTxt = fn(?array $c) => $c === null ? 'todas las cajas' : (count($c) === 1 ? 'la caja ' . $c[0] : 'las cajas ' . implode(', ', $c));
// Bloque de resultados (simulación o importación real): contadores e incidencias agrupadas
$resultado = function (array $x) { ?>
  <div class="chips" style="margin-bottom:12px">
    <?php foreach ($x['cuentas'] as $k => $v): ?><span class="chip <?= str_contains($k, 'nuev') ? 'chip-ok' : '' ?>"><b><?= (int)$v ?></b> <?= e($k) ?></span><?php endforeach ?>
    <?php if (!$x['cuentas']): ?><span class="nota">Sin cambios.</span><?php endif ?>
  </div>
  <?php if ($x['grupos']): ?>
    <h3>Avisos (<?= (int)$x['total'] ?>)<?= $x['a_validar'] ? ' · ' . (int)$x['a_validar'] . ' quedan para validar' : '' ?></h3>
    <?php foreach ($x['grupos'] as $g): ?>
      <details class="leyenda <?= $g['validar'] ? 'leyenda-warn' : '' ?>" style="display:block">
        <summary><b><?= e($g['titulo']) ?></b> · <?= (int)$g['n'] ?><?= $g['validar'] ? ' <span class="chip chip-warn">para validar</span>' : '' ?></summary>
        <p class="nota" style="margin:6px 0"><?= e($g['leyenda']) ?></p>
        <table class="tabla-chica">
          <?php foreach ($g['ejemplos'] as [$donde, $det]): ?><tr><td class="num"><?= e($donde) ?></td><td><?= e($det) ?></td></tr><?php endforeach ?>
        </table>
        <?php if ($g['n'] > count($g['ejemplos'])): ?><p class="nota">… y <?= $g['n'] - count($g['ejemplos']) ?> más.</p><?php endif ?>
      </details>
    <?php endforeach ?>
  <?php endif ?>
<?php };
?>
<div class="migas"><a href="<?= App::url('procesos') ?>">Importaciones</a> / Importar el Excel</div>
<div class="cabecera">
  <div>
    <p class="eyebrow">Carga del catálogo</p>
    <h1>Importar el Excel</h1>
    <p class="nota">Primero se simula: ves qué va a cambiar sin guardar nada. Después confirmás la importación del mismo archivo.</p>
  </div>
</div>

<?php if ($sim): ?>
  <section class="panel">
    <h2>Simulación · nada se guardó todavía</h2>
    <p><b><?= e($sim['original']) ?></b> (<?= $mb((int)$sim['bytes']) ?>) · <?= e($cajasTxt($sim['cajas'])) ?> ·
       simulado el <?= date('d/m/Y H:i', (int)$sim['en']) ?> en <?= number_format((float)$sim['segundos'], 1, ',', '.') ?> s</p>
    <?php $resultado($sim); ?>
    <div class="acciones acciones-guardar">
      <form method="post" action="<?= App::url('importar_confirmar') ?>">
        <?= App::campoToken() ?>
        <input type="hidden" name="simulacion" value="<?= e($sim['token']) ?>">
        <button class="btn btn-primario" data-doble="Se guardan en la base los cambios de arriba. Se puede deshacer completa desde Importaciones. ¿Confirmar?">Importar de verdad</button>
      </form>
      <form method="post" action="<?= App::url('importar_descartar') ?>">
        <?= App::campoToken() ?>
        <button class="btn">Descartar</button>
      </form>
      <span class="nota">La simulación vale por 2 horas. Lo editado a mano en el backoffice no se pisa.</span>
    </div>
  </section>
<?php else: ?>
  <?php if ($hecha): ?>
    <section class="panel">
      <h2>Importación #<?= (int)$hecha['proceso'] ?> terminada</h2>
      <p><b><?= e($hecha['original']) ?></b> · <?= e($cajasTxt($hecha['cajas'])) ?> · <?= number_format((float)$hecha['segundos'], 1, ',', '.') ?> s</p>
      <?php $resultado($hecha); ?>
      <div class="acciones">
        <a class="btn" href="<?= App::url('procesos') ?>">Ver en la lista de importaciones</a>
        <?php if ($hecha['a_validar']): ?><a class="btn" href="<?= App::url('notificaciones') ?>">🔔 Ir a las verificaciones</a><?php endif ?>
      </div>
    </section>
  <?php endif ?>

  <section class="panel">
    <h2>Subir el Excel</h2>
    <form method="post" action="<?= App::url('importar') ?>" enctype="multipart/form-data" id="form-subir">
      <?= App::campoToken() ?>
      <label>Archivo del catálogo (.xlsx, hasta <?= $mb($maxBytes) ?>)
        <input type="file" name="excel" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required data-max="<?= (int)$maxBytes ?>">
      </label>
      <label>Cajas (opcional)
        <input type="text" name="cajas" placeholder="Vacío = todas. Ej.: 1, 2, 10-14" inputmode="numeric" autocomplete="off">
      </label>
      <p class="nota">El archivo de Drive “Catálogo Aída Parte 1 BACKUP CONSERVACIÓN”: Archivo → Descargar → Microsoft Excel (.xlsx).</p>
      <div class="acciones">
        <button class="btn btn-primario">Simular importación</button>
        <span class="nota" aria-live="polite" id="subiendo" hidden>Subiendo y simulando… puede tardar unos segundos.</span>
      </div>
    </form>
  </section>
<?php endif ?>
<script>
(function () {
  var f = document.getElementById('form-subir');
  if (!f) return;
  var inp = f.querySelector('input[type=file]');
  inp.addEventListener('change', function () {
    var a = inp.files[0];
    inp.setCustomValidity(!a ? '' : !/\.xlsx$/i.test(a.name) ? 'Tiene que ser un Excel .xlsx.'
      : a.size > +inp.dataset.max ? 'El archivo supera el máximo permitido.' : '');
  });
  f.addEventListener('submit', function () {
    f.querySelector('button').disabled = true;
    document.getElementById('subiendo').hidden = false;
  });
})();
</script>
