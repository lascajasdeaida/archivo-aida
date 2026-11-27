<?php
/**
 * Inicio del archivo (docs/ux/sitio-publico.md §3 “Inicio”; lenguaje de sistema, docs/diseno/sistema.md “Archivo: sistema”).
 * Es esencialmente el buscador (con el cursor de la máquina) + cifras en una línea + accesos rápidos (cajas, tipos,
 * décadas, etapas si hay) + los últimos documentos digitales. Sin hero.
 * Recibe de SitioPublico::inicio(): $cifras, $totalArchivo, $etapas, $tipos, $totalTipos, $decadas, $destacados, $cajas, $frase, $enl.
 * Si no hay ninguna caja publicada muestra un estado claro (no un error) y no ofrece buscar en un archivo vacío.
 */
use Aida\Web\Html as H;

$tituloPagina = 'Archivo digital de Aída Bortnik';
$seccion = 'inicio';
$hay = $cifras['cajas'] > 0;
$etapasAsignadas = array_values(array_filter($etapas, fn($et) => $et['documentos'] > 0 && $et['id'] !== null));
ob_start(); ?>
<div class="contenedor">
  <section class="inicio" aria-labelledby="h-inicio">
    <h1 id="h-inicio">Buscar en el archivo de Aída Bortnik</h1>
    <?php if ($hay): ?>
    <form class="buscador buscador--grande" action="<?= H::e($enl->explorar()) ?>" role="search" aria-label="Buscar en el archivo" data-evento-busqueda="inicio">
      <?php if (!$enl->reescritura): ?><input type="hidden" name="ruta" value="explorar"><?php endif ?>
      <div class="buscador__campo">
        <label class="solo-lector" for="q-inicio">Buscar en el archivo</label>
        <input type="search" id="q-inicio" name="q" placeholder="Obra, persona, año o código" autocomplete="off" aria-keyshortcuts="/" aria-describedby="ayuda-inicio">
        <span class="buscador__cursor" aria-hidden="true"></span></div>
      <button class="boton boton--primario"><?= H::icono('buscar') ?><span class="buscador__texto">Buscar</span></button>
    </form>
    <p class="ayuda" id="ayuda-inicio">Por ejemplo: <a href="<?= H::e($enl->explorar(['q' => 'carta'])) ?>">carta</a>, <a href="<?= H::e($enl->explorar(['q' => 'guion'])) ?>">guion</a>, <a href="<?= H::e($enl->explorar(['q' => 'La historia oficial'])) ?>">La historia oficial</a> o un código como <span class="codigo">C2R5</span>.<span class="ayuda__atajo"> Tecla <kbd>/</kbd> para buscar desde cualquier página.</span></p>
    <p class="cifras">
      <span><b><?= H::numero($cifras['cajas']) ?></b> <?= $cifras['cajas'] === 1 ? 'caja publicada' : 'cajas publicadas' ?><?= $cifras['cajas'] < $totalArchivo ? ' de ' . H::numero($totalArchivo) : '' ?></span>
      <span><b><?= H::numero($cifras['documentos']) ?></b> documentos</span>
      <span><b><?= H::numero($cifras['digitales']) ?></b> digitalizados</span>
      <?php if ($cifras['desde']): ?><span><b><?= $cifras['desde'] ?>–<?= $cifras['hasta'] ?></b></span><?php endif ?>
    </p>
    <?php else: ?>
    <div class="estado" id="archivo-sin-publicar">
      <h2>Todavía no hay cajas publicadas</h2>
      <p>El archivo se publica caja por caja, a medida que se revisa su catálogo. Muy pronto vas a poder buscar acá
         en las <?= H::numero($totalArchivo) ?> cajas.</p>
      <p><a href="<?= H::e($enl->sobre()) ?>">Sobre el archivo</a></p>
    </div>
    <?php endif ?>
  </section>

<?php if ($hay): ?>
  <section class="bloque bloque--cajas" aria-labelledby="h-cajas">
    <div class="bloque__cabeza"><h2 id="h-cajas">Cajas</h2><a href="<?= H::e($enl->cajas()) ?>">Todas las cajas publicadas</a></div>
    <ul class="grilla-mcajas grilla-mcajas--chica">
      <?php $variante = 'chica'; foreach ($cajas as $c) { require __DIR__ . '/_modulo_caja.php'; } unset($variante); ?>
    </ul>
  </section>

  <div class="accesos-rapidos">
    <?php if ($tipos): ?>
    <section class="indice" aria-labelledby="h-tipo">
      <h2 id="h-tipo">Tipos de documento</h2>
      <ul>
        <?php foreach ($tipos as $o): ?><li><a href="<?= H::e($enl->explorar(['tipo' => $o['v']])) ?>"><span><?= H::e($o['nombre']) ?></span><span class="n"><?= H::numero($o['n']) ?></span></a></li><?php endforeach ?>
      </ul>
      <?php if ($totalTipos > count($tipos)): ?><p class="indice__mas"><a href="<?= H::e($enl->explorar()) ?>#faceta-tipo">Los <?= $totalTipos ?> tipos</a></p><?php endif ?>
    </section>
    <?php endif ?>

    <?php if ($decadas || $etapasAsignadas): ?>
    <section class="indice" aria-labelledby="h-decada">
      <h2 id="h-decada">Décadas</h2>
      <ul>
        <?php foreach ($decadas as $o): ?><li><a href="<?= H::e($enl->explorar(['decada' => $o['v']])) ?>"><span><?= H::e($o['nombre']) ?></span><span class="n"><?= H::numero($o['n']) ?></span></a></li><?php endforeach ?>
      </ul>
      <?php if ($etapasAsignadas): ?>
      <h2 id="h-etapas" class="indice__sub">Etapas</h2>
      <ul aria-labelledby="h-etapas">
        <?php foreach ($etapasAsignadas as $et): ?><li><a href="<?= H::e($enl->explorar(['etapa' => $et['slug']])) ?>"><span><?= H::e($et['nombre']) ?><?= $et['desde'] && $et['hasta'] ? ' <span class="indice__num">' . $et['desde'] . '–' . $et['hasta'] . '</span>' : '' ?></span><span class="n"><?= H::numero($et['documentos']) ?></span></a></li><?php endforeach ?>
      </ul>
      <?php endif ?>
    </section>
    <?php endif ?>
  </div>

  <?php if ($destacados): ?>
  <section class="bloque" aria-labelledby="h-digit">
    <div class="bloque__cabeza"><h2 id="h-digit">Documentos digitales</h2>
      <a href="<?= H::e($enl->explorar(['disponible' => 'digital'])) ?>">Ver todos los digitalizados</a></div>
    <ul class="lista-registros">
      <?php foreach ($destacados as $t) { require __DIR__ . '/_tarjeta.php'; } ?>
    </ul>
  </section>
  <?php endif ?>
<?php endif ?>

  <p class="inicio__sobre"><a href="<?= H::e($enl->sobre()) ?>">Sobre el archivo</a> · <a href="<?= H::e($enl->sobre('citar')) ?>">Cómo citar</a> · <a href="<?= H::e($enl->sobre('usar')) ?>">Cómo consultar un documento a pedido</a></p>
</div>
<?php $contenido = ob_get_clean();
require __DIR__ . '/_sitio.php';
