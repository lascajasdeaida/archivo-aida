<?php use Aida\Web\App; use Aida\Vocabulario; $titulo = 'Vocabularios';
$puede = App::puede('vocabularios');
$estadoDe = fn($t) => $t['fusionado_en'] ? ($t['regla'] === 'movido' ? 'movido' : 'fusionado') : $t['estado'];
$otro = Vocabulario::PLURALES[Vocabulario::otro($tipo)];
?>
<div class="cabecera">
  <div>
    <p class="eyebrow">Curaduría</p>
    <h1>Vocabularios</h1>
    <p class="nota">Lista cerrada de términos. Lo que llega del Excel y no existe entra como “propuesto” hasta que alguien lo apruebe, lo renombre, lo fusione, lo mueva al otro vocabulario o lo descarte.
      Cada cambio queda en el historial y la importación lo respeta: si la planilla vuelve a traer la grafía vieja, carga el término vigente y lo informa.</p>
  </div>
  <div class="acciones"><a class="btn" href="<?= App::url('vocab_informe') ?>">Cambios de curaduría<?= $cambiosCuraduria ? " ($cambiosCuraduria)" : '' ?></a></div>
</div>
<nav class="tabs">
  <a href="<?= App::url('vocabularios', ['tipo' => 'tipo_documento']) ?>" class="<?= $tipo === 'tipo_documento' ? 'activo' : '' ?>">Tipos de documento</a>
  <a href="<?= App::url('vocabularios', ['tipo' => 'descriptor']) ?>" class="<?= $tipo === 'descriptor' ? 'activo' : '' ?>">Descriptores</a>
</nav>

<?php if ($puede): ?>
<form method="post" action="<?= App::url('vocab_accion') ?>" class="filtros">
  <?= App::campoToken() ?><input type="hidden" name="tipo" value="<?= e($tipo) ?>"><input type="hidden" name="accion" value="nuevo">
  <label>Nuevo término<input type="text" name="nombre" required maxlength="120"></label>
  <button class="btn btn-crear">+ Agregar</button>
</form>
<?php $prop = count(array_filter($terminos, fn($t) => $estadoDe($t) === 'propuesto')); if ($prop): ?>
<form method="post" action="<?= App::url('vocab_accion') ?>" class="acciones" >
  <?= App::campoToken() ?><input type="hidden" name="tipo" value="<?= e($tipo) ?>"><input type="hidden" name="accion" value="aprobar_todos">
  <span class="nota"><?= $prop ?> términos propuestos sin curar.</span>
  <button class="btn btn-chico btn-crear" data-doble="Se aprueban <?= $prop ?> términos tal como están. ¿Confirmar?">Aprobar todos los propuestos</button>
</form>
<?php endif ?>
<?php endif ?>

<div class="filtros" id="filtro-vocab">
  <label>Buscar<input type="search" placeholder="término…" oninput="filtrarVocab()"></label>
  <label>Estado<select onchange="filtrarVocab()"><option value="">Vigentes y propuestos</option><option value="propuesto">Propuestos</option><option value="aprobado">Aprobados</option><option value="fusionado">Fusionados</option><option value="movido">Movidos a <?= e($otro) ?></option><option value="descartado">Descartados</option><option value="*">Todos</option></select></label>
</div>
<script>
function filtrarVocab() {
  var f = document.getElementById('filtro-vocab'), q = f.querySelector('input').value.toLowerCase(), e = f.querySelector('select').value;
  document.querySelectorAll('tr[data-estado]').forEach(function (tr) {
    var es = tr.dataset.estado, vigente = es === 'propuesto' || es === 'aprobado';
    tr.hidden = (q && tr.dataset.nombre.indexOf(q) < 0) || (e === '' ? !vigente : (e !== '*' && es !== e));
  });
}
document.addEventListener('DOMContentLoaded', filtrarVocab);
</script>
<table>
  <thead><tr><th>Término</th><th>Estado</th><th class="num">Registros</th><th><?= $puede ? 'Acciones' : '' ?></th></tr></thead>
  <tbody>
  <?php foreach ($terminos as $t): $est = $estadoDe($t); $vigente = in_array($est, ['propuesto', 'aprobado'], true); ?>
    <tr class="<?= $vigente ? '' : 'baja' ?>" data-nombre="<?= e(mb_strtolower($t['nombre'] . ' ' . $t['alias'])) ?>" data-estado="<?= e($est) ?>">
      <td><?= e($t['nombre']) ?>
        <?php if ($t['alias']): ?><br><span class="nota">También reconoce la grafía anterior “<?= e($t['alias']) ?>”</span><?php endif ?>
        <?php if (!$vigente && $t['motivo']): ?><br><span class="nota no-tachar">Motivo: <?= e($t['motivo']) ?></span><?php endif ?>
      </td>
      <td class="no-tachar">
        <?php if ($est === 'movido'): ?><span class="chip chip-acc">Movido a <?= e(Vocabulario::NOMBRES[$t['fusion_tipo']]) ?>: “<?= e($t['fusion_nombre']) ?>”</span>
        <?php elseif ($est === 'fusionado'): ?><span class="chip">Fusionado en “<?= e($t['fusion_nombre']) ?>”</span>
        <?php elseif ($est === 'descartado'): ?><span class="chip chip-stop">Descartado</span>
        <?php elseif ($est === 'propuesto'): ?><span class="chip chip-warn">Propuesto</span>
        <?php else: ?><span class="chip chip-ok">Aprobado</span><?php endif ?>
      </td>
      <td class="num"><?= (int)$t['usos'] ?></td>
      <td class="no-tachar">
        <?php if ($puede && $vigente): ?>
          <div class="acciones" style="margin:0">
            <?php if ($t['estado'] === 'propuesto'): ?>
              <form method="post" action="<?= App::url('vocab_accion') ?>" class="inline"><?= App::campoToken() ?>
                <input type="hidden" name="tipo" value="<?= e($tipo) ?>"><input type="hidden" name="accion" value="aprobar"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <button class="btn btn-chico btn-crear">Aprobar</button></form>
            <?php endif ?>
            <details class="inline renombrar"><summary class="btn btn-chico">Renombrar</summary>
              <form method="post" action="<?= App::url('vocab_accion') ?>" class="acciones" style="margin:6px 0">
                <?= App::campoToken() ?><input type="hidden" name="tipo" value="<?= e($tipo) ?>"><input type="hidden" name="accion" value="renombrar"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <input type="text" name="nombre" value="<?= e($t['nombre']) ?>" required maxlength="120" aria-label="Nuevo nombre">
                <input type="text" name="motivo" placeholder="Motivo (opcional)" maxlength="500" aria-label="Motivo del renombre">
                <button class="btn btn-chico btn-primario">Guardar</button>
              </form></details>
            <a class="btn btn-chico" href="<?= App::url('vocab_curar', ['op' => 'fusionar', 'id' => $t['id']]) ?>">Fusionar…</a>
            <a class="btn btn-chico" href="<?= App::url('vocab_curar', ['op' => 'mover', 'id' => $t['id']]) ?>">Mover a <?= e($otro) ?>…</a>
            <a class="btn btn-chico btn-peligro-borde" href="<?= App::url('vocab_curar', ['op' => 'descartar', 'id' => $t['id']]) ?>">Descartar…</a>
          </div>
        <?php elseif ($puede && $est === 'descartado'): ?>
          <form method="post" action="<?= App::url('vocab_accion') ?>" class="inline"><?= App::campoToken() ?>
            <input type="hidden" name="tipo" value="<?= e($tipo) ?>"><input type="hidden" name="accion" value="restaurar"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <button class="btn btn-chico btn-crear" data-doble="Vuelve a la lista y a los registros que lo tenían. ¿Confirmar?">Restaurar</button></form>
        <?php elseif ($puede): ?>
          <a class="link" href="<?= App::url('vocab_informe') ?>">Deshacer desde Cambios de curaduría</a>
        <?php endif ?>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
