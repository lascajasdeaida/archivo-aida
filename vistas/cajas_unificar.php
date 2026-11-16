<?php use Aida\Web\App; $titulo = 'Unificar nombres de cajas';
$clases = [
    'grafia' => ['Misma obra, distinta grafía', 'Difieren en tildes, mayúsculas, comillas o una letra. Conviene unificar.', 'chip-acc'],
    'resumen' => ['El resumen las presenta juntas', 'Tienen nombres distintos, pero el resumen de una caja dice que van juntas. Revisá si es la misma obra con dos títulos.', 'chip-warn'],
    'parte' => ['Misma obra, distinta parte: no unificar', 'El numeral final (“II”, “91/92/93”) distingue cada caja. Si hay algo para corregir, se unifica solo el título y cada caja conserva su parte.', 'chip'],
    'aclaracion' => ['Versiones distintas: no unificar', 'Mismo título con distinta aclaración (“(España)”, “(Argentina)”). Si hay algo para corregir, se unifica solo el título y cada caja conserva su aclaración.', 'chip'],
];
?>
<div class="migas"><a href="<?= App::url('cajas') ?>">Cajas</a> / Unificar nombres</div>
<div class="cabecera">
  <div>
    <p class="eyebrow">Curaduría</p>
    <h1>Unificar nombres de cajas</h1>
    <p class="nota">Grupos de cajas con nombres parecidos, separados según lo que conviene hacer.
      Ninguna viene tildada: tildá solo las cajas que correspondan, revisá cómo tiene que quedar escrito y aplicá.
      Cada cambio queda en el historial de la caja (se puede deshacer) y la planilla ya no lo pisa.</p>
  </div>
</div>
<?php if (!$grupos): ?>
  <section class="panel"><p>✓ No hay nombres parecidos para unificar.</p></section>
<?php endif ?>
<?php foreach ($grupos as $i => $g): [$etq, $expl, $chip] = $clases[$g['clase']]; $conservar = in_array($g['clase'], ['parte', 'aclaracion'], true);
    $hayCambios = (bool)array_filter($g['cajas'], fn($c) => $c['propuesto'] !== $c['nombre']); ?>
  <form method="post" action="<?= App::url('cajas_unificar') ?>" class="panel unificar-grupo">
    <?= App::campoToken() ?>
    <input type="hidden" name="modo" value="<?= $conservar ? 'conservar' : 'unico' ?>">
    <p><span class="chip <?= $chip ?>"><?= e($etq) ?></span> <span class="nota"><?= e($expl) ?></span></p>
    <?php if ($g['evidencia']): ?><p class="evidencia">Evidencia: <?= e(implode(' · ', $g['evidencia'])) ?>.</p><?php endif ?>
    <table>
      <thead><tr><th style="width:40px"></th><th style="width:80px">Caja</th><th>Nombre actual</th><?php if ($conservar): ?><th>Quedaría</th><?php endif ?></tr></thead>
      <?php foreach ($g['cajas'] as $c): ?>
        <tr>
          <td><input type="checkbox" name="cajas[]" value="<?= (int)$c['id'] ?>" aria-label="Incluir caja <?= (int)$c['numero'] ?>"></td>
          <td><a href="<?= App::url('caja', ['id' => $c['id']]) ?>"><?= (int)$c['numero'] ?></a></td>
          <td><?= e($c['nombre']) ?>
            <?php if (!$conservar): ?><button type="button" class="link" style="margin-left:8px;font-size:.8rem"
              onclick="this.form.nombre.value=<?= e(json_encode(trim(preg_replace(['/[\"“”«»]/u', '/\s+/u'], ['', ' '], $c['nombre'])), JSON_UNESCAPED_UNICODE)) ?>">usar este</button><?php endif ?></td>
          <?php if ($conservar): ?><td class="<?= $c['propuesto'] === $c['nombre'] ? 'nota' : '' ?>"><?= $c['propuesto'] === $c['nombre'] ? 'sin cambios' : e($c['propuesto']) ?></td><?php endif ?>
        </tr>
      <?php endforeach ?>
    </table>
    <?php if ($conservar): ?>
      <?php if ($hayCambios): ?>
      <details>
        <summary class="btn btn-chico">Unificar solo el título, conservando <?= $g['clase'] === 'parte' ? 'la parte' : 'la aclaración' ?> de cada caja…</summary>
        <div class="acciones">
          <label style="flex:1;margin:0">Título
            <input type="text" name="nombre" value="<?= e($g['titulo']) ?>" required>
          </label>
          <button class="btn btn-primario" style="align-self:flex-end" data-doble="Las cajas tildadas pasan a tener este título y cada una conserva <?= $g['clase'] === 'parte' ? 'su numeral' : 'su aclaración' ?> (se puede deshacer).">Aplicar a las cajas tildadas</button>
        </div>
      </details>
      <?php else: ?><p class="nota">✓ Ya están bien escritas: no hay nada que hacer.</p><?php endif ?>
    <?php else: ?>
      <div class="acciones">
        <label style="flex:1;margin:0">Nombre unificado
          <input type="text" name="nombre" value="<?= e($g['sugerido']) ?>" required <?= $g['sugerido'] === '' ? 'placeholder="Escribí cómo tiene que quedar (no hay una sugerencia segura)"' : '' ?>>
        </label>
        <button class="btn btn-primario" style="align-self:flex-end" data-doble="Las cajas tildadas pasan a llamarse igual (se puede deshacer).">Unificar las cajas tildadas</button>
      </div>
    <?php endif ?>
  </form>
<?php endforeach ?>
