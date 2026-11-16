<?php
// Parcial: lista de cambios con botón Deshacer. Recibe $historial y $volver.
use Aida\Web\App;

$etiquetas = \Aida\Web\Rutas::CAMPOS_REGISTRO + [
    'acceso' => 'Acceso', 'publicado' => 'Publicado', 'publicada' => 'Publicada', 'nombre' => 'Nombre', 'tipos' => 'Tipos',
    'descriptores' => 'Descriptores', 'eliminado_en' => 'Dado de baja', 'eliminado_por' => 'Baja por',
    'campos_bloqueados' => 'Campos editados a mano', 'origen' => 'Origen', 'anio' => 'Año (filtro)',
    'excel_digitalizado' => 'Digitalizado según Excel', 'excel_nomenclatura' => 'Nomenclatura en Excel',
    'excel_privado' => 'PRIVADO en Excel', 'codigo' => 'Código', 'numero' => 'Número', 'caja_id' => 'Caja',
    'anio_hasta' => 'Año hasta (filtro)', 'anio_sin_confirmar' => 'Año sin confirmar',
    'estado' => 'Estado', 'notas' => 'Notas internas', 'atendida_por' => 'Atendida por', 'atendida_en' => 'Atendida el',
    'uso' => 'Uso previsto', 'modalidad' => 'Modalidad', 'tipo' => 'Tipo',
];
$acciones = ['alta' => 'Alta', 'modificacion' => 'Modificación', 'baja' => 'Baja', 'restauracion' => 'Restauración', 'reversion' => 'Deshacer'];
$origenes = ['backoffice' => 'backoffice', 'importacion' => 'importación del Excel', 'escaneo' => 'escaneo', 'reversion' => 'deshacer', 'sitio' => 'sitio público'];
$mostrar = function ($v, string $campo = '') {
    if ($campo === 'acceso' && is_string($v) && $v !== '') return acceso_txt($v);
    if (in_array($campo, ['publicado', 'publicada', 'excel_digitalizado', 'anio_sin_confirmar'], true) && ($v === 0 || $v === 1 || $v === '0' || $v === '1' || is_bool($v))) return $v ? 'Sí' : 'No';
    if ($v === null || $v === '' || $v === []) return '—';
    if (is_array($v)) return implode(', ', $v);
    $s = (string)$v;
    return mb_strlen($s) > 140 ? mb_substr($s, 0, 140) . '…' : $s;
};
?>
<?php if (!$historial): ?>
  <p class="nota">Sin cambios registrados.</p>
<?php else: ?>
<ul class="historial">
<?php foreach ($historial as $a):
    $propio = $a['usuario_id'] !== null && (int)$a['usuario_id'] === App::usuarioId();
    $puede = App::puede($propio ? 'deshacer_propio' : 'deshacer_todo');
    $campos = $a['cambios'];
    if ($a['accion'] === 'alta') { $campos = array_intersect_key($campos, array_flip(['titulo', 'nombre', 'numero', 'codigo', 'acceso', 'uso', 'estado'])); }
?>
  <li class="<?= $a['revertido_en'] ? 'deshecho' : '' ?>">
    <div class="meta">
      <b><?= e($acciones[$a['accion']] ?? $a['accion']) ?></b>
      <span>#<?= (int)$a['id'] ?></span>
      <span><?= fecha($a['creado_en']) ?></span>
      <span>· <?= e($a['usuario_nombre'] ?: $a['usuario_email'] ?: ($origenes[$a['origen']] ?? $a['origen'])) ?></span>
      <?php $curaduria = ($a['proceso_tipo'] ?? '') === 'curaduria'; ?>
      <?php if ($curaduria): ?><span>· <a href="<?= App::url('vocab_informe') ?>">curaduría #<?= (int)$a['proceso_id'] ?></a>: <?= e($a['proceso_fuente']) ?></span>
      <?php elseif (($a['proceso_tipo'] ?? '') === 'correccion'): ?><span>· <a href="<?= App::url('procesos') ?>">corrección masiva #<?= (int)$a['proceso_id'] ?></a>: <?= e($a['proceso_fuente']) ?></span>
      <?php elseif ($a['proceso_id']): ?><span>· proceso #<?= (int)$a['proceso_id'] ?></span><?php endif ?>
      <?php if ($a['revierte_id']): ?><span class="chip">deshace #<?= (int)$a['revierte_id'] ?></span><?php endif ?>
      <?php if ($a['revertido_en']): ?><span class="chip chip-stop">deshecho <?= fecha($a['revertido_en']) ?></span>
      <?php elseif ($curaduria): ?>
        <a class="link" href="<?= App::url('vocab_informe') ?>" title="Un cambio de curaduría se deshace completo, para todos sus registros">Se deshace desde Cambios de curaduría</a>
      <?php elseif ($a['accion'] === 'alta' && $a['entidad'] === 'consulta'): // un pedido recibido no se deshace: se descarta ?>
      <?php elseif ($a['accion'] === 'alta' && in_array($a['entidad'], ['registro', 'caja'], true)):
          $ent = $a['entidad'] === 'registro' ? ($r ?? null) : ($caja ?? null);
          if ($puede && App::puede('baja') && $ent && !$ent['eliminado_en']): ?>
        <a class="btn btn-chico btn-peligro-borde" href="<?= App::url($a['entidad'] . '_baja', ['id' => $a['entidad_id']]) ?>" title="Deshacer el alta lo da de baja">Deshacer…</a>
      <?php endif ?>
      <?php elseif ($puede): ?>
        <form method="post" action="<?= App::url('deshacer') ?>">
          <?= App::campoToken() ?>
          <input type="hidden" name="cambio" value="<?= (int)$a['id'] ?>">
          <input type="hidden" name="volver" value="<?= e($volver) ?>">
          <button class="btn btn-chico btn-peligro-borde" data-doble="<?= $a['accion'] === 'alta' ? 'Se quita lo agregado.' : 'Vuelve al valor anterior.' ?>">Deshacer</button>
        </form>
      <?php endif ?>
    </div>
    <?php if ($campos): ?>
    <table>
      <?php foreach ($campos as $campo => [$antes, $despues]): ?>
        <tr>
          <td style="width:28%"><?= e($etiquetas[$campo] ?? $campo) ?></td>
          <td><?php if ($a['accion'] !== 'alta'): ?><span class="antes"><?= e($mostrar($antes, $campo)) ?></span> → <?php endif ?><span class="despues"><?= e($mostrar($despues, $campo)) ?></span></td>
        </tr>
      <?php endforeach ?>
    </table>
    <?php endif ?>
  </li>
<?php endforeach ?>
</ul>
<?php endif ?>
