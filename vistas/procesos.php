<?php use Aida\Web\App; $titulo = 'Importaciones';
$etq = ['cajas nuevas' => 'cajas nuevas', 'registros nuevos' => 'registros nuevos', 'registros actualizados' => 'actualizados',
        'correcciones aplicadas' => 'celdas corregidas', 'registros corregidos' => 'registros', 'cajas corregidas' => 'cajas',
        'campos corregidos en la planilla (se desbloquean)' => 'corregidos en la planilla',
        'años recalculados' => 'años recalculados',
        'archivos nuevos' => 'archivos nuevos', 'archivos desaparecidos' => 'archivos que faltan', 'alertas que se cerraron solas' => 'alertas cerradas solas'];
?>
<div class="cabecera">
  <div>
    <p class="eyebrow">Historial de cargas</p>
    <h1>Importaciones y escaneos</h1>
    <p class="nota">Cada corrida del importador del Excel, del escaneo de archivos y cada corrección masiva. Se puede deshacer completa.</p>
  </div>
  <?php if (App::puede('procesos')): ?>
    <div class="acciones">
      <?php if (App::puede('correccion')): ?><a class="btn" href="<?= App::url('correccion') ?>">Corrección masiva…</a><?php endif ?>
      <?php if (App::puede('procesos')): ?><a class="btn" href="<?= App::url('almacenamiento') ?>#listado">Listado de Drive…</a><?php endif ?>
      <a class="btn btn-crear" href="<?= App::url('importar') ?>">Subir Excel e importar…</a>
    </div>
  <?php endif ?>
</div>
<table>
  <thead><tr><th>#</th><th>Tipo</th><th>Fuente</th><th>Quién</th><th>Fecha</th><th>Resultado</th><th>Verificaciones</th><th>Acciones</th></tr></thead>
  <tbody>
  <?php foreach ($procesos as $p): $res = json_decode((string)$p['resumen'], true) ?: []; $vivos = (int)$p['cambios'] - (int)$p['deshechos']; ?>
    <tr>
      <td class="num"><?= (int)$p['id'] ?></td>
      <td><?= ['importacion' => 'Importación', 'escaneo' => 'Escaneo', 'correccion' => 'Corrección masiva', 'acceso_masivo' => 'Cambio de acceso'][$p['tipo']] ?? e($p['tipo']) ?></td>
      <td><?= e($p['fuente']) ?></td>
      <td><?= $p['usuario_nombre'] !== null ? e($p['usuario_nombre']) : '<span class="nota">Consola</span>' ?></td>
      <td><?= fecha($p['iniciado_en']) ?></td>
      <td><div class="chips">
        <?php foreach ($etq as $k => $l): if (!empty($res[$k])): ?><span class="chip"><?= (int)$res[$k] ?> <?= $l ?></span><?php endif; endforeach ?>
        <?php if ($p['deshechos'] && !$vivos): ?><span class="chip chip-stop">Deshecha</span><?php elseif ($p['deshechos']): ?><span class="chip chip-warn"><?= (int)$p['deshechos'] ?> cambios deshechos</span><?php endif ?>
      </div></td>
      <td><?php if ($p['pendientes']): ?><a class="chip chip-acc" href="<?= App::url('notificaciones') ?>">🔔 <?= (int)$p['pendientes'] ?></a><?php endif ?></td>
      <td><?php if ($vivos > 0 && App::puede('deshacer_todo')): ?><a class="btn btn-chico btn-peligro-borde" href="<?= App::url('proceso_deshacer', ['id' => $p['id']]) ?>">Deshacer…</a><?php endif ?></td>
    </tr>
  <?php endforeach ?>
  <?php if (!$procesos): ?><tr><td colspan="7" class="nota">Todavía no se corrió ninguna importación.</td></tr><?php endif ?>
  </tbody>
</table>
<p class="nota">El Excel se importa desde “Subir Excel e importar” (administración y edición). El escaneo de archivos lo corre por ahora el equipo técnico en el servidor.</p>
