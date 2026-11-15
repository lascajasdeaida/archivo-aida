<?php use Aida\Web\App; ?>
<section class="panel confirmar">
  <h1><?= e($titulo) ?></h1>
  <p class="lede"><?= e($texto) ?></p>
  <form method="post" action="<?= e($accion) ?>" class="acciones">
    <?= App::campoToken() ?>
    <button class="btn <?= !empty($peligro) ? 'btn-peligro' : 'btn-primario' ?>"><?= e($boton) ?></button>
    <a class="btn" href="<?= e($volver) ?>">Cancelar</a>
  </form>
</section>
