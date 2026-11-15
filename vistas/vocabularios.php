<?php use Aida\Web\App; $titulo = 'Vocabularios';
$puede = App::puede('vocabularios');
$activos = array_values(array_filter($terminos, fn($t) => !$t['fusionado_en']));
?>
<div class="cabecera">
  <div>
    <p class="eyebrow">Curaduría</p>
    <h1>Vocabularios</h1>
    <p class="nota">Lista cerrada de términos. Lo que llega del Excel y no existe entra como «propuesto» hasta que alguien lo apruebe, lo renombre o lo fusione con otro.</p>
  </div>
</div>
<nav class="tabs">
  <a href="<?= App::url('vocabularios', ['tipo' => 'tipo_documento']) ?>" class="<?= $tipo === 'tipo_documento' ? 'activo' : '' ?>">Tipos de documento</a>
  <a href="<?= App::url('vocabularios', ['tipo' => 'descriptor']) ?>" class="<?= $tipo === 'descriptor' ? 'activo' : '' ?>">Descriptores</a>
</nav>

<?php if ($puede): ?>
<form method="post" action="<?= App::url('vocab_accion') ?>" class="filtros">
  <?= App::campoToken() ?><input type="hidden" name="tipo" value="<?= e($tipo) ?>"><input type="hidden" name="accion" value="nuevo">
  <label>Nuevo término<input type="text" name="nombre" required></label>
  <button class="btn btn-crear">+ Agregar</button>
</form>
<?php $prop = count(array_filter($activos, fn($t) => $t['estado'] === 'propuesto')); if ($prop): ?>
<form method="post" action="<?= App::url('vocab_accion') ?>" class="acciones" >
  <?= App::campoToken() ?><input type="hidden" name="tipo" value="<?= e($tipo) ?>"><input type="hidden" name="accion" value="aprobar_todos">
  <span class="nota"><?= $prop ?> términos propuestos sin curar.</span>
  <button class="btn btn-chico btn-crear" data-doble="Se aprueban <?= $prop ?> términos tal como están. ¿Confirmar?">Aprobar todos los propuestos</button>
</form>
<?php endif ?>
<?php endif ?>

<table>
  <thead><tr><th>Término</th><th>Estado</th><th class="num">Registros</th><th><?= $puede ? 'Acciones' : '' ?></th></tr></thead>
  <tbody>
  <?php foreach ($terminos as $t): ?>
    <tr class="<?= $t['fusionado_en'] ? 'baja' : '' ?>">
      <td><?= e($t['nombre']) ?></td>
      <td class="no-tachar">
        <?php if ($t['fusionado_en']): ?><span class="chip">Fusionado en «<?= e($t['fusion_nombre']) ?>»</span>
        <?php elseif ($t['estado'] === 'propuesto'): ?><span class="chip chip-warn">Propuesto</span>
        <?php else: ?><span class="chip chip-ok">Aprobado</span><?php endif ?>
      </td>
      <td class="num"><?= (int)$t['usos'] ?></td>
      <td class="no-tachar">
        <?php if ($puede && !$t['fusionado_en']): ?>
          <div class="acciones" style="margin:0">
            <?php if ($t['estado'] === 'propuesto'): ?>
              <form method="post" action="<?= App::url('vocab_accion') ?>" class="inline"><?= App::campoToken() ?>
                <input type="hidden" name="tipo" value="<?= e($tipo) ?>"><input type="hidden" name="accion" value="aprobar"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <button class="btn btn-chico btn-crear">Aprobar</button></form>
            <?php endif ?>
            <form method="post" action="<?= App::url('vocab_accion') ?>" class="inline" onsubmit="var n=prompt('Nuevo nombre','<?= e(addslashes($t['nombre'])) ?>'); if(!n) return false; this.nombre.value=n;">
              <?= App::campoToken() ?><input type="hidden" name="tipo" value="<?= e($tipo) ?>"><input type="hidden" name="accion" value="renombrar"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="nombre">
              <button class="btn btn-chico">Renombrar</button></form>
            <form method="post" action="<?= App::url('vocab_accion') ?>" class="inline">
              <?= App::campoToken() ?><input type="hidden" name="tipo" value="<?= e($tipo) ?>"><input type="hidden" name="accion" value="fusionar"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <select name="destino" style="display:inline-block;width:auto;margin:0" required>
                <option value="">Fusionar en…</option>
                <?php foreach ($activos as $o): if ($o['id'] != $t['id']): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['nombre']) ?></option><?php endif; endforeach ?>
              </select>
              <button class="btn btn-chico btn-peligro-borde" data-doble="<?= (int)$t['usos'] ?> registros cambian de término. ¿Confirmar?">Fusionar</button></form>
          </div>
        <?php endif ?>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
