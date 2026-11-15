<?php use Aida\Web\App; use Aida\Web\Rutas;
$titulo = $r['codigo'];
$volver = App::url('registro', ['id' => $r['id']]);
$puedeEditar = App::puede('editar') && !$r['eliminado_en'];
$bloqueado = fn(string $k) => in_array($k, $bloqueados, true);
$hayArchivo = (bool)array_filter($archivos, fn($a) => $a['presente'] && !$a['eliminado_en']);
?>
<div class="migas">
  <a href="<?= App::url('cajas') ?>">Cajas</a> /
  <a href="<?= App::url('caja', ['id' => $caja['id']]) ?>">Caja <?= (int)$caja['numero'] ?> · <?= e($caja['nombre']) ?></a> /
  <?= e($r['codigo']) ?>
</div>
<div class="cabecera">
  <div>
    <p class="eyebrow">Registro <?= (int)$r['numero'] ?> · <?= e($r['codigo']) ?></p>
    <h1><?= e($r['titulo'] ?: ($tipos ? implode(' · ', $tipos) : 'Sin título ni tipo cargado')) ?></h1>
    <div class="chips">
      <?= $r['digitalizado'] ? '<span class="chip chip-ok">Digitalizado</span>' : '<span class="chip">Sin archivo</span>' ?>
      <span class="chip <?= $r['acceso'] === 'publico' ? '' : 'chip-warn' ?>"><?= acceso_txt($r['acceso']) ?></span>
      <span class="chip"><?= $r['origen'] === 'backoffice' ? 'Cargado a mano' : 'Desde el Excel' ?></span>
      <?php if (!$r['publicado']): ?><span class="chip chip-warn">No publicado</span><?php endif ?>
    </div>
  </div>
  <div class="acciones">
    <?php if ($anterior): ?><a class="btn btn-chico" href="<?= App::url('registro', ['id' => $anterior['id']]) ?>">← R<?= (int)$anterior['numero'] ?></a><?php endif ?>
    <?php if ($siguiente): ?><a class="btn btn-chico" href="<?= App::url('registro', ['id' => $siguiente['id']]) ?>">R<?= (int)$siguiente['numero'] ?> →</a><?php endif ?>
  </div>
</div>

<!-- Leyendas fijas -->
<?php if ($r['eliminado_en']): ?>
  <div class="leyenda leyenda-stop">
    <div><b>Dado de baja el <?= fecha($r['eliminado_en']) ?>.</b> No se muestra en el sitio y la importación no lo vuelve a crear.</div>
    <?php if (App::puede('baja')): ?><form method="post" action="<?= App::url('registro_restaurar', ['id' => $r['id']]) ?>"><?= App::campoToken() ?><button class="btn btn-chico btn-crear">Restaurar</button></form><?php endif ?>
  </div>
<?php endif ?>
<?php if ($caja['eliminado_en']): ?>
  <div class="leyenda leyenda-stop"><div>La caja de este registro está dada de baja, así que tampoco se muestra.</div></div>
<?php endif ?>
<?php if ($r['origen'] === 'backoffice'): ?>
  <div class="leyenda">
    <div><b>Cargado a mano.</b> La planilla no modifica este registro.</div>
    <?php if (App::puede('baja') && !$r['eliminado_en']): ?>
      <form method="post" action="<?= App::url('origen_excel', ['id' => $r['id']]) ?>"><?= App::campoToken() ?>
        <button class="btn btn-chico" data-doble="El Excel va a poder actualizarlo. ¿Confirmar?">Pasar a origen Excel</button>
      </form>
    <?php endif ?>
  </div>
<?php endif ?>
<?php if ($r['acceso'] === 'a_revisar'): ?>
  <div class="leyenda leyenda-warn"><div><b>Acceso sin definir.</b> La planilla dice «<?= e($r['excel_privado']) ?>». No se publica hasta elegir Público o Restringido.</div></div>
<?php elseif ($r['acceso'] === 'restringido'): ?>
  <div class="leyenda leyenda-acc"><div><b>Restringido:</b> la ficha se ve en el sitio; el documento se consulta a pedido.</div></div>
<?php endif ?>
<?php if (!$hayArchivo): ?>
  <div class="leyenda"><div><?= $r['excel_digitalizado'] ? 'La planilla lo marca digitalizado; el archivo todavía no subió.' : 'Todavía sin digitalizar.' ?></div></div>
<?php elseif ($r['excel_digitalizado'] === 0 || $r['excel_digitalizado'] === '0'): ?>
  <div class="leyenda"><div>Hay archivo, pero en la planilla figura como <b>no digitalizado</b>.</div></div>
<?php endif ?>
<?php if ($r['excel_nomenclatura'] && strtoupper(preg_replace('/(?<=[CR])0+(?=\d)/i', '', $r['excel_nomenclatura'])) !== $r['codigo']): ?>
  <div class="leyenda"><div>En la planilla la nomenclatura figura como <b><?= e($r['excel_nomenclatura']) ?></b>. Se usa <?= e($r['codigo']) ?> (caja + número de registro).</div></div>
<?php endif ?>

<?php require __DIR__ . '/_alertas.php'; ?>

<div class="grilla">
  <section class="panel">
    <h2>Ficha</h2>
    <form method="post" action="<?= App::url('registro_guardar', ['id' => $r['id']]) ?>" id="ficha">
      <?= App::campoToken() ?>
      <?php
      $campo = function (string $k, string $tipo = 'text') use ($r, $bloqueado, $bloqueadoPor, $puedeEditar) {
          $lbl = Rutas::CAMPOS_REGISTRO[$k];
          $ro = $puedeEditar ? '' : 'readonly';
          echo '<label class="' . ($bloqueado($k) ? 'campo-bloqueado' : '') . '">' . e($lbl);
          if ($tipo === 'area') {
              echo '<textarea name="' . $k . '" ' . $ro . ' rows="' . ($k === 'resumen' ? 7 : 3) . '">' . e($r[$k]) . '</textarea>';
          } else {
              echo '<input type="text" name="' . $k . '" value="' . e($r[$k]) . '" ' . $ro . '>';
          }
          echo '</label>';
          if ($bloqueado($k)) {
              $q = $bloqueadoPor[$k] ?? null;
              echo '<div class="candado">✎ Editado a mano' . ($q ? ' el ' . fecha($q['creado_en'], false) . ' por ' . e($q['usuario_nombre'] ?: $q['usuario_email'] ?: 'backoffice') : '')
                 . '. La planilla ya no actualiza este campo. '
                 . '<button type="submit" form="desbloquear-' . $k . '" class="link" data-doble="La planilla va a reemplazar este valor. ¿Confirmar?">Volver a tomarlo del Excel</button></div>';
          }
      };
      $campo('titulo');
      echo '<div class="dos">'; $campo('autor'); $campo('lugar'); echo '</div>';
      echo '<div class="dos">'; $campo('fecha_texto'); $campo('anio_texto'); echo '</div>';
      echo '<div class="dos">'; $campo('paginas'); $campo('isbn'); echo '</div>';
      $campo('resumen', 'area');
      ?>
      <div class="dos">
        <label class="<?= $bloqueado('tipos') ? 'campo-bloqueado' : '' ?>">Tipo de documento
          <select name="tipos[]" multiple <?= $puedeEditar ? '' : 'disabled' ?>>
            <?php foreach ($vocab['tipo_documento'] as $v): ?>
              <option value="<?= e($v['nombre']) ?>" <?= in_array($v['nombre'], $tipos, true) ? 'selected' : '' ?>><?= e($v['nombre']) ?><?= $v['estado'] === 'propuesto' ? ' (propuesto)' : '' ?></option>
            <?php endforeach ?>
          </select>
        </label>
        <label class="<?= $bloqueado('descriptores') ? 'campo-bloqueado' : '' ?>">Descriptores
          <select name="descriptores[]" multiple <?= $puedeEditar ? '' : 'disabled' ?>>
            <?php foreach ($vocab['descriptor'] as $v): ?>
              <option value="<?= e($v['nombre']) ?>" <?= in_array($v['nombre'], $descriptores, true) ? 'selected' : '' ?>><?= e($v['nombre']) ?><?= $v['estado'] === 'propuesto' ? ' (propuesto)' : '' ?></option>
            <?php endforeach ?>
          </select>
        </label>
      </div>
      <p class="nota">Ctrl/Cmd + clic para elegir varios. Los términos «propuestos» todavía no fueron curados.</p>
      <?php foreach (['tipos', 'descriptores'] as $k): if ($bloqueado($k)): ?>
        <div class="candado">✎ <?= $k === 'tipos' ? 'Tipos' : 'Descriptores' ?> editados a mano. La planilla ya no los actualiza.
          <button type="submit" form="desbloquear-<?= $k ?>" class="link" data-doble="La planilla va a reemplazarlos. ¿Confirmar?">Volver a tomarlos del Excel</button></div>
      <?php endif; endforeach ?>
      <?php $campo('obs_editorial', 'area'); $campo('edicion_catalogo', 'area'); $campo('obs_conservacion', 'area'); $campo('obs_digitalizacion', 'area'); ?>

      <fieldset class="panel" style="background:var(--paper)">
        <legend>Publicación</legend>
        <div class="dos">
          <label class="<?= $bloqueado('acceso') ? 'campo-bloqueado' : '' ?>">Acceso
            <select name="acceso" <?= ($puedeEditar && App::puede('acceso')) ? '' : 'disabled' ?>>
              <?php foreach (['publico', 'restringido', 'a_revisar'] as $a): ?><option value="<?= $a ?>" <?= $r['acceso'] === $a ? 'selected' : '' ?>><?= acceso_txt($a) ?></option><?php endforeach ?>
            </select>
          </label>
          <label class="check" style="margin-top:22px"><input type="checkbox" name="publicado" value="1" <?= $r['publicado'] ? 'checked' : '' ?> <?= $puedeEditar ? '' : 'disabled' ?>> Publicado en el sitio</label>
        </div>
        <?php if ($r['excel_privado'] === 'VERDADERO'): ?>
          <label class="check"><input type="checkbox" name="confirmar_publico" value="1"> La planilla lo marca PRIVADO: confirmo que se puede publicar como público</label>
        <?php endif ?>
        <?php if (!App::puede('acceso')): ?><p class="nota">Tu perfil no puede cambiar el acceso.</p><?php endif ?>
      </fieldset>

      <?php if ($puedeEditar): ?>
        <div class="acciones">
          <button class="btn btn-primario">Guardar cambios</button>
          <?php if ($r['origen'] === 'excel'): ?><span class="nota">Los campos que cambies dejan de actualizarse desde el Excel.</span><?php endif ?>
        </div>
      <?php endif ?>
    </form>
    <?php foreach ($bloqueados as $k): ?>
      <form method="post" action="<?= App::url('campo_desbloquear') ?>" id="desbloquear-<?= e($k) ?>" hidden>
        <?= App::campoToken() ?><input type="hidden" name="entidad" value="registro"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="campo" value="<?= e($k) ?>">
      </form>
    <?php endforeach ?>
  </section>

  <aside>
    <section class="panel" id="archivos">
      <h2>Archivos</h2>
      <?php if (!$archivos): ?><p class="nota">Sin archivos asociados.</p><?php endif ?>
      <?php foreach ($archivos as $a): $quitado = (bool)$a['eliminado_en']; ?>
        <div class="leyenda <?= $quitado || !$a['presente'] ? 'leyenda-stop' : '' ?>">
          <div>
            <b><?= e($a['nombre_archivo']) ?></b><br>
            <span class="nota"><?= e(strtoupper($a['formato'])) ?><?= $a['peso_bytes'] ? ' · ' . number_format($a['peso_bytes'] / 1048576, 1, ',', '.') . ' MB' : '' ?> · <?= e($a['ruta']) ?></span><br>
            <?php if ($quitado): ?><span class="nota">Quitado desde el backoffice el <?= fecha($a['eliminado_en']) ?>. El escaneo no lo vuelve a asociar.</span>
            <?php elseif (!$a['presente']): ?><span class="nota">No apareció en el último escaneo.</span><?php endif ?>
          </div>
          <?php if (App::puede('baja') && !$r['eliminado_en']): ?>
            <form method="post" action="<?= App::url($quitado ? 'archivo_restaurar' : 'archivo_baja', ['id' => $a['id']]) ?>">
              <?= App::campoToken() ?>
              <?php if ($quitado): ?><button class="btn btn-chico btn-crear">Volver a asociar</button>
              <?php else: ?><button class="btn btn-chico btn-peligro-borde" data-doble="Se oculta del registro (no se borra). ¿Confirmar?">Quitar</button><?php endif ?>
            </form>
          <?php endif ?>
        </div>
      <?php endforeach ?>
      <p class="nota">Los archivos se asocian solos con el escaneo de carpetas (nombre C<?= (int)$caja['numero'] ?>R<?= (int)$r['numero'] ?>.pdf).</p>
    </section>

    <section class="panel">
      <h2>Datos de la planilla</h2>
      <table>
        <tr><td>Digitalización</td><td><?= $r['excel_digitalizado'] === null ? '—' : ($r['excel_digitalizado'] ? 'Sí' : 'No') ?></td></tr>
        <tr><td>Nombre de archivo</td><td><?= e($r['excel_nomenclatura'] ?: '—') ?></td></tr>
        <tr><td>PRIVADO</td><td><?= e($r['excel_privado'] ?: '—') ?></td></tr>
        <tr><td>Última importación</td><td><?= fecha($r['importado_en']) ?: '—' ?></td></tr>
      </table>
    </section>

    <section class="panel">
      <h2>Historial</h2>
      <?php require __DIR__ . '/_historial.php'; ?>
    </section>

    <?php if (!$r['eliminado_en'] && App::puede('baja')): ?>
      <p><a class="btn btn-chico btn-peligro-borde" href="<?= App::url('registro_baja', ['id' => $r['id']]) ?>">Dar de baja el registro…</a></p>
    <?php endif ?>
  </aside>
</div>
