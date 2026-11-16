<?php use Aida\Web\App; ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Ingresar · Las cajas de Aída</title>
<?php $v = (int)@filemtime(dirname(__DIR__) . "/public/admin/estilo.css") + (int)@filemtime(dirname(__DIR__) . "/public/admin/tokens.css"); ?>
<link rel="stylesheet" href="tokens.css?v=<?= $v ?>">
<link rel="stylesheet" href="estilo.css?v=<?= $v ?>">
</head>
<body class="pagina-login">
<main class="login">
<h1 class="login__logo"><img src="img/logo-claro.svg" alt="Las cajas de Aída" width="300" height="132"></h1>
<p class="login__rotulo">Archivo · Backoffice</p>
<form method="post" action="<?= App::url('login') ?>" class="caja-login">
  <?= App::campoToken() ?>
  <?php if ($error): ?><div class="aviso aviso-error"><?= e($error) ?></div><?php endif ?>
  <label>Correo<input type="email" name="email" required autofocus autocomplete="username"></label>
  <label>Contraseña<input type="password" name="clave" required autocomplete="current-password"></label>
  <button class="btn btn-primario">Ingresar</button>
</form>
</main>
</body>
</html>
