<?php
/**
 * Las cajas (docs/ux/sitio-publico.md §3, maqueta cajas.html): grilla de las cajas publicadas, con filtro por nombre
 * o número y orden. Funciona sin JS (formulario GET); con JS el filtro se aplica mientras se escribe.
 * Recibe $cajas, $total (publicadas), $totalArchivo, $q, $orden, $enl.
 */
use Aida\Web\Html as H;

$tituloPagina = 'Las 70 cajas';
ob_start(); ?>
<div class="contenedor">
  <nav class="migas" aria-label="Estás en"><ol><li><a href="<?= H::e($enl->inicio()) ?>">Inicio</a></li><li aria-current="page">Las 70 cajas</li></ol></nav>
  <h1>Las 70 cajas</h1>
  <p>El número de caja es el del depósito. Varias obras ocupan más de una caja.</p>
  <?php if ($total < $totalArchivo): ?>
  <p class="meta">El archivo se publica caja por caja: hoy <?= $total === 1 ? 'está publicada 1 caja' : 'están publicadas ' . H::numero($total) . ' cajas' ?> de <?= H::numero($totalArchivo) ?>.</p>
  <?php endif ?>
  <?php if ($total): ?>
  <form class="filtro-cajas" method="get" action="<?= H::e($enl->cajas()) ?>" data-filtro-cajas>
    <?php if (!$enl->reescritura): ?><input type="hidden" name="ruta" value="cajas"><?php endif ?>
    <div class="campo filtro-cajas__q"><label for="filtro-cajas">Filtrar por nombre o número</label>
      <input id="filtro-cajas" name="q" type="search" aria-keyshortcuts="/" value="<?= H::e($q) ?>" autocomplete="off"></div>
    <div class="campo"><label for="orden-cajas">Ordenar</label>
      <select id="orden-cajas" name="orden">
        <?php foreach (['numero' => 'Por número', 'nombre' => 'Por nombre (A–Z)', 'digit' => 'Más digitalizadas primero'] as $v => $n): ?>
        <option value="<?= $v ?>"<?= $orden === $v ? ' selected' : '' ?>><?= $n ?></option>
        <?php endforeach ?>
      </select></div>
    <button class="boton boton--secundario solo-sin-js">Aplicar</button>
  </form>
  <p class="conteo" id="conteo-cajas" role="status"><?= count($cajas) === $total ? H::numero($total) . ($total === 1 ? ' caja' : ' cajas') : H::numero(count($cajas)) . ' de ' . H::numero($total) . ' cajas' ?></p>
  <?php if ($cajas): ?>
  <div class="columnas columnas--cajas" aria-hidden="true"><span>Caja</span><span>Nombre</span><span>Documentos</span><span>Años</span><span>Digitalización</span></div>
  <ul class="grilla-cajas" id="lista-cajas">
    <?php $nivelCaja = 2; foreach ($cajas as $c) { require __DIR__ . '/_tarjeta_caja.php'; } ?>
  </ul>
  <?php else: ?>
  <div class="estado"><h2>Ninguna caja coincide con «<?= H::e($q) ?>»</h2><p><a href="<?= H::e($enl->cajas()) ?>">Ver todas las cajas</a> o <a href="<?= H::e($enl->explorar(['q' => $q])) ?>">buscar «<?= H::e($q) ?>» en los documentos</a>.</p></div>
  <?php endif ?>
  <?php else: ?>
  <div class="estado"><h2>Todavía no hay cajas publicadas</h2>
    <p>El archivo se publica caja por caja, a medida que se revisa su catálogo.</p></div>
  <?php endif ?>
</div>
<?php $contenido = ob_get_clean();
require __DIR__ . '/_sitio.php';
