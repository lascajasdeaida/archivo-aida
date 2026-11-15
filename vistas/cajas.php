<?php use Aida\Web\App; $titulo = 'Cajas'; ?>
<div class="cabecera">
  <div>
    <p class="eyebrow">Archivo Aída Bortnik</p>
    <h1>Cajas</h1>
  </div>
  <div class="acciones">
    <?php if ($parecidos && App::puede('editar')): ?><a class="btn" href="<?= App::url('cajas_unificar') ?>">Unificar nombres parecidos <b class="contador"><?= $parecidos ?></b></a><?php endif ?>
    <a class="btn" href="<?= App::url('cajas', $verBajas ? [] : ['bajas' => 1]) ?>"><?= $verBajas ? 'Ocultar dadas de baja' : 'Ver también las dadas de baja' ?></a>
    <?php if (App::puede('baja')): ?><a class="btn btn-primario" href="<?= App::url('caja_nueva') ?>">+ Nueva caja</a><?php endif ?>
  </div>
</div>

<table>
  <thead><tr>
    <th>Caja</th><th>Nombre</th><th class="num">Registros</th><th class="num">Digitalizados</th><th>Acceso</th><th>Verificaciones</th><th>Estado</th>
  </tr></thead>
  <tbody>
  <?php foreach ($cajas as $c): $url = App::url('caja', ['id' => $c['id']]); ?>
    <tr class="fila-link <?= $c['eliminado_en'] ? 'baja' : '' ?>" onclick="location.href='<?= $url ?>'">
      <td class="num"><a href="<?= $url ?>"><?= (int)$c['numero'] ?></a></td>
      <td><?= e($c['nombre']) ?></td>
      <td class="num"><?= (int)$c['registros'] ?></td>
      <td class="num"><?= (int)$c['digitalizados'] ?><?php if ($c['registros']): ?> <span class="nota">(<?= round($c['digitalizados'] * 100 / $c['registros']) ?>%)</span><?php endif ?></td>
      <td class="no-tachar"><?php if ($c['no_publicos']): ?><span class="chip chip-warn"><?= (int)$c['no_publicos'] ?> no públicos</span><?php endif ?></td>
      <td class="no-tachar"><?php if ($c['alertas']): ?><span class="chip chip-acc">🔔 <?= (int)$c['alertas'] ?></span><?php endif ?></td>
      <td class="no-tachar">
        <?php if ($c['eliminado_en']): ?><span class="chip chip-stop">Dada de baja</span>
        <?php elseif ((int)$c['registros'] > 0 && $c['digitalizados'] == $c['registros']): ?><span class="chip chip-ok">Digitalizada</span>
        <?php elseif ((int)$c['digitalizados'] > 0): ?><span class="chip">Digitalización parcial</span>
        <?php else: ?><span class="chip">Sin archivos</span><?php endif ?>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
