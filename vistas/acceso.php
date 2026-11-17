<?php
use Aida\Usuarios;
use Aida\Web\App;

// Pantallas sin sesión: «Olvidé mi contraseña» y crear / restablecer la contraseña desde un enlace.
// $modo: olvide | vencido | alta | restablecer
$titulos = ['olvide' => 'Olvidé mi contraseña', 'vencido' => 'Enlace vencido', 'alta' => 'Crear tu contraseña', 'restablecer' => 'Nueva contraseña'];
$error = $error ?? null;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">
<title><?= e($titulos[$modo]) ?> · Las cajas de Aída</title>
<?php $v = (int)@filemtime(dirname(__DIR__) . "/public/admin/estilo.css") + (int)@filemtime(dirname(__DIR__) . "/public/admin/tokens.css"); ?>
<link rel="icon" href="img/favicon.svg" type="image/svg+xml">
<link rel="icon" href="img/favicon-32.png" sizes="32x32">
<link rel="apple-touch-icon" href="img/apple-touch-icon.png">
<link rel="stylesheet" href="tokens.css?v=<?= $v ?>">
<link rel="stylesheet" href="estilo.css?v=<?= $v ?>">
</head>
<body class="pagina-login">
<main class="login">
<p class="login__logo"><img src="img/logo-claro.svg" alt="Las cajas de Aída" width="300" height="132"></p>
<p class="login__rotulo">Archivo · Backoffice</p>
<div class="caja-login">
  <h1 style="margin:0 0 var(--e-3);font-size:1.15rem"><?= e($titulos[$modo]) ?></h1>

  <?php if ($modo === 'olvide' && $enviado): ?>
    <div class="aviso aviso-ok" role="status">Si el correo corresponde a un usuario activo, te llega un enlace para elegir una contraseña nueva.
      Vale <?= Usuarios::VIGENCIA['restablecer'] ?> horas. Revisá también el correo no deseado.</div>
    <p class="login__extra"><a href="<?= App::url('login') ?>">Volver a ingresar</a></p>

  <?php elseif ($modo === 'olvide'): ?>
    <p class="nota">Escribí el correo con el que ingresás y te mandamos un enlace para elegir una contraseña nueva.</p>
    <?php if ($error): ?><div class="aviso aviso-error" role="alert"><?= e($error) ?></div><?php endif ?>
    <form method="post" action="<?= App::url('olvide') ?>">
      <?= App::campoToken() ?>
      <label>Correo<input type="email" name="email" required autofocus autocomplete="username"></label>
      <button class="btn btn-primario">Mandar enlace</button>
    </form>
    <p class="login__extra"><a href="<?= App::url('login') ?>">Volver a ingresar</a></p>

  <?php elseif ($modo === 'vencido'): ?>
    <div class="aviso aviso-error" role="alert">El enlace no es válido: puede haber vencido o ya se usó.</div>
    <p class="login__extra"><a href="<?= App::url('olvide') ?>">Pedir un enlace nuevo</a> · <a href="<?= App::url('login') ?>">Ingresar</a></p>

  <?php else: ?>
    <p class="nota"><?= $modo === 'alta' ? 'Elegí la contraseña para' : 'Elegí una contraseña nueva para' ?> <b><?= e($email) ?></b>.
      Al menos <?= Usuarios::CLAVE_MIN ?> caracteres; una frase corta es más segura y fácil de recordar.</p>
    <?php if ($error): ?><div class="aviso aviso-error" role="alert"><?= e($error) ?></div><?php endif ?>
    <form method="post" action="<?= App::url('restablecer') ?>">
      <?= App::campoToken() ?>
      <input type="hidden" name="t" value="<?= e($valor) ?>">
      <input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" hidden>
      <label>Contraseña nueva<input type="password" name="clave" required minlength="<?= Usuarios::CLAVE_MIN ?>" autocomplete="new-password" autofocus></label>
      <label>Repetila<input type="password" name="clave2" required minlength="<?= Usuarios::CLAVE_MIN ?>" autocomplete="new-password"></label>
      <button class="btn btn-primario">Guardar contraseña</button>
    </form>
  <?php endif ?>
</div>
</main>
</body>
</html>
