<?php use Aida\Web\App; $titulo = 'Cajas'; ?>
<div class="cabecera">
  <div>
    <p class="eyebrow">Archivo Aída Bortnik</p>
    <h1>Cajas</h1>
  </div>
  <div class="acciones">
    <?php if ($parecidos && App::puede('correccion')): ?><a class="btn" href="<?= App::url('cajas_unificar') ?>">Unificar nombres parecidos <b class="contador"><?= $parecidos ?></b></a><?php endif ?>
    <?php if (App::puede('correccion')): ?><a class="btn" href="<?= App::url('correccion') ?>">Corrección masiva…</a><?php endif ?>
    <a class="btn" href="<?= App::url('cajas', $verBajas ? [] : ['bajas' => 1]) ?>"><?= $verBajas ? 'Ocultar dadas de baja' : 'Ver también las dadas de baja' ?></a>
    <?php if (App::puede('baja')): ?><a class="btn btn-crear" href="<?= App::url('caja_nueva') ?>">+ Nueva caja</a><?php endif ?>
  </div>
</div>

<div class="tabs" role="navigation" aria-label="Publicación en el sitio">
  <?php $base = $verBajas ? ['bajas' => 1] : []; ?>
  <a href="<?= App::url('cajas', $base) ?>" class="<?= $pub === '' ? 'activo' : '' ?>">Todas</a>
  <a href="<?= App::url('cajas', $base + ['pub' => 1]) ?>" class="<?= $pub === '1' ? 'activo' : '' ?>">Publicadas en el sitio (<?= $nPublicadas ?>)</a>
  <a href="<?= App::url('cajas', $base + ['pub' => 0]) ?>" class="<?= $pub === '0' ? 'activo' : '' ?>">Sin publicar (<?= $nSinPublicar ?>)</a>
</div>

<table>
  <thead><tr>
    <th>Caja</th><th>Nombre</th><th class="num">Registros</th><th class="num">Digitalizados</th><th>Acceso</th><th>Verificaciones</th><th>Estado</th><th>Sitio</th>
  </tr></thead>
  <tbody>
  <?php foreach ($cajas as $c): $url = App::url('caja', ['id' => $c['id']]); ?>
    <tr class="fila-link <?= $c['eliminado_en'] ? 'baja' : '' ?>" onclick="location.href='<?= $url ?>'">
      <td class="num"><a href="<?= $url ?>"><?= (int)$c['numero'] ?></a></td>
      <td><a href="<?= $url ?>" style="font-weight:400;color:inherit;text-decoration:none"><?= e($c['nombre']) ?></a></td>
      <td class="num"><?= (int)$c['registros'] ?></td>
      <td class="num"><?= (int)$c['digitalizados'] ?><?php if ($c['registros']): ?> <span class="nota">(<?= round($c['digitalizados'] * 100 / $c['registros']) ?>%)</span><?php endif ?></td>
      <td class="no-tachar"><?php if ($c['no_publicos']): ?><span class="chip chip-warn"><?= (int)$c['no_publicos'] ?> no públicos</span><?php endif ?></td>
      <td class="no-tachar"><?php if ($c['alertas']): ?><span class="chip chip-acc">🔔 <?= (int)$c['alertas'] ?></span><?php endif ?></td>
      <td class="no-tachar">
        <?php if ($c['eliminado_en']): ?><span class="chip chip-stop">Dada de baja</span>
        <?php elseif ((int)$c['registros'] > 0 && $c['digitalizados'] == $c['registros']): ?><span class="chip chip-ok">Digitalizada</span>
        <?php elseif ((int)$c['digitalizados'] > 0): ?><span class="chip chip-warn">Digitalización parcial</span>
        <?php else: ?><span class="chip">Sin digitalizar</span><?php endif ?>
      </td>
      <td class="no-tachar"><?= $c['publicada'] ? '<span class="chip chip-ok">Publicada</span>' : '<span class="chip">Sin publicar</span>' ?></td>
    </tr>
  <?php endforeach ?>
  <?php if (!$cajas): ?><tr><td colspan="8" class="nota"><?= $pub === '1' ? 'Todavía no hay cajas publicadas. Se publican desde la página de cada caja.' : 'No hay cajas con este filtro.' ?></td></tr><?php endif ?>
  </tbody>
</table>
