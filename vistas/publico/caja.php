<?php
/**
 * Página de una caja en el sitio público (docs/ux/sitio-publico.md §3 “Caja”, componentes.md §3, §5, §6, §8, §14).
 * Recibe $d (Aida\Sitio::cajaPor…), $enl (Aida\Web\Enlaces) y, opcionales, $antes y $robots para _sitio.php.
 */
use Aida\Web\Html as H;

$c = $d['caja'];
$n = (int)$c['numero'];
$cf = $d['cifras'];
$caja = ['id' => (int)$c['id'], 'numero' => $n, 'nombre' => $c['nombre']];
$tituloPagina = "Caja $n: {$c['nombre']}";
$seccion = 'cajas';
$resumen = trim((string)$c['resumen']);
$vecina = fn(?array $o) => $o ? ['href' => $enl->caja($o), 'corto' => 'Caja ' . (int)$o['numero'], 'largo' => (string)$o['nombre']] : null;
$paso = ['etiqueta' => 'Otras cajas', 'ant' => $vecina($d['anterior']), 'sig' => $vecina($d['siguiente'])];
ob_start(); ?>
<div class="contenedor">
  <div class="barra-registro">
    <nav class="migas" aria-label="Estás en"><ol>
      <li><a href="<?= H::e($enl->inicio()) ?>">Inicio</a></li>
      <li><a href="<?= H::e($enl->cajas()) ?>">Las 70 cajas</a></li>
      <li aria-current="page">Caja <?= $n ?></li>
    </ol></nav>
    <?php require __DIR__ . '/_paso_registro.php'; ?>
  </div>

  <header class="cabeza-registro cabeza-caja">
    <?php $mc = $caja + ['desde' => $cf['desde'], 'hasta' => $cf['hasta']]; $c2 = $c; $c = $mc; $variante = 'encabezado'; $etiqueta = 'div';
      require __DIR__ . '/_modulo_caja.php'; $c = $c2; unset($variante, $etiqueta); ?>
    <div class="cabeza-caja__texto">
    <p class="cabeza-registro__codigo">Caja <?= $n ?><?= $d['total_cajas'] ? ' de ' . (int)$d['total_cajas'] : '' ?></p>
    <h1><?= H::e($c['nombre']) ?></h1>
    <?php if (!empty($equipo)): ?><p class="barra-equipo"><a class="boton boton--fantasma" href="<?= H::e($equipo['admin'] . '?r=caja&id=' . (int)$c['id']) ?>">Editar en el backoffice</a> <span class="meta">Lo ves porque tenés abierta la sesión del backoffice (<?= H::e($equipo['nombre']) ?>).</span></p><?php endif ?>
    <p class="datos-caja">
      <span><b><?= H::numero($cf['documentos']) ?></b> documentos</span>
      <span><b><?= H::numero($cf['digitales']) ?></b> digitalizados</span>
      <?php if ($cf['a_pedido']): ?><span><b><?= H::numero($cf['a_pedido']) ?></b> a pedido</span><?php endif ?>
      <span><?= $cf['desde'] ? '<b>' . $cf['desde'] . ($cf['hasta'] !== $cf['desde'] ? '–' . $cf['hasta'] : '') . '</b>' : 'Sin años cargados' ?></span>
    </p>
    <?php if ($cf['documentos']): ?>
    <div class="avance avance--caja">
      <div class="avance__barra" role="img" aria-label="<?= $cf['avance'] ?> % digitalizado"><i style="width:<?= $cf['avance'] ?>%"></i></div>
      <span><?= $cf['avance'] ?> % digitalizado</span>
    </div>
    <?php endif ?>
    </div>
  </header>

  <?php if ($resumen !== ''): ?>
  <div class="texto-plegable plegado" data-plegable>
    <div class="cuerpo" id="cuerpo-resumen">
      <?php foreach (preg_split('/\R{2,}/u', $resumen) as $parrafo): ?><p><?= nl2br(H::e(trim($parrafo))) ?></p><?php endforeach ?>
    </div>
    <button type="button" class="boton boton--fantasma" aria-expanded="false" aria-controls="cuerpo-resumen" data-leer-mas hidden>Leer más</button>
  </div>
  <?php endif ?>

  <section class="bloque" aria-labelledby="h-docs">
    <div class="bloque__cabeza"><h2 id="h-docs">Documentos de la caja</h2><span class="meta">En el orden en que están en la caja</span></div>
    <form class="buscador" action="<?= H::e($enl->explorar()) ?>" role="search" aria-label="Buscar en esta caja">
      <?php if (!$enl->reescritura): ?><input type="hidden" name="ruta" value="explorar"><?php endif ?>
      <input type="hidden" name="caja" value="<?= $n ?>">
      <div class="buscador__campo"><label class="solo-lector" for="q-caja">Buscar en la caja <?= $n ?></label>
        <input type="search" id="q-caja" name="q" placeholder="Buscar en esta caja" aria-keyshortcuts="/"><span class="buscador__cursor" aria-hidden="true"></span></div>
      <button class="boton boton--primario"><?= H::icono('buscar') ?>Buscar</button>
    </form>
    <?php if ($d['tipos']): ?>
    <ul class="accesos" aria-label="Ver esta caja por tipo de documento">
      <?php foreach ($d['tipos'] as $tp): ?>
        <li><a class="acceso" href="<?= H::e($enl->explorar(['caja' => $n, 'tipo' => $tp['slug']])) ?>"><?= H::e($tp['nombre']) ?> <span class="n"><?= (int)$tp['cantidad'] ?></span></a></li>
      <?php endforeach ?>
      <li><a class="acceso acceso--todos" href="<?= H::e($enl->explorar(['caja' => $n])) ?>">Todos los filtros</a></li>
    </ul>
    <?php endif ?>
    <?php if ($d['registros']): ?>
    <div class="columnas columnas--caja" aria-hidden="true"><span>Código</span><span>Documento</span><span>Año</span><span>Acceso</span></div>
    <ol class="lista-registros lista-registros--caja" aria-label="Documentos de la caja <?= $n ?>">
      <?php foreach ($d['registros'] as $t) { require __DIR__ . '/_tarjeta.php'; } ?>
    </ol>
    <?php else: ?>
    <div class="estado"><h2>Esta caja todavía no tiene documentos catalogados</h2></div>
    <?php endif ?>
  </section>

  <div class="pie-registro"><?php $paso['etiqueta'] .= ' (al pie)'; require __DIR__ . '/_paso_registro.php'; ?></div>
</div>
<?php $contenido = ob_get_clean();
require __DIR__ . '/_sitio.php';
