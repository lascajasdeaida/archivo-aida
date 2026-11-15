<?php
/**
 * Layout del sitio público: cabecera, pie y sprite de íconos (docs/diseno/componentes.md §1).
 * Lo usan ficha.php y caja.php; el backoffice lo usa igual para la vista previa.
 *
 * Recibe:
 *  - $enl        Aida\Web\Enlaces (todas las URL salen de acá)
 *  - $tituloPagina, $contenido (HTML ya armado), $seccion ('cajas', 'explorar'… para aria-current)
 *  - $robots     opcional (vista previa: «noindex,nofollow»)
 *  - $antes      opcional: HTML que va arriba de todo (banner de la vista previa)
 *  - $cabezaExtra opcional: <link>/<script> extra en el <head>
 */
use Aida\Web\Html as H;

$actual = fn(string $s) => ($seccion ?? '') === $s ? ' aria-current="page"' : '';
?>
<!doctype html>
<html lang="es-AR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if (!empty($robots)): ?><meta name="robots" content="<?= H::e($robots) ?>">
<?php endif ?>
<title><?= H::e($tituloPagina) ?> · Las cajas de Aída</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= H::e($enl->recurso('sitio/estilo.css')) ?>">
<?= $cabezaExtra ?? '' ?>
<script src="<?= H::e($enl->recurso('sitio/sitio.js')) ?>" defer></script>
</head>
<body>
<?php require __DIR__ . '/_iconos.php'; ?>
<a class="saltar" href="#contenido">Saltar al contenido</a>
<?= $antes ?? '' ?>
<header class="cabecera"><div class="contenedor">
  <a class="marca" href="<?= H::e($enl->inicio()) ?>" aria-label="Las cajas de Aída, inicio">
    <svg class="marca__sello" viewBox="0 0 40 40" aria-hidden="true" focusable="false"><g fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round">
      <path d="M5 14h30v21H5z"/><path d="M3 8h34v6H3z"/><path d="M14 20h12v5H14z"/></g></svg>
    <span class="marca__texto">Las cajas <em>de Aída</em></span></a>
  <a class="cabecera__accion cabecera__buscar" href="<?= H::e($enl->explorar()) ?>#q"><?= H::icono('buscar') ?><span class="texto-largo">Buscar</span><span class="solo-lector">Buscar en el archivo</span></a>
  <button class="cabecera__accion menu-boton" type="button" aria-expanded="false" aria-controls="nav-principal"><?= H::icono('menu') ?>Menú</button>
  <nav class="nav-principal" id="nav-principal" aria-label="Principal"><ul>
    <li><a href="<?= H::e($enl->inicio()) ?>"<?= $actual('inicio') ?>>Inicio</a></li>
    <li><a href="<?= H::e($enl->explorar()) ?>"<?= $actual('explorar') ?>>Explorar el archivo</a></li>
    <li><a href="<?= H::e($enl->cajas()) ?>"<?= $actual('cajas') ?>>Las 70 cajas</a></li>
    <li><a href="<?= H::e($enl->sobre()) ?>"<?= $actual('sobre') ?>>Sobre el archivo</a></li>
  </ul></nav>
</div></header>

<main id="contenido" tabindex="-1">
<?= $contenido ?>
</main>

<footer class="pie"><div class="contenedor">
  <span class="marca__texto">Las cajas <em style="color:var(--c-ambar)">de Aída</em></span>
  <p>Archivo Aída Bortnik</p>
  <ul>
    <li><a href="<?= H::e($enl->sobre()) ?>">Sobre el archivo</a></li>
    <li><a href="<?= H::e($enl->sobre('citar')) ?>">Cómo citar</a></li>
    <li><a href="<?= H::e($enl->sobre('condiciones')) ?>">Condiciones de uso</a></li>
    <li><a href="<?= H::e($enl->sobre('accesibilidad')) ?>">Accesibilidad</a></li>
    <li><a href="<?= H::e($enl->sobre('contacto')) ?>">Contacto</a></li>
  </ul>
</div></footer>
</body>
</html>
