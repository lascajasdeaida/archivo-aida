<?php use Aida\Web\App; ?>
<section class="panel confirmar">
  <h1><?= e($titulo) ?></h1>
  <p class="lede"><?= e($texto) ?></p>
  <form method="post" action="<?= e($accion) ?>" class="acciones">
    <?= App::campoToken() ?>
    <?php if (!empty($peligro)): ?>
      <label class="check confirmo"><input type="checkbox" required onchange="this.form.querySelector('.btn-peligro').disabled = !this.checked"> Entiendo lo que va a pasar y quiero seguir</label>
      <button class="btn btn-peligro" disabled><?= e($boton) ?></button>
    <?php else: ?>
      <button class="btn btn-primario"><?= e($boton) ?></button>
    <?php endif ?>
    <a class="btn" href="<?= e($volver) ?>">Cancelar</a>
  </form>
</section>
