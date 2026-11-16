<?php
use Aida\Consultas;
use Aida\Web\App;

$numero = Consultas::numero($c);
$titulo = "Consulta $numero";
$volver = App::url('consulta', ['id' => $c['id']]);
?>
<div class="migas"><a href="<?= App::url('consultas') ?>">Consultas</a> / <?= e($numero) ?></div>
<div class="cabecera">
  <div>
    <p class="eyebrow">Consulta a pedido · <?= e(Consultas::TIPOS[$c['tipo']] ?? $c['tipo']) ?></p>
    <h1><?= e($numero) ?> · <?= e($c['codigo']) ?></h1>
  </div>
  <div class="acciones">
    <?php if ($c['registro_id']): ?><a class="btn" href="<?= App::url('registro', ['id' => $c['registro_id']]) ?>">Ver el registro <?= e($c['codigo']) ?></a><?php endif ?>
  </div>
</div>

<div class="grilla">
  <section>
    <section class="panel">
      <h2>Pedido</h2>
      <table>
        <tr><td style="width:30%">Recibida</td><td><?= fecha($c['creado_en']) ?></td></tr>
        <tr><td>Documento</td><td><b><?= e($c['codigo']) ?></b><?= $c['titulo'] ? ' · ' . e($c['titulo']) : '' ?><?= $c['caja_numero'] ? '<br><span class="nota">Caja ' . (int)$c['caja_numero'] . ': ' . e($c['caja_nombre']) . '</span>' : '' ?>
          <?php if ($c['acceso'] && $c['acceso'] !== 'publico'): ?><br><span class="chip chip-warn"><?= e(acceso_txt($c['acceso'])) ?></span><?php endif ?></td></tr>
        <tr><td>Nombre</td><td><?= e($c['nombre']) ?></td></tr>
        <tr><td>Correo</td><td><a href="mailto:<?= e($c['correo']) ?>?subject=<?= rawurlencode("Consulta $numero · {$c['codigo']} · Las cajas de Aída") ?>"><?= e($c['correo']) ?></a></td></tr>
        <?php if ($c['institucion']): ?><tr><td>Institución</td><td><?= e($c['institucion']) ?></td></tr><?php endif ?>
        <tr><td>Uso previsto</td><td><?= e(Consultas::USOS[$c['uso']] ?? $c['uso']) ?></td></tr>
        <tr><td>Modalidad</td><td><?= e(Consultas::MODALIDADES[$c['modalidad']] ?? $c['modalidad']) ?></td></tr>
      </table>
      <h3>Mensaje</h3>
      <p style="white-space:pre-wrap"><?= e($c['motivo']) ?></p>
    </section>
  </section>
  <aside>
    <section class="panel">
      <h2>Seguimiento</h2>
      <form method="post" action="<?= App::url('consulta_guardar', ['id' => $c['id']]) ?>">
        <?= App::campoToken() ?>
        <fieldset style="border:0;padding:0;margin:0 0 12px"><legend class="nota">Estado</legend>
          <?php foreach (Consultas::ESTADOS as $k => $n): ?>
          <label class="check"><input type="radio" name="estado" value="<?= $k ?>"<?= $c['estado'] === $k ? ' checked' : '' ?>> <?= e($n) ?></label>
          <?php endforeach ?>
        </fieldset>
        <label>Notas internas<textarea name="notas" rows="6" placeholder="Qué se respondió, quién, próximos pasos… (no se le muestra a quien pidió)"><?= e($c['notas']) ?></textarea></label>
        <?php if ($c['atendida_en']): ?><p class="nota">Atendida por <?= e($c['atendida_por_nombre'] ?: $c['atendida_por_email']) ?> el <?= fecha($c['atendida_en']) ?>.</p><?php endif ?>
        <div class="acciones"><button class="btn btn-primario">Guardar</button></div>
      </form>
    </section>
    <section class="panel">
      <h2>Historial</h2>
      <?php require __DIR__ . '/_historial.php'; ?>
    </section>
  </aside>
</div>
