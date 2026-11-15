<?php
// Página mínima del sitio público para mensajes de la entrega de archivos. Diseño web la reemplaza por la plantilla del sitio.
// Recibe $tituloPagina y $cuerpo (HTML ya escapado).
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $esc($tituloPagina) ?> · Las cajas de Aída</title>
<style>
  body{margin:0;font:17px/1.55 Georgia,"Times New Roman",serif;background:#F6F0E6;color:#2A211A}
  main{max-width:36rem;margin:12vh auto;padding:0 24px}
  .eyebrow{font:600 .75rem/1 system-ui,sans-serif;letter-spacing:.18em;text-transform:uppercase;color:#5E5140}
  h1{font-size:1.7rem;line-height:1.2;margin:.4em 0 .6em}
  .ficha{border-left:4px solid #B8642A;background:#fff;padding:12px 16px;margin:20px 0}
  a{color:#8F4C18}
</style>
</head>
<body>
<main>
  <p class="eyebrow">Las cajas de Aída</p>
  <?= $cuerpo ?>
</main>
</body>
</html>
