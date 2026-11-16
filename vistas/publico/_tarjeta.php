<?php
/**
 * Fila de registro (docs/diseno/sistema.md “Archivo: sistema”, componentes.md §5): una fila por documento, toda la fila
 * es un solo enlace. Columnas: código · documento (título y tipos) · caja · año · estado. En celular se apilan.
 * Si hay búsqueda ($e['q'] en Explorar), resalta los términos y muestra el fragmento del resumen donde aparecen.
 * Recibe $t (tarjeta de Aida\Sitio), $caja (id, numero, nombre) si la tarjeta no trae la suya, $enl y opcionalmente
 * $nivel (3) y $etiqueta ('li'; 'div' fuera de una lista).
 */
use Aida\Web\Html as H;

$nivel = $nivel ?? 3;
$etiqueta = $etiqueta ?? 'li';
$cj = $t['caja'] ?? $caja;
$qr = isset($e['q']) && is_string($e['q']) ? $e['q'] : '';
$frag = $qr !== '' ? H::fragmento($t['resumen'] ?? $t['extracto'], $qr) : null;
$tiposTexto = implode(' · ', array_map(fn($tp) => $tp['nombre'], array_slice($t['tipos'], 0, 3)));
?>
<<?= $etiqueta ?> class="registro" data-codigo="<?= H::e($t['codigo']) ?>">
  <span class="registro__codigo codigo"><?= H::resaltar($t['codigo'], $qr) ?></span>
  <div class="registro__cuerpo">
    <h<?= $nivel ?> class="registro__titulo"><a href="<?= H::e($enl->registro($t)) ?>"><?= H::nombre($t, $qr) ?></a></h<?= $nivel ?>>
    <?php if (!$t['sinTitulo'] && $tiposTexto !== ''): ?><p class="registro__tipos"><?= H::resaltar($tiposTexto, $qr) ?></p><?php endif ?>
    <?php if ($frag !== null): ?><p class="registro__fragmento"><?= H::resaltar($frag, $qr) ?></p><?php endif ?>
  </div>
  <p class="registro__caja">Caja <?= (int)$cj['numero'] ?><span class="solo-lector">:</span> <span class="registro__caja-nombre"><?= H::resaltar($cj['nombre'], $qr) ?></span></p>
  <p class="registro__anio"><?= H::anio($t['anio']) ?></p>
  <p class="registro__estado"><?= $t['pedido'] ? '<span class="etq etq--pedido">' . H::icono('candado') . '<span class="solo-lector">Consulta </span>A pedido</span>'
      : ($t['digital'] ? '<span class="etq etq--digital">' . H::icono('documento') . '<span class="solo-lector">Documento </span>Digital</span>'
                       : '<span class="etq etq--ficha">' . H::icono('ficha') . 'Solo ficha</span>') ?></p>
</<?= $etiqueta ?>>
