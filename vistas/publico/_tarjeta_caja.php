<?php
/**
 * Fila de caja (docs/diseno/componentes.md «Tarjeta de caja», sistema.md «Archivo: sistema»): número · nombre ·
 * documentos · años · avance de la digitalización. Toda la fila es un enlace. Recibe $c (Aida\Sitio::cajas), $enl
 * y opcionalmente $nivelCaja (3).
 */
use Aida\Web\Html as H;

$nivelCaja = $nivelCaja ?? 3;
$aniosCaja = $c['desde'] ? $c['desde'] . ($c['hasta'] !== $c['desde'] ? '–' . $c['hasta'] : '') : 'Sin años';
?>
<li><article class="caja">
  <p class="caja__num">Caja <b><?= (int)$c['numero'] ?></b></p>
  <h<?= $nivelCaja ?> class="caja__nombre"><a href="<?= H::e($enl->caja($c)) ?>"><?= H::e($c['nombre']) ?></a></h<?= $nivelCaja ?>>
  <p class="caja__datos"><?= H::numero($c['documentos']) ?> documentos<?= $c['a_pedido'] ? ' · ' . H::numero($c['a_pedido']) . ' a pedido' : '' ?></p>
  <p class="caja__anios"><?= H::e($aniosCaja) ?></p>
  <div class="avance">
    <div class="avance__barra" role="img" aria-label="<?= (int)$c['avance'] ?> % digitalizado"><i style="width:<?= (int)$c['avance'] ?>%"></i></div>
    <span><?= $c['digitales'] ? H::numero($c['digitales']) . ' digitalizados' : 'Sin digitalizar' ?></span></div>
</article></li>
