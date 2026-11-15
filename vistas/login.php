<?php use Aida\Web\App; ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Ingresar · Las cajas de Aída</title>
<link rel="stylesheet" href="estilo.css">
</head>
<body class="pagina-login">
<form method="post" action="<?= App::url('login') ?>" class="caja-login">
  <?= App::campoToken() ?>
  <p class="eyebrow">Archivo · Backoffice</p>
  <h1>Las cajas de <em>Aída</em></h1>
  <?php if ($error): ?><div class="aviso aviso-error"><?= e($error) ?></div><?php endif ?>
  <label>Correo<input type="email" name="email" required autofocus autocomplete="username"></label>
  <label>Contraseña<input type="password" name="clave" required autocomplete="current-password"></label>
  <button class="btn btn-primario">Ingresar</button>
</form>
</body>
</html>
