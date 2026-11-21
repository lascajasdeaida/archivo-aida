<?php
$tituloPagina = 'Documento no disponible';
ob_start(); ?>
  <h1>Documento no disponible</h1>
  <p>El documento que buscás no existe o ya no está publicado.</p>
  <p><a href="/">Volver al archivo</a></p>
<?php $cuerpo = ob_get_clean();
require __DIR__ . '/_base.php';
