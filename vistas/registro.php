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
  <span class="codigo"><?= e($r['codigo']) ?></span>
</div>
<div class="cabecera">
  <div>
    <p class="eyebrow">Registro <?= (int)$r['numero'] ?> · <span class="codigo"><?= e($r['codigo']) ?></span></p>
    <h1><?= e($r['titulo'] ?: ($tipos ? implode(' · ', $tipos) : 'Sin título ni tipo cargado')) ?></h1>
    <div class="chips">
      <?= $r['digitalizado'] ? '<span class="chip chip-ok">Digitalizado</span>' : '<span class="chip">Sin digitalizar</span>' ?>
      <span class="chip <?= $r['acceso'] === 'publico' ? '' : 'chip-warn' ?>"><?= acceso_txt($r['acceso']) ?></span>
      <span class="chip"><?= $r['origen'] === 'backoffice' ? 'Cargado a mano' : 'Desde el Excel' ?></span>
      <?php if (!$r['publicado']): ?><span class="chip chip-warn">No publicado</span><?php endif ?>
      <?php if ($r['anio_sin_confirmar']): ?><span class="chip chip-stop" title="La planilla dice “<?= e($r['anio_texto']) ?>”">Año no confirmado</span><?php endif ?>
    </div>
  </div>
  <div class="acciones">
    <a class="btn" href="<?= App::url('vista_previa', ['registro' => $r['id']]) ?>" target="_blank" rel="noopener" title="Abre en una pestaña nueva cómo se vería esta ficha en el sitio público">Ver como en el sitio ↗</a>
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
        <button class="btn btn-chico btn-peligro-borde" data-doble="El Excel va a poder pisar lo cargado a mano. ¿Confirmar?">Pasar a origen Excel</button>
      </form>
    <?php endif ?>
  </div>
<?php endif ?>
<?php if ($r['acceso'] === 'a_revisar'): ?>
  <div class="leyenda leyenda-warn"><div><b>Acceso sin definir.</b> La planilla dice “<?= e($r['excel_privado']) ?>”. No se publica hasta elegir Público o Restringido.</div></div>
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
      if ($r['anio_texto'] !== null && $r['anio_texto'] !== '') {
          // Los años a los que responde en el filtro (registro_anio), como se ven en el sitio
          $etq = \Aida\Texto::aniosEtiqueta($anios ?? []);
          echo '<p class="nota anio-filtro' . ($r['anio_sin_confirmar'] ? ' anio-dudoso' : '') . '">'
             . ($etq === null ? 'Sin año para el filtro: el texto de la columna Año no es un año, un rango, una lista ni una fecha.'
                 : 'Para el filtro por año: <b>' . e($etq) . '</b>' . ($r['anio_sin_confirmar'] ? ' · <b>año no confirmado</b> (la planilla lo trae con duda)' : ''))
             . '</p>';
      }
      echo '<div class="dos">'; $campo('paginas'); $campo('isbn'); echo '</div>';
      $campo('resumen', 'area');
      ?>
      <?php
      $selector = function (string $nombre, string $titulo, array $lista, array $elegidos) use ($bloqueado, $puedeEditar) {
          $k = $nombre === 'tipos' ? 'tipos' : 'descriptores';
          echo '<div class="' . ($bloqueado($k) ? 'campo-bloqueado' : '') . '" style="margin-bottom:12px"><span class="etiqueta">' . e($titulo) . '</span>';
          echo '<div class="selector-terminos"><div class="elegidos" aria-live="polite"></div>';
          echo '<details' . ($puedeEditar ? '' : ' hidden') . '><summary>Agregar o quitar ' . mb_strtolower(e($titulo)) . ' (' . count($lista) . ')</summary>';
          echo '<input type="search" class="buscar" placeholder="Buscar…" aria-label="Buscar en ' . e($titulo) . '"><div class="lista">';
          foreach ($lista as $v) {
              $on = in_array($v['nombre'], $elegidos, true);
              echo '<label class="' . ($v['estado'] === 'propuesto' ? 'propuesto' : '') . '"><input type="checkbox" name="' . $nombre . '[]" value="' . e($v['nombre']) . '"'
                 . ($on ? ' checked' : '') . ($puedeEditar ? '' : ' disabled') . '><span>' . e($v['nombre']) . '</span></label>';
          }
          echo '</div></details></div></div>';
      };
      ?>
      <div class="dos">
        <?php $selector('tipos', 'Tipo de documento', $vocab['tipo_documento'], $tipos); ?>
        <?php $selector('descriptores', 'Descriptores', $vocab['descriptor'], $descriptores); ?>
      </div>
      <p class="nota">Los términos marcados “propuesto” todavía no fueron curados en Vocabularios.</p>
      <?php foreach (['tipos', 'descriptores'] as $k): if ($bloqueado($k)): ?>
        <div class="candado">✎ <?= $k === 'tipos' ? 'Tipos' : 'Descriptores' ?> editados a mano. La planilla ya no los actualiza.
          <button type="submit" form="desbloquear-<?= $k ?>" class="link" data-doble="La planilla va a reemplazarlos. ¿Confirmar?">Volver a tomarlos del Excel</button></div>
      <?php endif; endforeach ?>
      <?php $campo('obs_editorial', 'area'); $campo('edicion_catalogo', 'area'); $campo('obs_conservacion', 'area'); $campo('obs_digitalizacion', 'area'); ?>

      <fieldset class="panel">
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
        <div class="acciones acciones-guardar">
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
            <?php if ($origen->nombre() === 'drive' || $a['drive_id'] !== null): ?>
              <span class="nota">Drive: <?= $a['drive_id'] !== null ? '<code>' . e($a['drive_id']) . '</code>' : 'sin id en el listado' ?>
                · <?= $a['drive_compartido'] === null ? 'compartido: sin dato' : ((int)$a['drive_compartido'] ? 'compartido con “cualquiera con el enlace”' : 'no compartido') ?></span><br>
            <?php endif ?>
            <?php if (!$quitado && $a['presente'] && $a['drive_compartido'] !== null): $comp = (int)$a['drive_compartido'] === 1; ?>
              <?php if ($comp && $r['acceso'] !== 'publico'): ?>
                <span class="chip chip-stop">En Drive cualquiera con el enlace lo abre, y el registro es <?= $r['acceso'] === 'restringido' ? 'restringido' : 'a revisar' ?></span><br>
              <?php elseif (!$comp && $r['acceso'] === 'publico' && $r['publicado'] && $origen->nombre() === 'drive'): ?>
                <span class="chip chip-warn">Público, pero en Drive no está compartido: el visor del sitio no lo va a mostrar</span><br>
              <?php endif ?>
            <?php endif ?>
            <?php if ($quitado): ?><span class="nota">Quitado desde el backoffice el <?= fecha($a['eliminado_en']) ?>. El escaneo no lo vuelve a asociar.</span>
            <?php elseif (!$a['presente']): ?><span class="nota">No apareció en el último escaneo.</span><?php endif ?>
            <?php if (!$quitado && $a['presente']): $url = '../archivo.php?id=' . (int)$a['id']; ?>
              <div class="acciones" style="margin-top:6px">
                <a class="btn btn-chico" href="<?= e($url) ?>" target="_blank" rel="noopener">Ver <?= e(strtoupper($a['formato'])) ?> ↗</a>
                <a class="btn btn-chico" href="<?= e($url) ?>&amp;descargar=1">Descargar</a>
                <?php if (strtolower($a['formato']) === 'pdf' && $origen->admiteVisor($a)): ?>
                  <button type="button" class="btn btn-chico" data-vista="<?= e($url) ?>" data-nombre="<?= e($a['nombre_archivo']) ?>" aria-controls="vista-previa">Vista previa ↓</button>
                <?php endif ?>
              </div>
              <?php if (!$origen->admiteVisor($a)): ?>
                <span class="nota">Pesa más de lo que abre el visor de Drive: en el sitio se ofrece solo la descarga.</span>
              <?php endif ?>
              <?php if ($r['acceso'] !== 'publico' || !$r['publicado']): ?>
                <span class="nota">En el sitio no se puede ver: <?= !$r['publicado'] ? 'el registro no está publicado' : 'se consulta a pedido' ?>. Vos lo ves porque estás en el backoffice.</span>
              <?php endif ?>
            <?php endif ?>
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
      <p class="nota">Los archivos se asocian solos con el escaneo <?= $origen->nombre() === 'drive' ? 'del listado de Drive' : 'de carpetas' ?> (nombre C<?= (int)$caja['numero'] ?>R<?= (int)$r['numero'] ?>.pdf).</p>
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
      <section class="panel zona-riesgo">
        <h2>Zona de riesgo</h2>
        <p class="nota">El registro deja de mostrarse en el sitio. Se puede restaurar.</p>
        <a class="btn btn-chico btn-peligro-borde" href="<?= App::url('registro_baja', ['id' => $r['id']]) ?>">Dar de baja el registro…</a>
      </section>
    <?php endif ?>
  </aside>
</div>

<section class="panel" id="vista-previa" hidden tabindex="-1">
  <h2>Vista previa · <span class="nombre"></span></h2>
  <p class="nota">Visor del navegador. Los PDF pesados cargan de a partes; para verlo más cómodo usá “Ver PDF ↗”.
    <button type="button" class="link cerrar">Cerrar vista previa</button></p>
</section>
