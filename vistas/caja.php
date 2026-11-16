<?php use Aida\Web\App;
$titulo = "Caja {$caja['numero']}";
$volver = App::url('caja', ['id' => $caja['id']]);
$bloq = json_decode((string)$caja['campos_bloqueados'], true) ?: [];
?>
<div class="migas"><a href="<?= App::url('cajas') ?>">Cajas</a> / Caja <?= (int)$caja['numero'] ?></div>
<div class="cabecera">
  <div>
    <p class="eyebrow">Caja <?= (int)$caja['numero'] ?></p>
    <h1><?= e($caja['nombre']) ?></h1>
  </div>
  <div class="acciones">
    <a class="btn" href="<?= App::url('vista_previa', ['caja' => $caja['id']]) ?>" target="_blank" rel="noopener" title="Abre en una pestaña nueva cómo se vería esta caja en el sitio público">Ver como en el sitio ↗</a>
    <?php if (!$caja['eliminado_en'] && App::puede('editar')): ?><a class="btn btn-crear" href="<?= App::url('registro_nuevo', ['caja' => $caja['id']]) ?>">+ Nuevo registro</a><?php endif ?>
  </div>
</div>

<?php if ($caja['eliminado_en']): ?>
  <div class="leyenda leyenda-stop">
    <div><b>Dada de baja el <?= fecha($caja['eliminado_en']) ?>.</b> No se muestra en el sitio y la importación del Excel no la vuelve a crear.</div>
    <?php if (App::puede('baja')): ?>
    <form method="post" action="<?= App::url('caja_restaurar', ['id' => $caja['id']]) ?>"><?= App::campoToken() ?><button class="btn btn-chico btn-crear">Restaurar</button></form>
    <?php endif ?>
  </div>
<?php endif ?>

<?php require __DIR__ . '/_alertas.php'; ?>

<div class="grilla">
  <section>
    <form method="get" class="filtros">
      <input type="hidden" name="r" value="caja"><input type="hidden" name="id" value="<?= (int)$caja['id'] ?>">
      <label>Buscar<input type="search" name="q" value="<?= e($filtros['q']) ?>" placeholder="título, resumen, autor o C#R#"></label>
      <label>Estado<select name="estado">
        <option value="">Todos</option>
        <option value="digitalizado" <?= $filtros['estado'] === 'digitalizado' ? 'selected' : '' ?>>Digitalizados</option>
        <option value="pendiente" <?= $filtros['estado'] === 'pendiente' ? 'selected' : '' ?>>Sin digitalizar</option>
      </select></label>
      <label>Acceso<select name="acceso">
        <option value="">Todos</option>
        <?php foreach (['publico', 'restringido', 'a_revisar'] as $a): ?><option value="<?= $a ?>" <?= $filtros['acceso'] === $a ? 'selected' : '' ?>><?= acceso_txt($a) ?></option><?php endforeach ?>
      </select></label>
      <label class="check"><input type="checkbox" name="bajas" value="1" <?= $filtros['bajas'] ? 'checked' : '' ?>> incluir bajas</label>
      <button class="btn">Filtrar</button>
    </form>

    <table>
      <thead><tr><th>Código</th><th>Título / tipo de documento</th><th>Año</th><th>Estado</th></tr></thead>
      <tbody>
      <?php foreach ($registros as $r0): $url = App::url('registro', ['id' => $r0['id']]); ?>
        <tr class="fila-link <?= $r0['eliminado_en'] ? 'baja' : '' ?>" onclick="location.href='<?= $url ?>'">
          <td><a href="<?= $url ?>"><?= e($r0['codigo']) ?></a></td>
          <td>
            <?php if ($r0['titulo']): ?><?= e($r0['titulo']) ?><?php if ($r0['tipos']): ?><br><span class="nota"><?= e($r0['tipos']) ?></span><?php endif ?>
            <?php elseif ($r0['tipos']): ?><?= e($r0['tipos']) ?>
            <?php else: ?><span class="nota">Sin título ni tipo cargado</span><?php endif ?>
          </td>
          <td class="<?= $r0['anio_sin_confirmar'] ? 'anio-dudoso' : '' ?>"><?= e($r0['anio_texto']) ?>
            <?php if ($r0['anio_sin_confirmar']): ?><br><span class="chip chip-stop" title="Para el filtro: <?= e(\Aida\Texto::aniosEtiqueta(\Aida\Anios::calcular($r0))) ?>">Año no confirmado</span>
            <?php elseif ($r0['anio_texto'] !== null && $r0['anio'] === null): ?><br><span class="chip" title="No aparece en el filtro por año">sin año</span><?php endif ?></td>
          <td class="no-tachar"><div class="chips">
            <?php if ($r0['eliminado_en']): ?><span class="chip chip-stop">Baja</span><?php endif ?>
            <?= $r0['digitalizado'] ? '<span class="chip chip-ok">Digitalizado</span>' : ($r0['excel_digitalizado'] ? '<span class="chip chip-warn">Archivo pendiente</span>' : '<span class="chip">Sin digitalizar</span>') ?>
            <?php if ($r0['acceso'] !== 'publico'): ?><span class="chip chip-warn"><?= acceso_txt($r0['acceso']) ?></span><?php endif ?>
            <?php if ($r0['origen'] === 'backoffice'): ?><span class="chip">Cargado a mano</span><?php endif ?>
            <?php if ($r0['campos_bloqueados'] && $r0['campos_bloqueados'] !== '[]'): ?><span class="chip" title="Tiene campos editados a mano">✎</span><?php endif ?>
            <?php if ($r0['alertas']): ?><span class="chip chip-acc">🔔 <?= (int)$r0['alertas'] ?></span><?php endif ?>
          </div></td>
        </tr>
      <?php endforeach ?>
      <?php if (!$registros): ?><tr><td colspan="4" class="nota">No hay registros con esos filtros.</td></tr><?php endif ?>
      </tbody>
    </table>
    <p class="nota"><?= count($registros) ?> registros</p>
  </section>

  <aside>
    <?php $pubUrl = (new \Aida\Web\Enlaces('../', false, (aida_config()['sitio']['reescritura'] ?? true) !== false))->caja($caja); ?>
    <section class="panel" id="publicacion">
      <h2>Publicación en el sitio</h2>
      <?php if ($caja['publicada']): ?>
        <p><span class="chip chip-ok">Publicada</span> La caja y sus registros se ven en el sitio.</p>
        <p class="nota"><?= (int)$publicacion['visibles'] ?> registros visibles<?= $publicacion['a_pedido'] ? ', ' . (int)$publicacion['a_pedido'] . ' de consulta a pedido' : '' ?>.
          <a href="<?= e($pubUrl) ?>" target="_blank" rel="noopener">Ver en el sitio ↗</a></p>
      <?php else: ?>
        <p><span class="chip">Sin publicar</span> Ni la caja ni sus registros se ven en el sitio.</p>
        <p class="nota">Al publicarla se van a ver <?= (int)$publicacion['visibles'] ?> registros<?= $publicacion['a_pedido'] ? ' (' . (int)$publicacion['a_pedido'] . ' de consulta a pedido: la ficha sí, el documento no)' : '' ?>.</p>
      <?php endif ?>
      <?php if ($publicacion['a_revisar'] || $publicacion['no_publicados']): ?>
        <p class="nota">No entran: <?= implode(', ', array_filter([
            $publicacion['a_revisar'] ? $publicacion['a_revisar'] . ' con acceso «a revisar»' : '',
            $publicacion['no_publicados'] ? $publicacion['no_publicados'] . ' marcados como no publicados' : ''])) ?>.</p>
      <?php endif ?>
      <?php if (App::puede('publicar') && !$caja['eliminado_en']): ?>
      <form method="post" action="<?= App::url('caja_publicar', ['id' => $caja['id']]) ?>" class="acciones">
        <?= App::campoToken() ?>
        <?php if ($caja['publicada']): ?>
          <button class="btn btn-peligro-borde" data-doble="La caja y sus <?= (int)$publicacion['visibles'] ?> registros dejan de verse en el sitio (sus enlaces responden «ya no está disponible»). ¿Confirmar?">Despublicar la caja</button>
        <?php else: ?>
          <input type="hidden" name="publicar" value="1">
          <button class="btn btn-crear">Publicar la caja</button>
        <?php endif ?>
        <a class="btn" href="<?= App::url('vista_previa', ['caja' => $caja['id']]) ?>" target="_blank" rel="noopener">Vista previa ↗</a>
      </form>
      <?php endif ?>
    </section>

    <section class="panel">
      <h2>Datos de la caja</h2>
      <form method="post" action="<?= App::url('caja_guardar', ['id' => $caja['id']]) ?>">
        <?= App::campoToken() ?>
        <?php foreach (['nombre' => 'Nombre', 'resumen' => 'Resumen (edición de catálogo)'] as $k => $lbl): ?>
          <label class="<?= in_array($k, $bloq, true) ? 'campo-bloqueado' : '' ?>"><?= $lbl ?>
            <?php if ($k === 'resumen'): ?><textarea name="resumen" rows="8" <?= App::puede('editar') ? '' : 'readonly' ?>><?= e($caja['resumen']) ?></textarea>
            <?php else: ?><input type="text" name="<?= $k ?>" value="<?= e($caja[$k]) ?>" required <?= App::puede('editar') ? '' : 'readonly' ?>><?php endif ?>
          </label>
          <?php if (in_array($k, $bloq, true)): ?>
            <div class="candado">✎ Editado a mano: la planilla ya no actualiza este campo.</div>
          <?php endif ?>
        <?php endforeach ?>
        <?php if (App::puede('editar')): ?><div class="acciones"><button class="btn btn-primario">Guardar</button></div><?php endif ?>
      </form>
      <?php foreach (array_intersect($bloq, ['nombre', 'resumen']) as $k): ?>
        <form method="post" action="<?= App::url('campo_desbloquear') ?>" class="acciones">
          <?= App::campoToken() ?><input type="hidden" name="entidad" value="caja"><input type="hidden" name="id" value="<?= (int)$caja['id'] ?>"><input type="hidden" name="campo" value="<?= e($k) ?>">
          <button class="link" data-doble="La planilla va a reemplazar este valor. ¿Confirmar?">Volver a tomar «<?= e($k) ?>» del Excel</button>
        </form>
      <?php endforeach ?>
    </section>
    <?php if ($caja['nombre_pestania']): ?>
    <section class="panel">
      <h2>Datos de la planilla</h2>
      <table><tr><td>Pestaña</td><td><?= e($caja['nombre_pestania']) ?></td></tr></table>
    </section>
    <?php endif ?>

    <section class="panel">
      <h2>Historial</h2>
      <?php require __DIR__ . '/_historial.php'; ?>
    </section>
    <?php if (!$caja['eliminado_en'] && App::puede('baja')): ?>
    <section class="panel zona-riesgo">
      <h2>Zona de riesgo</h2>
      <p class="nota">La caja y sus registros dejan de mostrarse en el sitio. Se puede restaurar.</p>
      <a class="btn btn-chico btn-peligro-borde" href="<?= App::url('caja_baja', ['id' => $caja['id']]) ?>">Dar de baja la caja…</a>
    </section>
    <?php endif ?>
  </aside>
</div>
