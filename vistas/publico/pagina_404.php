<?php
/** 404 del sitio: dirección o código inexistente, con buscador (docs/ux/sitio-publico.md §5.4). Recibe $enl. */
use Aida\Web\Html as H;

$tituloPagina = 'Página no encontrada';
ob_start(); ?>
<div class="contenedor contenedor--texto">
  <p class="kicker">Error 404</p>
  <h1>No encontramos esta página</h1>
  <p>La dirección no corresponde a ninguna página ni a ningún documento publicado del archivo. Si llegaste desde un enlace, puede estar mal escrito.</p>
  <form class="buscador" action="<?= H::e($enl->explorar()) ?>" role="search" aria-label="Buscar en el archivo" style="margin:24px 0">
    <?php if (!$enl->reescritura): ?><input type="hidden" name="ruta" value="explorar"><?php endif ?>
    <div class="buscador__campo"><label class="solo-lector" for="q-404">Buscar en el archivo</label>
      <input type="search" id="q-404" name="q" placeholder="Obra, persona, año o código"></div>
    <button class="boton boton--primario"><?= H::icono('buscar') ?><span class="buscador__texto">Buscar</span></button>
  </form>
  <p><a href="<?= H::e($enl->inicio()) ?>">Ir al inicio</a> · <a href="<?= H::e($enl->cajas()) ?>">Ver las cajas</a></p>
</div>
<?php $contenido = ob_get_clean();
require __DIR__ . '/_sitio.php';
