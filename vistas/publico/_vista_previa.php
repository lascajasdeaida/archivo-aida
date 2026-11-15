<?php
/**
 * Banner de la vista previa del backoffice (Rutas::vista_previa). No forma parte del sitio: va arriba de la
 * cabecera y solo existe en la vista previa. Estilos en public/sitio/vista-previa.css.
 * Recibe $motivos (por qué hoy no aparece en el sitio), $volver (URL del backoffice), $que («Este registro»)
 * y $ocultos (caja: registros que no entran en la lista, por motivo).
 */
use Aida\Web\Html as H;

$ocultos = array_filter($ocultos ?? []);
$nombres = ['a_revisar' => ['con acceso «a revisar»', 'con acceso «a revisar»'], 'no_publicados' => ['no publicado', 'no publicados'],
            'bajas' => ['dado de baja', 'dados de baja']];
?>
<div class="vp-barra" role="region" aria-label="Vista previa">
  <p><b>Vista previa</b> <span class="vp-barra__guion">—</span> así se vería en el sitio</p>
  <a class="vp-barra__volver" href="<?= H::e($volver) ?>">Volver al backoffice</a>
</div>
<div class="vp-avisos" role="region" aria-label="Avisos de la vista previa">
  <?php if ($motivos): ?>
    <div class="vp-aviso vp-aviso--no">
      <p><b><?= H::e($que) ?> hoy no aparece en el sitio.</b></p>
      <ul><?php foreach ($motivos as $m): ?><li><?= H::e($m) ?></li><?php endforeach ?></ul>
      <p>La vista muestra cómo se vería una vez resuelto.</p>
    </div>
  <?php else: ?>
    <div class="vp-aviso vp-aviso--si"><p><?= H::e($que) ?> aparece en el sitio: así se ve hoy.</p></div>
  <?php endif ?>
  <?php if ($ocultos): $n = array_sum($ocultos); ?>
    <div class="vp-aviso"><p><b><?= $n ?> <?= $n === 1 ? 'registro de la caja no entra' : 'registros de la caja no entran' ?> en la lista</b>
      (<?= H::e(implode(', ', array_map(fn($k, $v) => $v . ' ' . $nombres[$k][$v === 1 ? 0 : 1], array_keys($ocultos), $ocultos))) ?>).
      El sitio no los muestra.</p></div>
  <?php endif ?>
  <p class="vp-estado" role="status" aria-live="polite" data-vp-estado></p>
</div>
