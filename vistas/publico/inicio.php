<?php
/**
 * Inicio del sitio (docs/ux/sitio-publico.md §3 «Inicio», maqueta docs/diseno/maquetas/inicio.html).
 * Recibe de SitioPublico::inicio(): $cifras, $totalArchivo, $etapas, $tipos, $totalTipos, $decadas, $destacados, $cajas, $frase, $enl.
 * Si no hay ninguna caja publicada muestra un estado claro (no un error) y no ofrece buscar en un archivo vacío.
 */
use Aida\Web\Html as H;

$tituloPagina = 'Archivo digital de Aída Bortnik';
$hay = $cifras['cajas'] > 0;
$min = 1938;
$max = 2013;
$pos = fn(int $a) => round(($a - $min) / ($max - $min) * 100, 1);
ob_start(); ?>
<section class="hero" aria-labelledby="h-hero">
  <div class="contenedor">
    <div>
      <p class="kicker">Archivo digital · Aída Bortnik</p>
      <h1 id="h-hero" data-tipeo>Las cajas <em>de Aída</em></h1>
      <p class="hero__frase"><?= H::e($frase) ?></p>
      <?php if ($hay): ?>
      <form class="buscador" action="<?= H::e($enl->explorar()) ?>" role="search" aria-label="Buscar en el archivo" data-evento-busqueda="inicio">
        <?php if (!$enl->reescritura): ?><input type="hidden" name="ruta" value="explorar"><?php endif ?>
        <div class="buscador__campo"><?= H::icono('buscar') ?>
          <label class="solo-lector" for="q-inicio">Buscar en el archivo</label>
          <input type="search" id="q-inicio" name="q" placeholder="Buscá una obra, una persona, un tipo de documento…" autocomplete="off"></div>
        <button class="boton boton--primario">Buscar</button>
      </form>
      <ul class="cifras">
        <li><b><?= H::numero($cifras['cajas']) ?></b> <?= $cifras['cajas'] === 1 ? 'caja publicada' : 'cajas publicadas' ?><?= $cifras['cajas'] < $totalArchivo ? ' de ' . H::numero($totalArchivo) : '' ?></li>
        <li><b><?= H::numero($cifras['documentos']) ?></b> documentos catalogados</li>
        <li><b><?= H::numero($cifras['digitales']) ?></b> ya digitalizados</li>
        <?php if ($cifras['desde']): ?><li><b><?= $cifras['desde'] ?>–<?= $cifras['hasta'] ?></b> años que abarca</li><?php endif ?>
      </ul>
      <?php endif ?>
    </div>
    <figure class="hero__foto" aria-hidden="true"><img src="<?= H::e($enl->recurso('sitio/img/cajas-deposito.jpg')) ?>" alt="" width="1000" height="563"></figure>
  </div>
</section>

<div class="contenedor">
<?php if (!$hay): ?>
  <section class="seccion" aria-labelledby="h-vacio">
    <div class="estado" id="archivo-sin-publicar">
      <h2 id="h-vacio">Todavía no hay cajas publicadas</h2>
      <p>El archivo se publica caja por caja, a medida que se revisa su catálogo. Muy pronto vas a poder recorrer acá
         las <?= H::numero($totalArchivo) ?> cajas.</p>
      <p><a class="boton boton--secundario" href="<?= H::e($enl->sobre()) ?>">Sobre el archivo</a></p>
    </div>
  </section>
<?php endif ?>

  <section class="seccion" aria-labelledby="h-etapas">
    <div class="seccion-cabeza"><h2 id="h-etapas">Etapas</h2></div>
    <ul class="etapas">
      <?php foreach ($etapas as $et): $asignada = $et['documentos'] > 0 && $et['id'] !== null; ?>
      <li><article class="etapa<?= $asignada ? '' : ' etapa--sin-asignar' ?>">
        <h3><?= $asignada ? '<a href="' . H::e($enl->explorar(['etapa' => $et['slug']])) . '">' . H::e($et['nombre']) . '</a>' : H::e($et['nombre']) ?></h3>
        <?php if ($et['desde'] && $et['hasta']): ?>
        <p class="etapa__anios"><?= $et['desde'] ?>–<?= $et['hasta'] ?></p>
        <div class="tiempo" role="img" aria-label="De <?= $et['desde'] ?> a <?= $et['hasta'] ?>, dentro de <?= $min ?>–<?= $max ?>"><i style="left:<?= $pos(max($min, $et['desde'])) ?>%;width:<?= $pos(min($max, $et['hasta'])) - $pos(max($min, $et['desde'])) ?>%"></i></div>
        <?php endif ?>
        <p class="etapa__n"><?= $asignada ? H::numero($et['documentos']) . ' documentos' : 'Sin cajas asignadas' ?></p>
      </article></li>
      <?php endforeach ?>
    </ul>
  </section>

<?php if ($hay): ?>
  <?php if ($tipos): ?>
  <section class="seccion" aria-labelledby="h-tipo">
    <div class="seccion-cabeza"><h2 id="h-tipo">Explorá por tipo de documento</h2></div>
    <ul class="accesos">
      <?php foreach ($tipos as $o): ?><li><a class="acceso" href="<?= H::e($enl->explorar(['tipo' => $o['v']])) ?>"><?= H::e($o['nombre']) ?> <span class="n"><?= H::numero($o['n']) ?></span></a></li><?php endforeach ?>
      <?php if ($totalTipos > count($tipos)): ?><li><a class="acceso acceso--todos" href="<?= H::e($enl->explorar()) ?>#faceta-tipo">Todos los tipos (<?= $totalTipos ?>)</a></li><?php endif ?>
    </ul>
  </section>
  <?php endif ?>

  <?php if ($decadas): ?>
  <section class="seccion" aria-labelledby="h-decada">
    <div class="seccion-cabeza"><h2 id="h-decada">Por época</h2></div>
    <ul class="accesos">
      <?php foreach ($decadas as $o): ?><li><a class="acceso" href="<?= H::e($enl->explorar(['decada' => $o['v']])) ?>"><?= H::e($o['nombre']) ?> <span class="n"><?= H::numero($o['n']) ?></span></a></li><?php endforeach ?>
    </ul>
  </section>
  <?php endif ?>

  <?php if ($destacados): ?>
  <section class="seccion" aria-labelledby="h-digit">
    <div class="seccion-cabeza"><h2 id="h-digit">Ya podés leerlos</h2><a href="<?= H::e($enl->explorar(['disponible' => 'digital'])) ?>">Ver todos los documentos digitalizados</a></div>
    <ul class="carrusel" aria-labelledby="h-digit">
      <?php foreach ($destacados as $t) { require __DIR__ . '/_tarjeta.php'; } ?>
    </ul>
  </section>
  <?php endif ?>

  <section class="seccion" aria-labelledby="h-cajas">
    <div class="seccion-cabeza"><h2 id="h-cajas">Las cajas</h2><a href="<?= H::e($enl->cajas()) ?>">Ver las cajas publicadas</a></div>
    <p>Cada caja reúne el material de una obra o un proyecto, tal como Aída lo guardó.</p>
    <ul class="grilla-cajas">
      <?php foreach ($cajas as $c) { require __DIR__ . '/_tarjeta_caja.php'; } ?>
    </ul>
  </section>
<?php endif ?>
</div>

<section class="seccion--pergamino" aria-labelledby="h-sobre">
  <div class="contenedor">
    <div class="seccion-cabeza"><h2 id="h-sobre">Sobre el archivo</h2></div>
    <div class="pendiente-cliente" role="note"><b>Texto a definir por el cliente:</b> qué es el archivo (texto institucional).</div>
    <p style="margin-top:16px"><a class="boton boton--secundario" href="<?= H::e($enl->sobre()) ?>">Cómo se armó el archivo y cómo consultarlo</a></p>
  </div>
</section>
<?php $contenido = ob_get_clean();
require __DIR__ . '/_sitio.php';
