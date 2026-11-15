<?php use Aida\Web\App; $titulo = 'Importaciones';
$etq = ['cajas nuevas' => 'cajas nuevas', 'registros nuevos' => 'registros nuevos', 'registros actualizados' => 'actualizados',
        'archivos nuevos' => 'archivos nuevos', 'archivos desaparecidos' => 'archivos que faltan', 'alertas que se cerraron solas' => 'alertas cerradas solas'];
?>
<div class="cabecera">
  <div>
    <p class="eyebrow">Historial de cargas</p>
    <h1>Importaciones y escaneos</h1>
    <p class="nota">Cada corrida del importador del Excel o del escaneo de archivos. Se puede deshacer completa.</p>
  </div>
</div>
<table>
  <thead><tr><th>#</th><th>Tipo</th><th>Fuente</th><th>Fecha</th><th>Resultado</th><th>Verificaciones</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($procesos as $p): $res = json_decode((string)$p['resumen'], true) ?: []; $vivos = (int)$p['cambios'] - (int)$p['deshechos']; ?>
    <tr>
      <td class="num"><?= (int)$p['id'] ?></td>
      <td><?= $p['tipo'] === 'importacion' ? 'Importación' : ($p['tipo'] === 'escaneo' ? 'Escaneo' : e($p['tipo'])) ?></td>
      <td><?= e($p['fuente']) ?></td>
      <td><?= fecha($p['iniciado_en']) ?></td>
      <td><div class="chips">
        <?php foreach ($etq as $k => $l): if (!empty($res[$k])): ?><span class="chip"><?= (int)$res[$k] ?> <?= $l ?></span><?php endif; endforeach ?>
        <?php if ($p['deshechos'] && !$vivos): ?><span class="chip chip-stop">Deshecha</span><?php elseif ($p['deshechos']): ?><span class="chip chip-warn"><?= (int)$p['deshechos'] ?> cambios deshechos</span><?php endif ?>
      </div></td>
      <td><?php if ($p['pendientes']): ?><a class="chip chip-acc" href="<?= App::url('notificaciones') ?>">🔔 <?= (int)$p['pendientes'] ?></a><?php endif ?></td>
      <td><?php if ($vivos > 0 && App::puede('deshacer_todo')): ?><a class="btn btn-chico" href="<?= App::url('proceso_deshacer', ['id' => $p['id']]) ?>">Deshacer…</a><?php endif ?></td>
    </tr>
  <?php endforeach ?>
  <?php if (!$procesos): ?><tr><td colspan="7" class="nota">Todavía no se corrió ninguna importación.</td></tr><?php endif ?>
  </tbody>
</table>
<p class="nota">Por ahora la importación y el escaneo se corren desde el servidor: <code>php bin/importar.php</code> y <code>php bin/escanear.php</code>.</p>
