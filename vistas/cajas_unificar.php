<?php use Aida\Web\App; $titulo = 'Unificar nombres de cajas'; ?>
<div class="migas"><a href="<?= App::url('cajas') ?>">Cajas</a> / Unificar nombres</div>
<div class="cabecera">
  <div>
    <p class="eyebrow">Curaduría</p>
    <h1>Unificar nombres de cajas</h1>
    <p class="nota">Grupos de cajas con nombres casi iguales: diferencias de tildes, mayúsculas, comillas o errores de tipeo.
      Tildá las cajas que van con el mismo nombre, corregí cómo tiene que quedar escrito y unificá.
      Cada cambio queda en el historial de la caja (se puede deshacer) y la planilla ya no lo pisa.</p>
  </div>
</div>
<?php if (!$grupos): ?>
  <section class="panel"><p>✓ No hay nombres parecidos para unificar.</p></section>
<?php endif ?>
<?php foreach ($grupos as $i => $g): ?>
  <form method="post" action="<?= App::url('cajas_unificar') ?>" class="panel">
    <?= App::campoToken() ?>
    <table>
      <thead><tr><th style="width:40px"></th><th style="width:80px">Caja</th><th>Nombre actual</th></tr></thead>
      <?php foreach ($g['cajas'] as $c): ?>
        <tr>
          <td><input type="checkbox" name="cajas[]" value="<?= (int)$c['id'] ?>" checked aria-label="Incluir caja <?= (int)$c['numero'] ?>"></td>
          <td><a href="<?= App::url('caja', ['id' => $c['id']]) ?>"><?= (int)$c['numero'] ?></a></td>
          <td><?= e($c['nombre']) ?>
            <button type="button" class="link" style="margin-left:8px;font-size:.8rem"
              onclick="this.form.nombre.value=<?= e(json_encode(trim(preg_replace(['/[\"“”«»]/u', '/\s+/u'], ['', ' '], $c['nombre'])), JSON_UNESCAPED_UNICODE)) ?>">usar este</button></td>
        </tr>
      <?php endforeach ?>
    </table>
    <div class="acciones">
      <label style="flex:1;margin:0">Nombre unificado
        <input type="text" name="nombre" value="<?= e($g['sugerido']) ?>" required>
      </label>
      <button class="btn btn-primario" style="align-self:flex-end">Unificar las tildadas</button>
    </div>
  </form>
<?php endforeach ?>
