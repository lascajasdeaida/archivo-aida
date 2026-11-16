<?php
/** Filtros activos de Explorar: cada chip es un enlace que lo quita. Recibe $chips, $e, $enl. */
use Aida\Web\Html as H;

if ($chips): ?>
<ul class="chips" aria-label="Filtros activos">
  <?php foreach ($chips as $ch): ?>
  <li><a class="chip" href="<?= H::e($ch['quitar']) ?>" data-quitar><?= H::e($ch['titulo']) ?>: <b><?= H::e($ch['valor']) ?></b><span class="chip__x"><?= H::icono('cerrar') ?></span><span class="solo-lector">, quitar filtro</span></a></li>
  <?php endforeach ?>
  <?php if (count($chips) > 1): ?><li><a class="boton boton--fantasma boton--chico" href="<?= H::e($enl->explorar()) ?>" data-limpiar-todo>Limpiar todo</a></li><?php endif ?>
</ul>
<?php endif;
