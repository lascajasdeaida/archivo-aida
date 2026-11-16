<?php use Aida\Web\App; $titulo = 'Nuevo registro'; ?>
<div class="migas"><a href="<?= App::url('cajas') ?>">Cajas</a> / <a href="<?= App::url('caja', ['id' => $caja['id']]) ?>">Caja <?= (int)$caja['numero'] ?></a> / Nuevo registro</div>
<section class="panel" style="max-width:640px">
  <h1>Nuevo registro en la Caja <?= (int)$caja['numero'] ?></h1>
  <p class="nota">Se carga como “cargado a mano”: la planilla no lo modifica. Después de crearlo completás el resto de la ficha.</p>
  <?php if ($error): ?><div class="aviso aviso-error"><?= e($error) ?></div><?php endif ?>
  <form method="post" action="<?= App::url('registro_nuevo', ['caja' => $caja['id']]) ?>">
    <?= App::campoToken() ?>
    <div class="dos">
      <label>Número de registro
        <input type="number" name="numero" min="1" value="<?= e($datos['numero'] ?? $sugerido) ?>" <?= App::puede('nomenclatura') ? '' : 'readonly' ?>>
      </label>
      <label>Código<input type="text" value="C<?= (int)$caja['numero'] ?>R…" readonly></label>
    </div>
    <p class="nota">Sugerido: <?= $sugerido ?> (el siguiente libre; no se reutilizan números de registros dados de baja).</p>
    <?php if (App::puede('nomenclatura')): ?>
      <label class="check"><input type="checkbox" name="confirmar_numero" value="1"> Confirmo usar un número distinto del sugerido</label>
    <?php endif ?>
    <label>Título<input type="text" name="titulo" value="<?= e($datos['titulo'] ?? '') ?>" required autofocus></label>
    <div class="acciones">
      <button class="btn btn-crear">Crear registro</button>
      <a class="btn" href="<?= App::url('caja', ['id' => $caja['id']]) ?>">Cancelar</a>
    </div>
  </form>
</section>
