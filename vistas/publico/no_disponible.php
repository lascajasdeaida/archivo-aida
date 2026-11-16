<?php
/** 410: el documento existió en el sitio pero ya no está publicado (o su caja no). Recibe $codigo, $caja (o null), $enl. */
use Aida\Web\Html as H;

$tituloPagina = 'Documento no disponible';
ob_start(); ?>
<div class="contenedor contenedor--texto">
  <p class="kicker">Código <span class="codigo"><?= H::e($codigo) ?></span></p>
  <h1>Este documento ya no está disponible</h1>
  <p>El documento <?= H::e($codigo) ?> no está publicado en el sitio en este momento.</p>
  <p><?php if ($caja): ?><a class="boton boton--secundario" href="<?= H::e($enl->caja($caja)) ?>">Ver la caja <?= (int)$caja['numero'] ?>: <?= H::e($caja['nombre']) ?></a>
     <?php else: ?><a class="boton boton--secundario" href="<?= H::e($enl->explorar()) ?>">Explorar el archivo</a><?php endif ?></p>
</div>
<?php $contenido = ob_get_clean();
require __DIR__ . '/_sitio.php';
