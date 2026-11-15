<?php use Aida\Web\App;
$titulo = "Caja {$caja['numero']}";
$volver = App::url('caja', ['id' => $caja['id']]);
$bloq = json_decode((string)$caja['campos_bloqueados'], true) ?: [];
?>
<div class="migas"><a href="<?= App::url('cajas') ?>">Cajas</a> / Caja <?= (int)$caja['numero'] ?></div>
<div class="cabecera">
  <div>
    <p class="eyebrow">Caja <?= (int)$caja['numero'] ?><?= $caja['nombre_pestania'] ? ' · pestaña «' . e($caja['nombre_pestania']) . '»' : '' ?></p>
    <h1><?= e($caja['nombre']) ?></h1>
  </div>
  <div class="acciones">
    <?php if (!$caja['eliminado_en'] && App::puede('editar')): ?><a class="btn btn-primario" href="<?= App::url('registro_nuevo', ['caja' => $caja['id']]) ?>">+ Nuevo registro</a><?php endif ?>
  </div>
</div>

<?php if ($caja['eliminado_en']): ?>
  <div class="leyenda leyenda-stop">
    <div><b>Dada de baja el <?= fecha($caja['eliminado_en']) ?>.</b> No se muestra en el sitio y la importación del Excel no la vuelve a crear.</div>
    <?php if (App::puede('baja')): ?>
    <form method="post" action="<?= App::url('caja_restaurar', ['id' => $caja['id']]) ?>"><?= App::campoToken() ?><button class="btn btn-chico">Restaurar</button></form>
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
        <option value="pendiente" <?= $filtros['estado'] === 'pendiente' ? 'selected' : '' ?>>Sin archivo</option>
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
          <td><?= e($r0['anio_texto']) ?></td>
          <td class="no-tachar"><div class="chips">
            <?php if ($r0['eliminado_en']): ?><span class="chip chip-stop">Baja</span><?php endif ?>
            <?= $r0['digitalizado'] ? '<span class="chip chip-ok">Digitalizado</span>' : ($r0['excel_digitalizado'] ? '<span class="chip">Archivo pendiente</span>' : '<span class="chip">Sin digitalizar</span>') ?>
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
        <label class="check"><input type="checkbox" name="publicada" value="1" <?= $caja['publicada'] ? 'checked' : '' ?>> Publicada en el sitio</label>
        <?php if (App::puede('editar')): ?><button class="btn btn-primario">Guardar</button><?php endif ?>
      </form>
      <?php foreach (array_intersect($bloq, ['nombre', 'resumen']) as $k): ?>
        <form method="post" action="<?= App::url('campo_desbloquear') ?>" class="acciones">
          <?= App::campoToken() ?><input type="hidden" name="entidad" value="caja"><input type="hidden" name="id" value="<?= (int)$caja['id'] ?>"><input type="hidden" name="campo" value="<?= e($k) ?>">
          <button class="link" onclick="return confirm('En la próxima importación, «<?= e($k) ?>» se reemplaza por lo que diga la planilla. ¿Seguir?')">Volver a tomar «<?= e($k) ?>» del Excel</button>
        </form>
      <?php endforeach ?>
      <?php if (!$caja['eliminado_en'] && App::puede('baja')): ?>
        <p class="acciones"><a class="btn btn-chico" href="<?= App::url('caja_baja', ['id' => $caja['id']]) ?>">Dar de baja la caja…</a></p>
      <?php endif ?>
    </section>

    <section class="panel">
      <h2>Historial</h2>
      <?php require __DIR__ . '/_historial.php'; ?>
    </section>
  </aside>
</div>
