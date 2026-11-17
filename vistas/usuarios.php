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

<div class="tabla-scroll" tabindex="0" role="region" aria-label="Lista de usuarios">
<table>
  <thead><tr><th>Usuario</th><th>Perfil</th><th>Estado</th><th>Último ingreso</th></tr></thead>
  <tbody>
  <?php foreach ($usuarios as $u): $url = App::url('usuario', ['id' => $u['id']]); ?>
    <tr class="fila-link" onclick="location.href='<?= $url ?>'">
      <td><a href="<?= $url ?>"><?= e($u['nombre'] ?: $u['email']) ?></a><?= (int)$u['id'] === App::usuarioId() ? ' <span class="nota">· vos</span>' : '' ?>
        <?php if ($u['nombre']): ?><br><span class="nota"><?= e($u['email']) ?></span><?php endif ?></td>
      <td><?= e(App::ROLES[$u['rol']] ?? $u['rol']) ?></td>
      <td><span class="chip <?= (int)$u['activo'] ? 'chip-ok' : '' ?>"><?= (int)$u['activo'] ? 'Activo' : 'Desactivado' ?></span></td>
      <td><?= $u['ultimo_ingreso'] ? fecha($u['ultimo_ingreso']) : '<span class="nota">Nunca</span>' ?></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
</div>

<section class="panel" style="margin-top:var(--e-5)">
  <h2>Qué puede hacer cada perfil</h2>
  <dl class="perfiles">
    <dt>Administrador</dt><dd>Todo, más usuarios y base de datos.</dd>
    <dt>Editor</dt><dd>Edita, da de baja, publica cajas, cura vocabularios, importa, corrige en lote y atiende consultas.</dd>
    <dt>Carga de datos</dt><dd>Crea y edita registros y deshace sus propios cambios.</dd>
    <dt>Control de calidad</dt><dd>Ve todo y valida verificaciones; no edita.</dd>
  </dl>
</section>
