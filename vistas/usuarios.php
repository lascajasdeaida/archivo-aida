<?php
use Aida\Web\App;

$titulo = 'Usuarios';
?>
<div class="cabecera">
  <div>
    <p class="eyebrow">Administración</p>
    <h1>Usuarios</h1>
  </div>
  <div class="acciones"><a class="btn btn-crear" href="<?= App::url('usuario') ?>">Nuevo usuario</a></div>
</div>
<p class="nota">Quién puede entrar al backoffice y con qué perfil. Los usuarios no se borran: se desactivan y dejan de poder ingresar.
  <?php if (!$correo): ?><br><b>Correo sin configurar:</b> los enlaces de alta y de contraseña se muestran para copiarlos y pasarlos a mano.<?php endif ?></p>

<table>
  <thead><tr><th>Nombre</th><th>Correo</th><th>Perfil</th><th>Estado</th><th>Último ingreso</th></tr></thead>
  <tbody>
  <?php foreach ($usuarios as $u): $url = App::url('usuario', ['id' => $u['id']]); ?>
    <tr class="fila-link" onclick="location.href='<?= $url ?>'">
      <td><a href="<?= $url ?>"><?= e($u['nombre'] ?: '(sin nombre)') ?></a><?= (int)$u['id'] === App::usuarioId() ? ' <span class="nota">· vos</span>' : '' ?></td>
      <td><?= e($u['email']) ?></td>
      <td><?= e(App::ROLES[$u['rol']] ?? $u['rol']) ?></td>
      <td><span class="chip <?= (int)$u['activo'] ? 'chip-ok' : '' ?>"><?= (int)$u['activo'] ? 'Activo' : 'Desactivado' ?></span></td>
      <td><?= $u['ultimo_ingreso'] ? fecha($u['ultimo_ingreso']) : '<span class="nota">Nunca</span>' ?></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>

<section class="panel" style="margin-top:var(--e-5)">
  <h2>Qué puede hacer cada perfil</h2>
  <table>
    <tr><td style="width:24%"><b>Administrador</b></td><td>Todo, más usuarios y base de datos.</td></tr>
    <tr><td><b>Editor</b></td><td>Edita, da de baja, publica cajas, cura vocabularios, importa, corrige en lote y atiende consultas.</td></tr>
    <tr><td><b>Carga de datos</b></td><td>Crea y edita registros y deshace sus propios cambios.</td></tr>
    <tr><td><b>Control de calidad</b></td><td>Ve todo y valida verificaciones; no edita.</td></tr>
  </table>
</section>
