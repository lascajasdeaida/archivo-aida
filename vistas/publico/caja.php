<?php
/**
 * Página de una caja en el sitio público (docs/ux/sitio-publico.md §3 «Caja», componentes.md §3, §5, §6, §8, §14).
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
ob_start(); ?>
<div class="contenedor">
  <nav class="migas" aria-label="Estás en"><ol>
    <li><a href="<?= H::e($enl->inicio()) ?>">Inicio</a></li>
    <li><a href="<?= H::e($enl->cajas()) ?>">Las 70 cajas</a></li>
    <li aria-current="page">Caja <?= $n ?></li>
  </ol></nav>

  <div class="cabeza-pagina">
    <p class="kicker">Caja <?= $n ?><?= $d['total_cajas'] ? ' de ' . (int)$d['total_cajas'] : '' ?></p>
    <h1><?= H::e($c['nombre']) ?></h1>
    <ul class="datos-caja">
      <li><b><?= H::numero($cf['documentos']) ?></b> documentos</li>
      <li><b><?= H::numero($cf['digitales']) ?></b> con documento digital</li>
      <?php if ($cf['a_pedido']): ?><li><b><?= H::numero($cf['a_pedido']) ?></b> de consulta a pedido</li><?php endif ?>
      <li><?= $cf['desde'] ? '<b>' . $cf['desde'] . ($cf['hasta'] !== $cf['desde'] ? '–' . $cf['hasta'] : '') . '</b>' : 'Sin años cargados' ?></li>
      <?php if ($cf['sin_titulo']): ?><li><b><?= H::numero($cf['sin_titulo']) ?></b> sin título</li><?php endif ?>
    </ul>
    <?php if ($cf['documentos']): ?>
    <div class="avance avance--caja">
      <div class="avance__barra" role="img" aria-label="<?= $cf['avance'] ?> % digitalizado"><i style="width:<?= $cf['avance'] ?>%"></i></div>
      <span><?= $cf['digitales'] ? H::numero($cf['digitales']) . ' de ' . H::numero($cf['documentos']) . ' digitalizados' : 'Sin digitalizar todavía' ?></span>
    </div>
    <?php endif ?>
    <?php if ($resumen !== ''): ?>
    <div class="texto-plegable plegado" data-plegable>
      <div class="cuerpo" id="cuerpo-resumen">
        <?php foreach (preg_split('/\R{2,}/u', $resumen) as $parrafo): ?><p><?= nl2br(H::e(trim($parrafo))) ?></p><?php endforeach ?>
      </div>
      <button type="button" class="boton boton--fantasma" aria-expanded="false" aria-controls="cuerpo-resumen" data-leer-mas hidden>Leer más</button>
    </div>
    <?php endif ?>
    <form class="buscador" action="<?= H::e($enl->explorar()) ?>" role="search" aria-label="Buscar en esta caja" style="margin-top:24px">
      <input type="hidden" name="caja" value="<?= $n ?>">
      <div class="buscador__campo"><?= H::icono('buscar') ?><label class="solo-lector" for="q-caja">Buscar en la caja <?= $n ?></label>
        <input type="search" id="q-caja" name="q" placeholder="Buscar en esta caja…"></div>
      <button class="boton boton--primario">Buscar</button>
    </form>
    <p style="margin-top:12px"><a href="<?= H::e($enl->explorar(['caja' => $n])) ?>">Ver esta caja en el buscador, con todos los filtros</a></p>
  </div>

  <section class="seccion" aria-labelledby="h-docs">
    <div class="seccion-cabeza"><h2 id="h-docs">Documentos de la caja</h2><span class="meta">En el orden en que están en la caja</span></div>
    <?php if ($d['tipos']): ?>
    <ul class="accesos" aria-label="Filtrar esta caja por tipo" style="margin-bottom:16px">
      <?php foreach ($d['tipos'] as $tp): ?>
        <li><a class="acceso" href="<?= H::e($enl->explorar(['caja' => $n, 'tipo' => $tp['slug']])) ?>"><?= H::e($tp['nombre']) ?> <span class="n"><?= (int)$tp['cantidad'] ?></span></a></li>
      <?php endforeach ?>
    </ul>
    <?php endif ?>
    <?php if ($d['registros']): ?>
    <ol class="lista-registros" aria-label="Documentos de la caja <?= $n ?>">
      <?php foreach ($d['registros'] as $t) { require __DIR__ . '/_tarjeta.php'; } ?>
    </ol>
    <?php else: ?>
    <div class="estado"><h2>Esta caja todavía no tiene documentos catalogados</h2></div>
    <?php endif ?>
  </section>

  <?php if ($d['anterior'] || $d['siguiente']): ?>
  <nav class="vecinos" aria-label="Otras cajas">
    <?php if ($o = $d['anterior']): ?><a href="<?= H::e($enl->caja($o)) ?>"><?= H::icono('flechaIzq') ?><span><small>Caja anterior</small>Caja <?= (int)$o['numero'] ?>: <?= H::e($o['nombre']) ?></span></a><?php endif ?>
    <?php if ($o = $d['siguiente']): ?><a class="sig" href="<?= H::e($enl->caja($o)) ?>"><span><small>Caja siguiente</small>Caja <?= (int)$o['numero'] ?>: <?= H::e($o['nombre']) ?></span><?= H::icono('flechaDer') ?></a><?php endif ?>
  </nav>
  <?php endif ?>
</div>
<?php $contenido = ob_get_clean();
require __DIR__ . '/_sitio.php';
