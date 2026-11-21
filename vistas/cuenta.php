<?php
use Aida\Usuarios;
use Aida\Web\App;

$titulo = 'Mi cuenta';
?>
<section class="panel" style="max-width:520px">
  <h1>Mi cuenta</h1>
  <table>
    <tr><td style="width:35%">Correo</td><td><?= e($u['email']) ?></td></tr>
    <tr><td>Nombre</td><td><?= e($u['nombre'] ?: '—') ?></td></tr>
    <tr><td>Perfil</td><td><?= e(App::ROLES[$u['rol']] ?? $u['rol']) ?></td></tr>
  </table>
  <h2>Cambiar contraseña</h2>
  <?php if ($error): ?><div class="aviso aviso-error" role="alert"><?= e($error) ?></div><?php endif ?>
  <form method="post" action="<?= App::url('cuenta') ?>">
    <?= App::campoToken() ?>
    <input type="email" name="email" value="<?= e($u['email']) ?>" autocomplete="username" hidden>
    <label>Contraseña actual<input type="password" name="actual" required autocomplete="current-password"></label>
    <label>Contraseña nueva<input type="password" name="clave" required minlength="<?= Usuarios::CLAVE_MIN ?>" autocomplete="new-password"></label>
    <label>Repetila<input type="password" name="clave2" required minlength="<?= Usuarios::CLAVE_MIN ?>" autocomplete="new-password"></label>
    <p class="nota">Al menos <?= Usuarios::CLAVE_MIN ?> caracteres. Al cambiarla se cierran tus otras sesiones abiertas.</p>
    <div class="acciones"><button class="btn btn-primario">Cambiar contraseña</button></div>
  </form>
</section>
