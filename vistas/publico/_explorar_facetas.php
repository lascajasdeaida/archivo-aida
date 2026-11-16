<?php
/**
 * Facetas de Explorar (docs/ux/sitio-publico.md §4.1, componentes de la maqueta explorar.html).
 * Dentro de una faceta, O; entre facetas, Y. Conteos sin el filtro de la propia faceta; las opciones en 0 no se muestran
 * salvo las elegidas. Más de 8 opciones: las 8 primeras (más las elegidas) y «Ver N más» (details: anda sin JS).
 * Recibe $facetas, $opciones, $e, $enl.
 */
use Aida\Web\Html as H;

$opcion = function (string $k, int $i, array $o): string {
    $id = "f-$k-$i";
    return '<label class="opcion" for="' . $id . '"><input type="checkbox" id="' . $id . '" name="' . $k . '[]" value="' . H::e($o['v']) . '"'
        . ($o['elegida'] ? ' checked' : '') . '><span>' . H::e($o['nombre']) . '</span><span class="n">' . H::numero($o['n'])
        . '<span class="solo-lector"> resultados</span></span></label>';
};
foreach ($facetas as $k => $def):
    $ops = $opciones[$k];
    $n = count(array_filter($ops, fn($o) => $o['elegida'])) + ($k === 'decada' && ($e['desde'] || $e['hasta']) ? 1 : 0);
    $abierta = $def['abierta'] || $n > 0;
    // Las 8 con más resultados (el orden ya viene dado) y las elegidas siempre a la vista
    $visibles = [];
    $resto = [];
    foreach ($ops as $i => $o) {
        if (count($ops) <= 10 || $i < 8 || $o['elegida']) {
            $visibles[$i] = $o;
        } else {
            $resto[$i] = $o;
        }
    }
    $maxN = max(1, ...array_map(fn($o) => $o['n'], $ops ?: [['n' => 1]]));
?>
<details class="faceta" data-clave="<?= $k ?>" id="faceta-<?= $k ?>"<?= $abierta ? ' open' : '' ?>>
  <summary><span><?= H::e($def['titulo']) ?></span><?php if ($n): ?><span class="faceta__n"><?= $n ?><span class="solo-lector"> elegido<?= $n > 1 ? 's' : '' ?></span></span><?php endif ?><?= H::icono('abajo') ?></summary>
  <fieldset><legend><?= H::e($def['titulo']) ?></legend>
    <?php if ($k === 'decada'): ?>
    <div class="histo" aria-hidden="true"><?php foreach ($ops as $o): if ($o['v'] === 'sin-fecha') continue; ?><span class="<?= $o['elegida'] ? 'elegida' : '' ?>" style="height:<?= round($o['n'] / $maxN * 100) ?>%"></span><?php endforeach ?></div>
    <?php endif ?>
    <?php if (!empty($def['buscar']) && count($ops) > 8): ?>
    <label class="solo-lector solo-con-js" for="buscar-<?= $k ?>">Buscar en <?= H::e($def['titulo']) ?></label>
    <input class="buscar-en-faceta solo-con-js" id="buscar-<?= $k ?>" data-clave="<?= $k ?>" type="search" placeholder="Buscar <?= H::e(mb_strtolower(preg_replace('/ \(.*\)/', '', $def['titulo']))) ?>…" autocomplete="off">
    <?php endif ?>
    <?php if (!$ops): ?><p class="meta">No hay opciones con estos filtros.</p><?php endif ?>
    <?php foreach ($visibles as $i => $o) { echo $opcion($k, $i, $o); } ?>
    <?php if ($resto): ?>
    <details class="faceta-mas"><summary>Ver <?= count($resto) ?> más</summary>
      <?php foreach ($resto as $i => $o) { echo $opcion($k, $i, $o); } ?>
    </details>
    <?php endif ?>
    <p class="meta faceta-sin-coincidencias" hidden>Ninguna opción coincide.</p>
    <?php if ($k === 'decada'): ?>
    <div class="rango"><label>Desde <input type="number" inputmode="numeric" name="desde" id="desde" min="1900" max="2100" value="<?= $e['desde'] ?: '' ?>"></label>
      <label>Hasta <input type="number" inputmode="numeric" name="hasta" id="hasta" min="1900" max="2100" value="<?= $e['hasta'] ?: '' ?>"></label>
      <button class="boton boton--secundario" id="aplicar-rango">Aplicar</button></div>
    <p class="meta" style="margin-top:8px">Los documentos «Sin fecha» quedan fuera al elegir un rango de años.</p>
    <?php endif ?>
  </fieldset>
</details>
<?php endforeach;
