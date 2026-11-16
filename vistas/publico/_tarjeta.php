<?php
/**
 * Tarjeta de registro (docs/diseno/componentes.md §5): toda la tarjeta es un solo enlace.
 * Recibe $t (tarjeta de Aida\Sitio), $caja (id, numero, nombre) si la tarjeta no trae la suya, $enl y opcionalmente
 * $nivel (3) y $etiqueta ('li'; 'div' fuera de una lista).
 */
use Aida\Web\Html as H;

$nivel = $nivel ?? 3;
$etiqueta = $etiqueta ?? 'li';
$cj = $t['caja'] ?? $caja;
$mini = $t['pedido'] ? ['mini--pedido', 'candado'] : ($t['digital'] ? ['mini--hoja', null] : ['mini--vacia', 'ficha']);
?>
<<?= $etiqueta ?> class="registro" data-codigo="<?= H::e($t['codigo']) ?>">
  <div class="mini <?= $mini[0] ?>" aria-hidden="true"><?= $mini[1] ? H::icono($mini[1]) : '' ?></div>
  <div class="registro__cuerpo">
    <h<?= $nivel ?>><a href="<?= H::e($enl->registro($t)) ?>"><?= H::nombre($t) ?></a></h<?= $nivel ?>>
    <p class="registro__datos"><?= $t['sinTitulo'] ? 'Sin título · ' : '' ?><span class="codigo"><?= H::e($t['codigo']) ?></span>
      · Caja <?= (int)$cj['numero'] ?>: <?= H::e($cj['nombre']) ?> · <?= H::anio($t['anio']) ?></p>
    <?php if ($t['extracto'] !== ''): ?><p class="registro__extracto"><?= H::e($t['extracto']) ?></p><?php endif ?>
    <div class="etqs">
      <?= $t['digital'] ? '<span class="etq etq--digital">' . H::icono('documento') . 'Documento digital</span>'
                        : '<span class="etq etq--ficha">' . H::icono('ficha') . 'Solo ficha</span>' ?>
      <?php if ($t['pedido']): ?><span class="etq etq--pedido"><?= H::icono('candado') ?>Consulta a pedido</span><?php endif ?>
      <?php foreach (array_slice($t['tipos'], 0, 2) as $tp): ?><span class="etq etq--tipo"><?= H::e($tp['nombre']) ?></span><?php endforeach ?>
    </div>
  </div>
</<?= $etiqueta ?>>
