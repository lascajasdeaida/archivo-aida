<?php
use Aida\Archivos\Espacio;
use Aida\Web\App;

$titulo = 'Almacenamiento';
$g = [Espacio::class, 'gb'];
$t = $informe['total'];
$p = $informe['proyeccion'];
$esDrive = $origen->nombre() === 'drive';
$listadoDel = $drive->listadoDel();
// Contadores e incidencias agrupadas (mismo bloque que Importar el Excel)
$resultado = function (array $x) { ?>
  <div class="chips" style="margin-bottom:12px">
    <?php foreach ($x['cuentas'] as $k => $v): ?><span class="chip <?= str_contains($k, 'nuev') || str_contains($k, 'cargad') ? 'chip-ok' : '' ?>"><b><?= (int)$v ?></b> <?= e($k) ?></span><?php endforeach ?>
  </div>
  <?php if ($x['grupos']): ?>
    <h3>Avisos (<?= (int)$x['total'] ?>)<?= $x['a_validar'] ? ' · ' . (int)$x['a_validar'] . ' quedan para validar' : '' ?></h3>
    <?php foreach ($x['grupos'] as $gr): ?>
      <details class="leyenda <?= $gr['nivel'] === 'error' ? 'leyenda-stop' : ($gr['validar'] ? 'leyenda-warn' : '') ?>" style="display:block">
        <summary><b><?= e($gr['titulo']) ?></b> · <?= (int)$gr['n'] ?><?= $gr['validar'] ? ' <span class="chip chip-warn">para validar</span>' : '' ?></summary>
        <p class="nota" style="margin:6px 0"><?= e($gr['leyenda']) ?></p>
        <table class="tabla-chica">
          <?php foreach ($gr['ejemplos'] as [$donde, $det]): ?><tr><td class="num"><?= e($donde) ?></td><td><?= e($det) ?></td></tr><?php endforeach ?>
        </table>
        <?php if ($gr['n'] > count($gr['ejemplos'])): ?><p class="nota">… y <?= $gr['n'] - count($gr['ejemplos']) ?> más.</p><?php endif ?>
      </details>
    <?php endforeach ?>
  <?php endif ?>
<?php };
?>
<div class="cabecera">
  <div>
    <p class="eyebrow">Archivos digitalizados</p>
    <h1>Almacenamiento</h1>
    <p class="nota">De dónde se sirven los documentos, el listado de Drive y cuánto espacio ocupan, para dimensionar el hosting.</p>
  </div>
</div>

<section class="panel" id="origen">
  <h2>Origen de los archivos</h2>
  <?php if ($esDrive): ?>
    <p><span class="chip chip-acc">Drive del cliente (provisorio)</span>
      El sitio muestra los documentos con el visor de Drive y la descarga va a Drive, siempre después de los controles de acceso de siempre.
      Los de más de <?= e($g($drive->visorMaxBytes())) ?> se ofrecen solo para descargar. Los restringidos nunca se entregan.</p>
    <p class="nota">Listado vigente: <?= $listadoDel ? 'cargado el ' . date('d/m/Y H:i', $listadoDel) : '<b>todavía no se cargó ninguno</b>' ?> ·
      <?= (int)$conIds ?> archivos presentes con id de Drive.</p>
    <?php if ($listadoDel && App::puede('procesos')): ?>
      <form method="post" action="<?= App::url('drive_reescanear') ?>" class="acciones">
        <?= App::campoToken() ?>
        <button class="btn btn-primario">Revisar de nuevo con el último listado</button>
        <span class="nota">Pone al día las alertas de Drive (por ejemplo, después de cambiar el acceso de un registro).</span>
      </form>
    <?php endif ?>
  <?php else: ?>
    <p><span class="chip">Disco propio</span> Los documentos se sirven desde la carpeta del servidor.
      <?php if ($conIds): ?><span class="nota">Hay <?= (int)$conIds ?> archivos con id de Drive guardado (se conservan).</span><?php endif ?></p>
  <?php endif ?>
  <p class="nota">Se cambia en <code>config/config.php</code> (<code>'archivos' => ['origen' => 'disco' | 'drive']</code>); ver <code>docs/despliegue.md</code>.</p>
</section>

<?php if (App::puede('procesos')): ?>
<section class="panel" id="listado">
  <h2>Listado de Drive</h2>
  <?php if ($sim): ?>
    <h3>Simulación · nada se guardó todavía</h3>
    <p><b><?= e($sim['original']) ?></b> · simulado el <?= date('d/m/Y H:i', (int)$sim['en']) ?></p>
    <?php $resultado($sim); ?>
    <div class="acciones acciones-guardar">
      <?php if ($esDrive): ?>
      <form method="post" action="<?= App::url('drive_listado_aplicar') ?>">
        <?= App::campoToken() ?>
        <input type="hidden" name="simulacion" value="<?= e($sim['token']) ?>">
        <button class="btn btn-primario" data-doble="Se guardan los id de Drive y qué archivos están presentes. El escaneo se puede deshacer desde Importaciones. ¿Confirmar?">Cargar el listado</button>
      </form>
      <?php else: ?>
        <span class="nota">El origen es «disco»: para cargar el listado hay que pasar el origen a «drive».</span>
      <?php endif ?>
      <form method="post" action="<?= App::url('drive_listado_descartar') ?>">
        <?= App::campoToken() ?>
        <button class="btn">Descartar</button>
      </form>
    </div>
  <?php else: ?>
    <?php if ($hecho): ?>
      <h3>Escaneo #<?= (int)$hecho['proceso'] ?> · <?= e($hecho['original']) ?></h3>
      <?php $resultado($hecho); ?>
      <div class="acciones">
        <a class="btn" href="<?= App::url('procesos') ?>">Ver en la lista de importaciones</a>
        <?php if ($hecho['a_validar']): ?><a class="btn" href="<?= App::url('notificaciones') ?>">🔔 Ir a las verificaciones</a><?php endif ?>
      </div>
      <hr>
    <?php endif ?>
    <p class="nota">Mientras no haya acceso a la API de Drive, los id se cargan con un listado: una línea por archivo con
      <b>ruta</b> (C1/C1R31.pdf), <b>id de Drive</b> (o el enlace al archivo), <b>bytes</b> y, si se sabe, <b>compartido</b>
      (sí/no: «cualquiera con el enlace»). TSV o CSV, con o sin encabezado. Primero se simula.</p>
    <form method="post" action="<?= App::url('almacenamiento') ?>" enctype="multipart/form-data" id="form-listado">
      <?= App::campoToken() ?>
      <label>Listado (.tsv, .csv o .txt, hasta <?= (int)$maxMb ?> MB)
        <input type="file" name="listado" accept=".tsv,.csv,.txt,text/tab-separated-values,text/csv,text/plain" required>
      </label>
      <div class="acciones"><button class="btn btn-primario">Simular el listado</button></div>
    </form>
  <?php endif ?>
</section>
<?php endif ?>

<section class="panel" id="espacio">
  <h2>Espacio</h2>
  <div class="chips" style="margin-bottom:12px">
    <span class="chip chip-acc"><b><?= e($g($t['bytes'])) ?></b> en total</span>
    <span class="chip"><b><?= (int)$t['archivos'] ?></b> archivos de <b><?= (int)$t['digitalizados'] ?></b> registros</span>
    <span class="chip"><b><?= (int)$t['cajas_con_archivos'] ?></b> de <?= (int)$t['cajas_base'] ?> cajas con archivos</span>
    <span class="chip"><b><?= e($g($t['promedio_registro'])) ?></b> por registro digitalizado</span>
    <span class="chip">el mayor: <b><?= e($g($t['mayor'])) ?></b></span>
    <span class="chip <?= $t['pesados'] ? 'chip-warn' : '' ?>"><b><?= (int)$t['pesados'] ?></b> de más de <?= e($g($informe['pesado'])) ?></span>
  </div>
  <?php if ($p['desde'] !== null): ?>
    <div class="leyenda leyenda-acc"><div>
      <b>Proyección a las <?= (int)$p['cajas'] ?> cajas: <?= e($g($p['desde'])) ?> a <?= e($g($p['hasta'])) ?></b><br>
      <span class="nota">Por registro: <?= e($g($t['promedio_registro'])) ?> × <?= number_format((int)$p['registros'], 0, ',', '.') ?> registros = <?= e($g($p['por_registro'])) ?>.
        Por caja: <?= e($g($t['promedio_caja'])) ?> × <?= (int)$p['cajas'] ?> cajas = <?= e($g($p['por_caja'])) ?>.
        Supone que se digitaliza todo y que lo que falta pesa como lo que ya está (hoy, <?= $p['avance'] === null ? '—' : number_format((float)$p['avance'], 1, ',', '.') ?> % de los registros).
        Sin margen para backups ni versiones livianas para web.</span>
    </div></div>
  <?php else: ?>
    <p class="nota">Todavía no hay archivos con peso conocido.</p>
  <?php endif ?>
  <?php if ($t['sin_peso']): ?><p class="nota"><?= (int)$t['sin_peso'] ?> archivos sin peso conocido no cuentan en el total.</p><?php endif ?>
  <?php if ($informe['cajas']): ?>
  <table>
    <thead><tr><th>Caja</th><th class="num">Registros</th><th class="num">Digitalizados</th><th class="num">Archivos</th>
      <th class="num">Espacio</th><th class="num">Por registro</th><th class="num">El mayor</th><th class="num">Más de <?= e($g($informe['pesado'])) ?></th></tr></thead>
    <tbody>
    <?php foreach ($informe['cajas'] as $c): ?>
      <tr><td>Caja <?= (int)$c['numero'] ?> · <?= e($c['nombre']) ?></td><td class="num"><?= (int)$c['registros'] ?></td>
        <td class="num"><?= (int)$c['digitalizados'] ?></td><td class="num"><?= (int)$c['archivos'] ?></td>
        <td class="num"><b><?= e($g($c['bytes'])) ?></b></td><td class="num"><?= e($g($c['promedio'])) ?></td>
        <td class="num"><?= e($g($c['mayor'])) ?></td><td class="num"><?= (int)$c['pesados'] ?: '—' ?></td></tr>
    <?php endforeach ?>
    </tbody>
    <tfoot><tr><th>Total</th><th class="num"><?= number_format((int)$t['registros_base'], 0, ',', '.') ?></th><th class="num"><?= (int)$t['digitalizados'] ?></th>
      <th class="num"><?= (int)$t['archivos'] ?></th><th class="num"><?= e($g($t['bytes'])) ?></th><th class="num"><?= e($g($t['promedio_registro'])) ?></th>
      <th class="num"><?= e($g($t['mayor'])) ?></th><th class="num"><?= (int)$t['pesados'] ?></th></tr></tfoot>
  </table>
  <?php endif ?>
  <p class="nota">También por consola: <code>php bin/espacio.php</code>.</p>
</section>
