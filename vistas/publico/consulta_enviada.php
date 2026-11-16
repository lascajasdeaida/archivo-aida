<?php
/** Confirmación de la consulta (después del POST, PRG). Recibe $d (ficha), $numero (null si no se puede mostrar), $enl. */
use Aida\Web\Html as H;

$codigo = (string)$d['registro']['codigo'];
$tituloPagina = 'Recibimos tu pedido';
ob_start(); ?>
<div class="contenedor contenedor--texto">
  <nav class="migas" aria-label="Estás en"><ol>
    <li><a href="<?= H::e($enl->inicio()) ?>">Inicio</a></li>
    <li><a href="<?= H::e($enl->registro($d['registro'])) ?>"><?= H::e($codigo) ?></a></li>
    <li aria-current="page">Consulta enviada</li>
  </ol></nav>
  <div class="confirmacion" role="status" style="margin-top:24px" data-consulta-enviada="<?= H::e($codigo) ?>" data-caja="<?= (int)$d['caja']['numero'] ?>">
    <h1 tabindex="-1" id="ok" style="font-size:var(--t-titulo)"><?= H::icono('check') ?>Recibimos tu pedido</h1>
    <?php if ($numero): ?><p>Número de consulta: <span class="numero-consulta"><?= H::e($numero) ?></span>. Anotalo por si necesitás escribirnos.</p><?php endif ?>
    <p>Pediste la consulta del documento <span class="codigo"><?= H::e($codigo) ?></span>: <?= H::nombre($d) ?>.</p>
    <p>El equipo del archivo te va a responder por correo en un plazo de <b>[a definir por el cliente]</b> días hábiles.</p>
    <div class="acciones"><a class="boton boton--secundario" href="<?= H::e($enl->registro($d['registro'])) ?>"><?= H::icono('flechaIzq') ?>Volver al documento</a>
      <a class="boton boton--fantasma" href="<?= H::e($enl->explorar()) ?>">Seguir explorando</a></div>
  </div>
</div>
<?php $contenido = ob_get_clean();
require __DIR__ . '/_sitio.php';
