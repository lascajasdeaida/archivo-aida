<?php use Aida\Web\App; ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Ingresar · Las cajas de Aída</title>
<?php $v = (int)@filemtime(dirname(__DIR__) . "/public/admin/estilo.css") + (int)@filemtime(dirname(__DIR__) . "/public/admin/tokens.css"); ?>
<link rel="icon" href="img/favicon.svg" type="image/svg+xml">
<link rel="icon" href="img/favicon-32.png" sizes="32x32">
<link rel="apple-touch-icon" href="img/apple-touch-icon.png">
<link rel="stylesheet" href="tokens.css?v=<?= $v ?>">
<link rel="stylesheet" href="estilo.css?v=<?= $v ?>">
</head>
<body class="pagina-login">
<main class="login">
<h1 class="login__logo"><img src="img/logo-claro.svg" alt="Las cajas de Aída" width="300" height="132"></h1>
<p class="login__rotulo">Archivo · Backoffice</p>
<form method="post" action="<?= App::url('login') ?>" class="caja-login">
  <?= App::campoToken() ?>
  <?php foreach (App::avisos() as [$tipo, $texto]): ?><div class="aviso aviso-<?= e($tipo) ?>" role="status"><?= e($texto) ?></div><?php endforeach ?>
  <?php if ($error): ?><div class="aviso aviso-error" role="alert"><?= e($error) ?></div><?php endif ?>
  <label>Correo<input type="email" name="email" required autofocus autocomplete="username"></label>
  <label>Contraseña<input type="password" name="clave" required autocomplete="current-password"></label>
  <button class="btn btn-primario">Ingresar</button>
  <p class="login__extra"><a href="<?= App::url('olvide') ?>">Olvidé mi contraseña</a></p>
</form>
</main>
</body>
</html>
