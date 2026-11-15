<?php use Aida\Web\App; use Aida\Vocabulario;
$nombreT = Vocabulario::NOMBRES[$t['tipo']];
$plurT = Vocabulario::PLURALES[$t['tipo']];
$plurD = Vocabulario::PLURALES[$tipoDest];
$n = count($imp['registros']);
$regs = $n === 1 ? '1 registro' : "$n registros";
$titulo = match ($op) {
    'mover' => "Mover «{$t['nombre']}» a $plurD",
    'fusionar' => "Fusionar «{$t['nombre']}»",
    default => "Descartar «{$t['nombre']}»",
};
$codigos = array_column($imp['registros'], 'codigo');
$listaCodigos = fn(array $c, int $max = 40) => implode(', ', array_slice($c, 0, $max)) . (count($c) > $max ? ' … (+' . (count($c) - $max) . ')' : '');
$equiv = array_values(array_filter($destinos, fn($d) => $d['equivalente']));
$preferido = $equiv[0]['id'] ?? null;
?>
<div class="migas"><a href="<?= App::url('vocabularios', ['tipo' => $t['tipo']]) ?>">Vocabularios</a> / <?= e($titulo) ?></div>
<section class="panel confirmar">
  <p class="eyebrow"><?= e(ucfirst($nombreT)) ?> · <?= $t['estado'] === 'aprobado' ? 'aprobado' : 'propuesto' ?></p>
  <h1><?= e($titulo) ?></h1>

  <?php if ($op === 'descartar'): ?>
    <p class="lede">«<?= e($t['nombre']) ?>» deja de mostrarse y de ofrecerse para asignar. Se quita de <b><?= $regs ?></b><?= $n ? ' (' . e($listaCodigos($codigos)) . ')' : '' ?>.
      Los demás tipos y descriptores de esos registros no se tocan ni se bloquean: la planilla los sigue actualizando.</p>
    <p>Si la planilla lo vuelve a traer, la importación lo ignora y lo informa en Notificaciones → Informativas. Se puede restaurar.</p>
  <?php else: ?>
    <p class="lede" id="resumen-cambio">
      <?php if ($op === 'mover'): ?>
        <b><?= $regs ?></b> pierden el <?= e($nombreT) ?> «<?= e($t['nombre']) ?>» y suman el <?= e(Vocabulario::NOMBRES[$tipoDest]) ?> elegido.
      <?php else: ?>
        <b><?= $regs ?></b> pasan de «<?= e($t['nombre']) ?>» al término elegido.
      <?php endif ?>
      <span id="comparten"></span>
    </p>
    <?php if ($op === 'mover' && $imp['solo_este']): ?>
      <div class="leyenda leyenda-warn"><div><b><?= count($imp['solo_este']) === 1 ? '1 registro queda' : count($imp['solo_este']) . ' registros quedan' ?> sin <?= e($nombreT) ?>:</b>
        <?= e($listaCodigos($imp['solo_este'])) ?>. Conviene asignarles uno desde la ficha.</div></div>
    <?php endif ?>
    <p>La importación lo respeta: si la planilla trae «<?= e($t['nombre']) ?>» en la columna de <?= e($plurT) ?>, carga el término elegido sin crear uno nuevo, y lo informa.
      Se puede deshacer desde <a href="<?= App::url('vocab_informe') ?>">Cambios de curaduría</a>.</p>
  <?php endif ?>

  <form method="post" action="<?= App::url('vocab_curar', ['op' => $op, 'id' => $t['id']]) ?>">
    <?= App::campoToken() ?>
    <?php if ($op !== 'descartar'): ?>
      <fieldset class="destinos">
        <legend><?= $op === 'mover' ? 'Destino en ' . e($plurD) : 'Fusionar en' ?></legend>
        <?php foreach ($equiv as $d): ?>
          <label class="check"><input type="radio" name="destino" value="<?= (int)$d['id'] ?>" data-comparten="<?= (int)$d['comparten'] ?>" <?= $d['id'] === $preferido ? 'checked' : '' ?> required>
            <?= $op === 'mover' ? 'Fusionar en el ' . e(Vocabulario::NOMBRES[$tipoDest]) . ' existente' : 'En el término equivalente' ?> «<?= e($d['nombre']) ?>»
            <span class="nota">(<?= (int)$d['usos'] ?> registros<?= $d['estado'] === 'propuesto' ? ', propuesto' : '' ?>) · recomendado</span></label>
        <?php endforeach ?>
        <?php if ($op === 'mover' && !$otroIgual): ?>
          <label class="check"><input type="radio" name="destino" value="nuevo" data-comparten="0" <?= $preferido ? '' : 'checked' ?> required>
            Crear «<?= e($t['nombre']) ?>» como <?= e(Vocabulario::NOMBRES[$tipoDest]) ?> nuevo (aprobado)</label>
        <?php endif ?>
        <label class="check"><input type="radio" name="destino" value="" id="destino-otro" data-comparten="" required> Otro término:</label>
        <select id="destino-lista" aria-label="Elegir otro término" style="max-width:100%">
          <option value="">Elegí…</option>
          <?php foreach ($destinos as $d): ?><option value="<?= (int)$d['id'] ?>" data-comparten="<?= (int)$d['comparten'] ?>"><?= e($d['nombre']) ?> (<?= (int)$d['usos'] ?>)</option><?php endforeach ?>
        </select>
      </fieldset>
    <?php endif ?>
    <label>Motivo<?= $op === 'descartar' ? ' (obligatorio: queda en el informe que se le pasa al equipo)' : ' (opcional, queda en el informe)' ?>
      <textarea name="motivo" rows="2" maxlength="500" <?= $op === 'descartar' ? 'required' : '' ?>
        placeholder="<?= $op === 'descartar' ? 'Ej.: letra suelta en C6R10, sin intención que se pueda deducir' : ($op === 'mover' ? 'Ej.: es un medio, no un tipo de documento' : 'Ej.: misma palabra con punto final') ?>"></textarea></label>
    <div class="acciones">
      <label class="check confirmo"><input type="checkbox" required onchange="this.form.querySelector('.btn-peligro').disabled = !this.checked"> Entiendo lo que va a pasar y quiero seguir</label>
      <button class="btn btn-peligro" disabled data-doble="<?= $op === 'descartar' ? "Se descarta «" . e($t['nombre']) . "» y se quita de $regs." : "Cambian $regs." ?> ¿Confirmar?"><?= $op === 'mover' ? 'Mover a ' . e($plurD) : ($op === 'fusionar' ? 'Fusionar' : 'Descartar') ?></button>
      <a class="btn" href="<?= App::url('vocabularios', ['tipo' => $t['tipo']]) ?>">Cancelar</a>
    </div>
  </form>
</section>
<?php if ($op !== 'descartar'): ?>
<script>
(function () {
  var f = document.querySelector('.destinos'), otro = document.getElementById('destino-otro'), lista = document.getElementById('destino-lista'),
      out = document.getElementById('comparten'), n = <?= $n ?>;
  function actualizar() {
    var r = f.querySelector('input[name=destino]:checked'), c = '';
    if (r === otro) { var o = lista.options[lista.selectedIndex]; otro.value = lista.value; c = o && o.value ? o.dataset.comparten : ''; }
    else if (r) { c = r.dataset.comparten; }
    out.textContent = c === '' || c === undefined ? '' : (+c === 0 ? 'Ninguno lo tenía ya.' : (+c === n ? 'Todos ya lo tenían: solo se quita el anterior.' : c + ' ya lo tenían.'));
  }
  f.addEventListener('change', actualizar);
  lista.addEventListener('change', function () { otro.checked = true; actualizar(); });
  actualizar();
})();
</script>
<?php endif ?>
