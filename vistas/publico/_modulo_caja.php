<?php
/**
 * Módulo-caja (docs/diseno/sistema.md «Archivo: sistema» §A.10): el frente de una caja de archivo de cartón dibujado
 * en línea fina, con la tapa, el troquel de la manija y el rótulo pegado (número grande a máquina, obra, años). Al pie
 * del frente, el «contenido»: cantidad de registros y barra de digitalización. Es el anclaje físico del archivo.
 *
 * El dibujo (tapa, troquel, marco del rótulo) es decorativo y va en CSS/`aria-hidden`; el texto es real y el enlace
 * dice «Caja N: nombre». Toda la caja es un solo enlace (::after del título).
 *
 * Recibe:
 *  - $c       caja de Aida\Sitio::cajas() (id, numero, nombre, documentos, digitales, a_pedido, desde, hasta, avance)
 *  - $enl     Aida\Web\Enlaces
 *  - $variante 'grande' (Las cajas, por defecto) · 'chica' (accesos del inicio) · 'encabezado' (página de la caja: sin enlace, todo aria-hidden)
 *  - $nivelCaja nivel del título (3 por defecto)
 *  - $etiqueta  'li' por defecto ('div' fuera de una lista)
 */
use Aida\Web\Html as H;

$variante = $variante ?? 'grande';
$nivelCaja = $nivelCaja ?? 3;
$etiqueta = $etiqueta ?? 'li';
$anios = $c['desde'] ? $c['desde'] . ($c['hasta'] !== $c['desde'] ? '–' . $c['hasta'] : '') : 'Sin años';
$encabezado = $variante === 'encabezado';
?>
<<?= $etiqueta ?> class="mcaja mcaja--<?= H::e($variante) ?>"<?= $encabezado ? ' aria-hidden="true"' : '' ?>>
  <span class="mcaja__tapa" aria-hidden="true"></span>
  <div class="mcaja__frente">
    <span class="mcaja__troquel" aria-hidden="true"></span>
    <div class="mcaja__rotulo">
      <p class="mcaja__num" aria-hidden="true"><?= (int)$c['numero'] ?></p>
      <?php if (!$encabezado): ?>
      <h<?= $nivelCaja ?> class="mcaja__titulo"><a href="<?= H::e($enl->caja($c)) ?>"><span class="solo-lector">Caja <?= (int)$c['numero'] ?>: </span><?= H::e($c['nombre']) ?></a></h<?= $nivelCaja ?>>
      <?php endif ?>
      <p class="mcaja__anios"><?= H::e($anios) ?></p>
    </div>
    <?php if ($variante !== 'encabezado'): ?>
    <p class="mcaja__contenido"><?= H::numero($c['documentos']) ?> <?= $c['documentos'] === 1 ? 'registro' : 'registros' ?><?= $variante === 'grande' && $c['a_pedido'] ? ' · ' . H::numero($c['a_pedido']) . ' a pedido' : '' ?></p>
    <div class="avance mcaja__avance">
      <div class="avance__barra" role="img" aria-label="<?= (int)$c['avance'] ?> % digitalizado"><i style="width:<?= (int)$c['avance'] ?>%"></i></div>
      <span aria-hidden="true"><?= (int)$c['avance'] ?> %</span>
    </div>
    <?php endif ?>
  </div>
</<?= $etiqueta ?>>
