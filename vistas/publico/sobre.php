<?php
/**
 * Sobre el archivo (maqueta sobre.html). Los textos institucionales los provee el cliente (docs/consultas-cliente.md §2):
 * cada lugar está marcado con “Texto a definir por el cliente”. Lo demás sale del sitio actual
 * (docs/diseno/referencias/identidad-sitio-actual.md), de la maqueta y de los datos (cifras).
 * Recibe $cifras, $enl.
 */
use Aida\Web\Html as H;

$tituloPagina = 'Sobre el archivo';
$pend = fn(string $que) => '<div class="pendiente-cliente" role="note"><b>Texto a definir por el cliente:</b> ' . H::e($que) . '</div>';
ob_start(); ?>
<div class="contenedor contenedor--texto">
  <nav class="migas" aria-label="Estás en"><ol><li><a href="<?= H::e($enl->inicio()) ?>">Inicio</a></li><li aria-current="page">Sobre el archivo</li></ol></nav>
  <p class="kicker">Archivo digital</p>
  <h1>Sobre el archivo</h1>
  <ul class="indice-sobre" aria-label="En esta página">
    <li><a class="acceso" href="#quien">Quién fue Aída Bortnik</a></li><li><a class="acceso" href="#catalogo">Cómo se armó el catálogo</a></li>
    <li><a class="acceso" href="#usar">Cómo usar el archivo</a></li><li><a class="acceso" href="#citar">Cómo citar</a></li>
    <li><a class="acceso" href="#condiciones">Condiciones de uso</a></li><li><a class="acceso" href="#accesibilidad">Accesibilidad</a></li><li><a class="acceso" href="#contacto">Contacto</a></li>
  </ul>
  <div class="prosa">
    <p class="sobre__frase">Más de 70 cajas de un corpus creativo donde las palabras importan más que las imágenes y los sueños más que las ficciones.</p>
    <?= $pend('qué es el archivo, en una o dos oraciones (hoy se usa la frase del sitio actual).') ?>
    <h2 id="quien">Quién fue Aída Bortnik</h2>
    <?= $pend('biografía breve.') ?>
    <h2 id="catalogo">Cómo se armó el catálogo</h2>
    <ul class="datos-caja datos-caja--lista">
      <li><b><?= H::numero($cifras['cajas']) ?></b> <?= $cifras['cajas'] === 1 ? 'caja publicada' : 'cajas publicadas' ?></li>
      <li><b><?= H::numero($cifras['documentos']) ?></b> documentos catalogados</li>
      <li><b><?= H::numero($cifras['digitales']) ?></b> ya digitalizados</li>
    </ul>
    <?= $pend('quiénes lo armaron, cómo se organizó (cajas, registros, catálogo) y el estado de la digitalización; 3 o 4 párrafos cortos.') ?>
    <h2 id="usar">Cómo usar el archivo</h2>
    <dl class="glosario">
      <div><dt><?= H::icono('caja') ?> Caja</dt><dd>El número de caja es el del depósito. Varias obras ocupan más de una caja.</dd></div>
      <div><dt><span class="codigo">C14R3</span></dt><dd>Caja 14 · Registro 3. Es el código del documento y no cambia.</dd></div>
      <div><dt><span class="etq etq--digital"><?= H::icono('documento') ?>Documento digital</span></dt><dd>Ver documento · Descargar PDF</dd></div>
      <div><dt><span class="etq etq--ficha"><?= H::icono('ficha') ?>Solo ficha</span></dt><dd>Todavía no está digitalizado. El archivo se digitaliza por etapas; por ahora podés ver la ficha del catálogo.</dd></div>
      <div><dt><span class="etq etq--pedido"><?= H::icono('candado') ?>Consulta a pedido</span></dt><dd>La ficha es pública, pero el documento se consulta pidiéndolo al archivo.</dd></div>
    </dl>
    <h2 id="citar">Cómo citar</h2>
    <p class="cita">Bortnik, Aída. [Título o tipo]. [Año]. Archivo “Las cajas de Aída”, caja N, código CxRy. URL.</p>
    <?= $pend('formato de cita definitivo (el de arriba es provisorio).') ?>
    <h2 id="condiciones">Condiciones de uso</h2>
    <?= $pend('condiciones de uso y licencia del material.') ?>
    <h2 id="accesibilidad">Accesibilidad</h2>
    <?= $pend('declaración de conformidad WCAG 2.1 AA y contacto para reportar barreras.') ?>
    <h2 id="contacto">Contacto</h2>
    <?= $pend('correo del archivo y quién responde las consultas.') ?>
  </div>
</div>
<?php $contenido = ob_get_clean();
require __DIR__ . '/_sitio.php';
