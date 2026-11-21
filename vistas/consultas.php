<?php
use Aida\Consultas;
use Aida\Web\App;

$titulo = 'Consultas';
?>
<div class="cabecera">
  <div>
    <p class="eyebrow">Sitio público</p>
    <h1>Consultas a pedido</h1>
  </div>
</div>
<p class="nota">Pedidos que llegan desde el formulario “Pedir consulta” del sitio. Los datos personales se ven solo acá.
  Todavía no se manda ningún correo: respondé desde tu casilla y marcá la consulta como respondida.</p>

<div class="tabs" role="navigation" aria-label="Estado">
  <a href="<?= App::url('consultas') ?>" class="<?= $estado === null ? 'activo' : '' ?>">Todas (<?= array_sum($conteos) ?>)</a>
  <?php foreach (Consultas::ESTADOS as $k => $n): ?>
  <a href="<?= App::url('consultas', ['estado' => $k]) ?>" class="<?= $estado === $k ? 'activo' : '' ?>"><?= ['nueva' => 'Nuevas', 'respondida' => 'Respondidas', 'descartada' => 'Descartadas'][$k] ?> (<?= $conteos[$k] ?>)</a>
  <?php endforeach ?>
</div>

<form method="get" class="filtros">
  <input type="hidden" name="r" value="consultas"><?php if ($estado): ?><input type="hidden" name="estado" value="<?= e($estado) ?>"><?php endif ?>
  <label>Buscar<input type="search" name="q" value="<?= e($q) ?>" placeholder="nombre, correo, institución o C#R#"></label>
  <button class="btn">Filtrar</button>
</form>

<table>
  <thead><tr><th>N.º</th><th>Recibida</th><th>Documento</th><th>Quién</th><th>Uso previsto</th><th>Estado</th></tr></thead>
  <tbody>
  <?php foreach ($consultas as $c): $url = App::url('consulta', ['id' => $c['id']]); ?>
    <tr class="fila-link" onclick="location.href='<?= $url ?>'">
      <td><a href="<?= $url ?>"><?= e(Consultas::numero($c)) ?></a></td>
      <td><?= fecha($c['creado_en']) ?></td>
      <td><b class="codigo"><?= e($c['codigo']) ?></b><?php if ($c['titulo']): ?><br><span class="nota"><?= e(mb_strimwidth($c['titulo'], 0, 60, '…')) ?></span><?php endif ?>
        <?php if ($c['tipo'] === 'original'): ?><br><span class="chip">Consulta por el documento</span><?php endif ?></td>
      <td><?= e($c['nombre']) ?><br><span class="nota"><?= e($c['correo']) ?><?= $c['institucion'] ? ' · ' . e($c['institucion']) : '' ?></span></td>
      <td><?= e(Consultas::USOS[$c['uso']] ?? $c['uso']) ?><br><span class="nota"><?= e(Consultas::MODALIDADES[$c['modalidad']] ?? '') ?></span></td>
      <td><span class="chip <?= ['nueva' => 'chip-acc', 'respondida' => 'chip-ok', 'descartada' => ''][$c['estado']] ?>"><?= e(Consultas::ESTADOS[$c['estado']]) ?></span></td>
    </tr>
  <?php endforeach ?>
  <?php if (!$consultas): ?><tr><td colspan="6" class="nota"><?= $estado === 'nueva' ? 'No hay consultas nuevas.' : 'No hay consultas con este filtro.' ?></td></tr><?php endif ?>
  </tbody>
</table>
