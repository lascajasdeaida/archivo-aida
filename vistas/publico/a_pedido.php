<?php
// Documento restringido o con acceso a revisar. Recibe $a (archivo + registro) solo si es «restringido».
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$tituloPagina = 'Documento de consulta a pedido';
ob_start(); ?>
  <h1>Documento de consulta a pedido</h1>
  <?php if (!empty($a)): ?>
    <div class="ficha"><b><?= $esc($a['codigo']) ?></b><?= $a['titulo'] ? ' · ' . $esc($a['titulo']) : '' ?><br>
      Caja <?= (int)$a['caja_numero'] ?> · <?= $esc($a['caja_nombre']) ?></div>
  <?php endif ?>
  <p>Este documento forma parte del archivo, pero no está disponible para ver en línea.
     Se puede consultar a pedido: escribinos indicando el código del documento y te contamos cómo acceder.</p>
  <p><a href="/">Volver al archivo</a></p>
<?php $cuerpo = ob_get_clean();
require __DIR__ . '/_base.php';
