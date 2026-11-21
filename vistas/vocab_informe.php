<?php use Aida\Web\App; use Aida\Vocabulario; $titulo = 'Cambios de curaduría';
$vigentes = count(array_filter($items, fn($x) => !$x['deshecho']));
?>
<div class="migas"><a href="<?= App::url('vocabularios') ?>">Vocabularios</a> / Cambios de curaduría</div>
<div class="cabecera">
  <div>
    <p class="eyebrow">Curaduría</p>
    <h1>Cambios de curaduría</h1>
    <p class="nota">Cada término movido al otro vocabulario, fusionado, renombrado o descartado, con fecha, quién, motivo y registros afectados.
      Sirve para pasárselo al equipo de digitalización o al cliente. La importación ya aplica estos cambios sola cuando la planilla trae la grafía anterior.</p>
  </div>
  <div class="acciones">
    <button type="button" class="btn" id="copiar">Copiar como texto</button>
    <a class="btn" href="<?= App::url('vocab_informe', ['formato' => 'csv']) ?>">Descargar CSV</a>
  </div>
</div>
<textarea id="texto-informe" readonly hidden><?= e($texto) ?></textarea>
<p class="nota" id="copiado" role="status"></p>
<table>
  <thead><tr><th>#</th><th>Fecha</th><th>Quién</th><th>Cambio</th><th>Motivo</th><th class="num">Registros</th><th>Estado</th></tr></thead>
  <tbody>
  <?php foreach ($items as $x): ?>
    <tr class="<?= $x['deshecho'] ? 'baja' : '' ?>">
      <td class="num"><?= (int)$x['id'] ?></td>
      <td><?= fecha($x['fecha']) ?></td>
      <td><?= e($x['usuario']) ?></td>
      <td><b><?= e($x['accion_txt']) ?></b> · <?= e(ucfirst($x['descripcion'])) ?>
        <?php if ($x['solo_este'] && !$x['deshecho']): ?><br><span class="nota no-tachar">Quedaron sin <?= e(Vocabulario::NOMBRES[$x['tipo']] ?? '') ?>: <?= e(implode(', ', $x['solo_este'])) ?></span><?php endif ?>
      </td>
      <td><?= e($x['motivo'] ?? '—') ?></td>
      <td class="num"><?php if ($x['codigos']): ?><details><summary><?= (int)$x['registros'] ?></summary><span class="nota"><?= e(implode(', ', $x['codigos'])) ?></span></details><?php else: ?>0<?php endif ?></td>
      <td class="no-tachar">
        <?php if ($x['deshecho']): ?><span class="chip chip-stop">Deshecho <?= fecha($x['deshecho']['en']) ?> · <?= e($x['deshecho']['usuario']) ?></span>
        <?php else: ?><span class="chip chip-ok">Vigente</span>
          <?php if (App::puede('vocabularios')): ?> <a class="btn btn-chico btn-peligro-borde" href="<?= App::url('curaduria_deshacer', ['id' => $x['id']]) ?>">Deshacer…</a><?php endif ?>
        <?php endif ?>
      </td>
    </tr>
  <?php endforeach ?>
  <?php if (!$items): ?><tr><td colspan="7" class="nota">Todavía no hay cambios de curaduría. Se hacen desde Vocabularios: Mover, Fusionar, Renombrar o Descartar.</td></tr><?php endif ?>
  </tbody>
</table>
<p class="nota"><?= $vigentes ?> cambios vigentes de <?= count($items) ?>. El texto para copiar incluye solo los vigentes; el CSV, todos.</p>
<script>
document.getElementById('copiar').addEventListener('click', function () {
  var t = document.getElementById('texto-informe').value, s = document.getElementById('copiado');
  function listo() { s.textContent = 'Copiado. Pegalo en un mail o en un documento.'; }
  if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(t).then(listo, alternativa); } else { alternativa(); }
  function alternativa() {
    var a = document.getElementById('texto-informe'); a.hidden = false; a.select();
    try { document.execCommand('copy'); listo(); } catch (e) { s.textContent = 'Seleccioná el texto y copialo con Ctrl+C.'; }
    a.rows = 10; a.style.width = '100%';
  }
});
</script>
