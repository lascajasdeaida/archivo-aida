<?php
/** Error 500 del sitio (ya quedó en el log del servidor). Recibe $enl. */
use Aida\Web\Html as H;

$tituloPagina = 'Error';
ob_start(); ?>
<div class="contenedor contenedor--texto">
  <div class="estado estado--error" role="alert" style="margin-top:32px"><h1 style="font-size:var(--t-titulo)"><?= H::icono('alerta') ?> Algo falló</h1>
    <p>No pudimos mostrar esta página. Probá de nuevo en unos minutos.</p>
    <p><a class="boton boton--primario" href="">Reintentar</a> <a class="boton boton--fantasma" href="<?= H::e($enl->inicio()) ?>">Ir al inicio</a></p></div>
</div>
<?php $contenido = ob_get_clean();
require __DIR__ . '/_sitio.php';
