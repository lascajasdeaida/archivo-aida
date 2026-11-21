<?php
use Aida\Usuarios;
use Aida\Web\App;

$nuevo = $u === null;
$titulo = $nuevo ? 'Nuevo usuario' : ($u['nombre'] ?: $u['email']);
$yo = !$nuevo && (int)$u['id'] === App::usuarioId();
$err = fn(string $k) => isset($errores[$k]) ? '<span class="campo-error" id="err-' . $k . '">' . e($errores[$k]) . '</span>' : '';
$aria = fn(string $k) => isset($errores[$k]) ? ' aria-invalid="true" aria-describedby="err-' . $k . '"' : '';
?>
<div class="migas"><a href="<?= App::url('usuarios') ?>">Usuarios</a> / <?= e($titulo) ?></div>
<div class="grilla">
<section class="panel">
  <h1><?= e($titulo) ?></h1>
  <?php if ($errores): ?><div class="aviso aviso-error" role="alert">Revisá los campos marcados.</div><?php endif ?>
  <form method="post" action="<?= App::url('usuario_guardar') ?>">
    <?= App::campoToken() ?>
    <?php if (!$nuevo): ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><?php endif ?>
    <label>Correo<input type="email" name="email" value="<?= e($form['email']) ?>" required autocomplete="off"<?= $aria('email') ?>><?= $err('email') ?></label>
    <label>Nombre<input type="text" name="nombre" value="<?= e($form['nombre'] ?? '') ?>" maxlength="120" autocomplete="off"<?= $aria('nombre') ?>><?= $err('nombre') ?></label>
    <label>Perfil
      <select name="rol"<?= $yo ? ' disabled' : '' ?><?= $aria('rol') ?>>
        <?php foreach (App::ROLES as $k => $n): ?><option value="<?= e($k) ?>"<?= $form['rol'] === $k ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach ?>
      </select><?= $err('rol') ?>
    </label>
    <?php if ($yo): ?><input type="hidden" name="rol" value="<?= e($form['rol']) ?>"><input type="hidden" name="activo" value="1"><?php endif ?>
    <label class="casilla"><input type="checkbox" name="activo" value="1"<?= (int)$form['activo'] ? ' checked' : '' ?><?= $yo ? ' disabled' : '' ?>> Activo (puede ingresar)</label><?= $err('activo') ?>
    <?php if ($yo): ?><p class="nota">Tu propio perfil y estado los cambia otro administrador.</p><?php endif ?>
    <?php if ($nuevo): ?><p class="nota">Al crearlo le llega un enlace para elegir su contraseña (vale <?= Usuarios::VIGENCIA['alta'] ?> horas).</p><?php endif ?>
    <div class="acciones">
      <button class="btn btn-primario"><?= $nuevo ? 'Crear usuario' : 'Guardar cambios' ?></button>
      <a class="btn" href="<?= App::url('usuarios') ?>">Volver</a>
    </div>
  </form>
</section>

<?php if (!$nuevo): ?>
<aside>
  <section class="panel">
    <h2>Acceso</h2>
    <table>
      <tr><td style="width:45%">Último ingreso</td><td><?= $u['ultimo_ingreso'] ? fecha($u['ultimo_ingreso']) : 'Nunca' ?></td></tr>
      <tr><td>Contraseña cambiada</td><td><?= $u['clave_cambiada_en'] ? fecha($u['clave_cambiada_en']) : '—' ?></td></tr>
      <tr><td>Alta</td><td><?= fecha($u['creado_en']) ?></td></tr>
    </table>
    <?php if ($enlace): ?>
      <div class="aviso <?= $enlace['enviado'] ? 'aviso-ok' : 'aviso-info' ?>">
        <?= $enlace['enviado'] ? 'Enlace enviado por correo. Por si no le llega, este es el mismo:' : 'Copiá este enlace y pasáselo por otro medio:' ?>
        <div class="enlace-copiar">
          <input type="text" readonly value="<?= e($enlace['url']) ?>" id="enlace-acceso" aria-label="Enlace de acceso" onfocus="this.select()">
          <button type="button" class="btn" onclick="navigator.clipboard.writeText(document.getElementById('enlace-acceso').value).then(()=>{this.textContent='Copiado'})">Copiar</button>
        </div>
        <span class="nota">Se muestra una sola vez. Vale <?= Usuarios::VIGENCIA[$enlace['tipo']] ?> horas.</span>
      </div>
    <?php endif ?>
    <?php if ((int)$u['activo']): ?>
    <form method="post" action="<?= App::url('usuario_enlace') ?>">
      <?= App::campoToken() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
      <button class="btn"><?= $u['ultimo_ingreso'] ? 'Mandar enlace para nueva contraseña' : 'Reenviar enlace de alta' ?></button>
      <p class="nota">Anula cualquier enlace anterior. La contraseña actual sigue valiendo hasta que se use el enlace.</p>
    </form>
    <?php endif ?>
  </section>
</aside>
<?php endif ?>
</div>
