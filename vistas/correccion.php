<?php use Aida\Web\App; $titulo = 'Corrección masiva';
$mostrar = function ($v, ?string $campo) {
    if ($v === null || $v === '') return '—';
    if ($campo === 'acceso') return acceso_txt((string)$v);
    $s = (string)$v;
    return mb_strlen($s) > 120 ? mb_substr($s, 0, 120) . '…' : $s;
};
$grupos = [
    'aplicar'    => ['Se aplican', 'chip-acc', true, 'se aplican'],
    'conflicto'  => ['Conflictos: no se aplican', 'chip-stop', true, 'con conflicto'],
    'error'      => ['Con error: no se aplican', 'chip-stop', true, 'con error'],
    'saltear'    => ['Se saltean («Revisar» o sin valor nuevo)', 'chip-warn', false, 'se saltean'],
    'sin_cambio' => ['Ya estaban así', 'chip-ok', false, 'ya estaban así'],
];
?>
<div class="migas"><a href="<?= App::url('procesos') ?>">Importaciones</a> / Corrección masiva</div>
<div class="cabecera">
  <div>
    <p class="eyebrow">Correcciones por planilla</p>
    <h1>Corrección masiva</h1>
    <p class="nota">Subís una planilla con las celdas a corregir, ves qué va a cambiar y recién después lo aplicás.
      Los campos corregidos quedan como «editados a mano»: la importación del Excel no los pisa hasta que el equipo los corrija también en su planilla.</p>
  </div>
</div>

<?php if ($sim && $plan): $c = $plan['cuentas']; ?>
  <section class="panel">
    <h2>Vista previa · nada se guardó todavía</h2>
    <p><b><?= e($sim['original']) ?></b> · <?= count($plan['filas']) ?> filas · subida el <?= date('d/m/Y H:i', (int)$sim['en']) ?></p>
    <div class="chips" style="margin-bottom:12px">
      <?php foreach ($grupos as $k => [$tit, $chip, , $corto]): ?><span class="chip <?= $c[$k] ? $chip : '' ?>"><b><?= (int)$c[$k] ?></b> <?= e($corto) ?></span><?php endforeach ?>
    </div>
    <?php if ($c['conflicto']): ?>
      <div class="leyenda leyenda-stop"><div><b><?= $c['conflicto'] === 1 ? 'Hay 1 conflicto.' : 'Hay ' . (int)$c['conflicto'] . ' conflictos.' ?></b> El archivo esperaba un valor actual y en la base hoy hay otro: alguien ya lo cambió.
        Esas filas no se aplican; revisalas en la ficha.</div></div>
    <?php endif ?>

    <?php foreach ($grupos as $k => [$tit, $chip, $abierto]): $filas = array_filter($plan['filas'], fn($p) => $p['estado'] === $k); if (!$filas) continue; ?>
      <details class="leyenda <?= $k === 'conflicto' || $k === 'error' ? 'leyenda-stop' : ($k === 'saltear' ? 'leyenda-warn' : '') ?>" style="display:block" <?= $abierto ? 'open' : '' ?>>
        <summary><b><?= e($tit) ?></b> · <?= count($filas) ?></summary>
        <div style="max-height:480px;overflow:auto;margin-top:8px">
        <table class="tabla-chica">
          <thead><tr><th class="num">Fila</th><th>Registro</th><th>Columna</th><th>En la base</th><th>Valor nuevo</th>
            <?php if ($k === 'conflicto'): ?><th>El archivo esperaba</th><?php endif ?><th>Nota</th></tr></thead>
          <tbody>
          <?php foreach ($filas as $p): ?>
            <tr>
              <td class="num"><?= (int)$p['fila'] ?></td>
              <td><?php if ($p['url']): ?><a href="<?= App::url($p['url'][0], ['id' => $p['url'][1]]) ?>" target="_blank"><?= e($p['destino']) ?></a><?php else: ?><?= e($p['destino']) ?><?php endif ?></td>
              <td><?= e($p['etiqueta']) ?></td>
              <td><span class="antes"><?= e($mostrar($p['actual'], $p['campo'])) ?></span></td>
              <td><span class="despues"><?= e($mostrar($p['nuevo'] ?? $p['valor_nuevo'], $p['campo'])) ?></span></td>
              <?php if ($k === 'conflicto'): ?><td><?= e($mostrar($p['valor_actual'], null)) ?></td><?php endif ?>
              <td class="nota"><?= e($p['nota']) ?></td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
        </div>
      </details>
    <?php endforeach ?>

    <div class="acciones acciones-guardar">
      <?php if ($c['aplicar']): ?>
        <form method="post" action="<?= App::url('correccion_aplicar') ?>">
          <?= App::campoToken() ?>
          <input type="hidden" name="vista" value="<?= e($sim['token']) ?>">
          <button class="btn btn-primario" data-doble="Se corrige<?= $c['aplicar'] === 1 ? ' 1 celda' : 'n ' . (int)$c['aplicar'] . ' celdas' ?> en la base y quedan bloqueadas para el Excel. Se puede deshacer completa desde Importaciones. ¿Confirmar?"><?= $c['aplicar'] === 1 ? 'Aplicar 1 corrección' : 'Aplicar ' . (int)$c['aplicar'] . ' correcciones' ?></button>
        </form>
      <?php endif ?>
      <form method="post" action="<?= App::url('correccion_descartar') ?>">
        <?= App::campoToken() ?>
        <button class="btn">Descartar</button>
      </form>
      <span class="nota"><?= $c['aplicar'] ? 'Solo se aplican las filas de «Se aplican». La vista previa vale 2 horas.' : 'No hay nada para aplicar.' ?></span>
    </div>
  </section>
<?php else: ?>
  <?php if ($hecha): ?>
    <section class="panel">
      <h2>Corrección #<?= (int)$hecha['proceso'] ?> aplicada</h2>
      <p><b><?= e($hecha['original']) ?></b> · <?= (int)$hecha['aplicadas'] ?> celdas corregidas en <?= (int)$hecha['registros'] ?> registros
        <?= $hecha['cajas'] ? 'y ' . (int)$hecha['cajas'] . ' cajas' : '' ?>.
        <?= $hecha['cuentas']['saltear'] ? (int)$hecha['cuentas']['saltear'] . ' salteadas.' : '' ?>
        <?= $hecha['cuentas']['conflicto'] ? (int)$hecha['cuentas']['conflicto'] . ' con conflicto, no se aplicaron.' : '' ?></p>
      <p class="nota">Cuando el equipo corrija lo mismo en su planilla, la importación lo detecta, desbloquea esos campos y lo informa
        («Corrección hecha también en la planilla»).</p>
      <div class="acciones"><a class="btn" href="<?= App::url('procesos') ?>">Ver en Importaciones (para deshacerla)</a></div>
    </section>
  <?php endif ?>

  <section class="panel">
    <h2>Subir la planilla de correcciones</h2>
    <form method="post" action="<?= App::url('correccion') ?>" enctype="multipart/form-data" id="form-corr">
      <?= App::campoToken() ?>
      <label>Archivo (.csv o .xlsx, hasta <?= (int)$maxMb ?> MB)
        <input type="file" name="planilla" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
      </label>
      <div class="acciones">
        <button class="btn btn-primario">Ver vista previa</button>
        <span class="nota" aria-live="polite" id="corr-subiendo" hidden>Leyendo la planilla…</span>
      </div>
    </form>
  </section>

  <section class="panel">
    <h2>Cómo armar la planilla</h2>
    <p>Una fila por celda a corregir, con estos encabezados en la primera fila (sirve la lista
      <code>correcciones-planilla.csv</code> que se le pasó al equipo tal cual):</p>
    <table class="tabla-chica">
      <tr><td><b>registro</b></td><td>El código del registro (<code>C14R3</code>), o <code>Caja 5 (todas las filas)</code> para un dato de la caja.</td></tr>
      <tr><td><b>columna</b></td><td>El nombre de la columna del Excel: Autor, Título, Lugar, Fecha como dice el documento, Año, Páginas, Resumen, ISBN,
        Observaciones/Editorial, Edición de catálogo, Observaciones de conservación, Observaciones de digitalización, Selección para digitalización,
        Acceso (o PRIVADO), Nombre de caja (columna B), Resumen de la caja.</td></tr>
      <tr><td><b>valor_nuevo</b></td><td>El valor corregido (también vale <code>correccion_sugerida</code>). Vacío o «—» se saltea.</td></tr>
      <tr><td>valor_actual</td><td>Opcional. Si no coincide con la base (sin contar espacios ni mayúsculas), es un conflicto y no se aplica. Vacío: no se controla.</td></tr>
      <tr><td>accion</td><td>Opcional. «Revisar» se saltea y se lista aparte.</td></tr>
    </table>
    <p class="nota"><b>Cambio masivo de acceso:</b> columna <code>Acceso</code> con Público, Restringido o A revisar
      (también VERDADERO = restringido, FALSO = público, VERLO = a revisar). <b>Año:</b> se recalcula el año del filtro con las mismas reglas que la importación.
      Tipos y descriptores no se corrigen por acá: se curan desde Vocabularios.</p>
  </section>
<?php endif ?>
<script>
(function () {
  var f = document.getElementById('form-corr');
  if (!f) return;
  f.addEventListener('submit', function () { f.querySelector('button').disabled = true; document.getElementById('corr-subiendo').hidden = false; });
})();
</script>
