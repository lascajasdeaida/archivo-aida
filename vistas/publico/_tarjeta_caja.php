<?php
/**
 * Tarjeta de caja (docs/diseno/componentes.md, «Tarjeta de caja»): rótulo de caja de archivo con barra de avance.
 * Recibe $c (Aida\Sitio::cajas), $enl y opcionalmente $nivel (3).
 */
use Aida\Web\Html as H;

$nivelCaja = $nivelCaja ?? 3;
$aniosCaja = $c['desde'] ? $c['desde'] . ($c['hasta'] !== $c['desde'] ? '–' . $c['hasta'] : '') : '';
?>
<li><article class="caja">
  <p class="caja__num">Caja <b><?= (int)$c['numero'] ?></b></p>
  <h<?= $nivelCaja ?>><a href="<?= H::e($enl->caja($c)) ?>"><?= H::e($c['nombre']) ?></a></h<?= $nivelCaja ?>>
  <p class="caja__datos"><?= H::numero($c['documentos']) ?> documentos<?= $aniosCaja ? ' · ' . $aniosCaja : '' ?><?= $c['a_pedido'] ? ' · ' . H::numero($c['a_pedido']) . ' a pedido' : '' ?></p>
  <div class="caja__pie"><div class="avance">
    <div class="avance__barra" role="img" aria-label="<?= (int)$c['avance'] ?> % digitalizado"><i style="width:<?= (int)$c['avance'] ?>%"></i></div>
    <span><?= $c['digitales'] ? H::numero($c['digitales']) . ' digitalizados' : 'Sin digitalizar todavía' ?></span></div></div>
</article></li>
