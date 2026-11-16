<?php
/**
 * Anterior / siguiente de un registro o una caja (barra de la ficha y de la caja, arriba y al pie).
 * Recibe $paso = ['etiqueta' => aria-label, 'ant' => ?['href','corto','largo'], 'sig' => ?[…]].
 * El nombre largo va visible desde 600 px y siempre para lectores de pantalla.
 */
use Aida\Web\Html as H;

$corto = fn(array $v) => preg_match('/^C\d+R\d+$/', $v['corto']) ? '<span class="codigo">' . H::e($v['corto']) . '</span>' : H::e($v['corto']);

if (!empty($paso['ant']) || !empty($paso['sig'])): ?>
<nav class="paso" aria-label="<?= H::e($paso['etiqueta']) ?>">
  <?php if ($v = $paso['ant']): ?><a class="paso__ant" rel="prev" href="<?= H::e($v['href']) ?>"><?= H::icono('izq') ?><span><span class="paso__rotulo">Anterior</span> <?= $corto($v) ?><span class="paso__largo"> · <?= H::e(mb_strimwidth($v['largo'], 0, 48, '…')) ?></span></span></a><?php else: ?><span class="paso__vacio"></span><?php endif ?>
  <?php if ($v = $paso['sig']): ?><a class="paso__sig" rel="next" href="<?= H::e($v['href']) ?>"><span><span class="paso__rotulo">Siguiente</span> <?= $corto($v) ?><span class="paso__largo"> · <?= H::e(mb_strimwidth($v['largo'], 0, 48, '…')) ?></span></span><?= H::icono('der') ?></a><?php endif ?>
</nav>
<?php endif;
