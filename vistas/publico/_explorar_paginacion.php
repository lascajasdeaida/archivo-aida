<?php
/**
 * «Mostrar 20 más» (docs/ux/sitio-publico.md §4.2): enlace real a ?pagina=N+1 (sin JS, la página siguiente; con JS se
 * agregan a la lista). $e['primera'] es la primera página que se está viendo: con JS se van sumando.
 * Recibe $r, $e, $enl.
 */
use Aida\Web\Html as H;
use Aida\Web\SitioPublico as SP;

$pp = SP::POR_PAGINA;
$desde = ($e['primera'] - 1) * $pp + 1;
$hasta = min($r['total'], $r['pagina'] * $pp);
$quedan = $r['total'] - $hasta;
$texto = $desde > 1 ? 'Mostrando ' . H::numero($desde) . '–' . H::numero($hasta) . ' de ' . H::numero($r['total'])
                    : 'Mostrando ' . H::numero($hasta) . ' de ' . H::numero($r['total']);
?>
<div class="paginacion" id="paginacion" data-primera="<?= (int)$e['primera'] ?>">
  <p class="meta" id="mostrando"><?= $texto ?></p>
  <progress max="<?= (int)$r['total'] ?>" value="<?= (int)$hasta ?>" aria-labelledby="mostrando"></progress>
  <?php if ($quedan > 0): ?>
    <a class="boton boton--secundario" id="mas" rel="next" data-pagina="<?= $r['pagina'] + 1 ?>"
       href="<?= H::e($enl->explorar(SP::parametros(['pagina' => $r['pagina'] + 1] + $e))) ?>">Mostrar <?= min($pp, $quedan) ?> más</a>
  <?php else: ?>
    <p class="meta">Llegaste al final de la lista.</p>
  <?php endif ?>
  <?php if ($e['primera'] > 1): ?>
    <a rel="prev" href="<?= H::e($enl->explorar(SP::parametros(['pagina' => $e['primera'] - 1] + $e))) ?>">Ver los resultados anteriores</a>
  <?php endif ?>
</div>
