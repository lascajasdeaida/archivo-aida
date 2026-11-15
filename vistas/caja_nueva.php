<?php use Aida\Web\App; $titulo = 'Nueva caja'; ?>
<div class="migas"><a href="<?= App::url('cajas') ?>">Cajas</a> / Nueva caja</div>
<section class="panel" style="max-width:640px">
  <h1>Nueva caja</h1>
  <p class="nota">El número es el número físico de la caja en el depósito.</p>
  <?php if ($error): ?><div class="aviso aviso-error"><?= e($error) ?></div><?php endif ?>
  <form method="post" action="<?= App::url('caja_nueva') ?>">
    <?= App::campoToken() ?>
    <label>Número<input type="number" name="numero" min="1" value="<?= e($datos['numero'] ?? $sugerido) ?>" required></label>
    <label>Nombre<input type="text" name="nombre" value="<?= e($datos['nombre'] ?? '') ?>" required></label>
    <label>Resumen<textarea name="resumen" rows="5"><?= e($datos['resumen'] ?? '') ?></textarea></label>
    <div class="acciones">
      <button class="btn btn-primario">Crear caja</button>
      <a class="btn" href="<?= App::url('cajas') ?>">Cancelar</a>
    </div>
  </form>
</section>
