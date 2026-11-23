<?php
/**
 * Layout del sitio público: cabecera, pie y sprite de íconos (docs/diseno/componentes.md §1).
 * Lo usan ficha.php y caja.php; el backoffice lo usa igual para la vista previa.
 *
 * Recibe:
 *  - $enl        Aida\Web\Enlaces (todas las URL salen de acá)
 *  - $tituloPagina, $contenido (HTML ya armado), $seccion ('cajas', 'explorar'… para aria-current)
 *  - $robots     opcional (vista previa: “noindex,nofollow”)
 *  - $antes      opcional: HTML que va arriba de todo (banner de la vista previa)
 *  - $cabezaExtra opcional: <link>/<script> extra en el <head>
 *  - $meta       opcional (sitio público): descripcion, canonical (absoluta), og_tipo ('website' por defecto)
 *  - $medicion   opcional (solo SitioPublico::pagina): contexto de GTM/GA4 (Aida\Web\Medicion, docs/medicion.md).
 *                La vista previa del backoffice no lo pasa: ahí no se carga GTM ni se mide nada.
 */
use Aida\Web\Html as H;
use Aida\Web\Medicion;

$medicion = $medicion ?? null;

$meta = $meta ?? [];
$tituloCompleto = $tituloPagina . ' · Las cajas de Aída';

$actual = fn(string $s) => ($seccion ?? '') === $s ? ' aria-current="page"' : '';
?>
<!doctype html>
<html lang="es-AR" class="sin-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#FFFFFF">
<?= Medicion::cabeza($medicion) ?>
<script>document.documentElement.className = document.documentElement.className.replace('sin-js', 'con-js');</script>
<?php if (!empty($robots)): ?><meta name="robots" content="<?= H::e($robots) ?>">
<?php endif ?>
<title><?= H::e($tituloCompleto) ?></title>
<?php if (!empty($meta['descripcion'])): ?><meta name="description" content="<?= H::e($meta['descripcion']) ?>">
<?php endif ?>
<?php if (!empty($meta['canonical'])): ?><link rel="canonical" href="<?= H::e($meta['canonical']) ?>">
<meta property="og:type" content="<?= H::e($meta['og_tipo'] ?? 'website') ?>">
<meta property="og:site_name" content="Las cajas de Aída">
<meta property="og:locale" content="es_AR">
<meta property="og:title" content="<?= H::e($tituloPagina) ?>">
<meta property="og:url" content="<?= H::e($meta['canonical']) ?>">
<?php if (!empty($meta['descripcion'])): ?><meta property="og:description" content="<?= H::e($meta['descripcion']) ?>">
<meta name="twitter:card" content="summary">
<?php endif ?>
<?php endif ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="icon" href="<?= H::e($enl->recurso('sitio/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="icon" href="<?= H::e($enl->recurso('sitio/img/favicon-32.png')) ?>" sizes="32x32">
<link rel="apple-touch-icon" href="<?= H::e($enl->recurso('sitio/img/apple-touch-icon.png')) ?>">
<link rel="stylesheet" href="<?= H::e($enl->recurso('sitio/estilo.css')) ?>">
<?= $cabezaExtra ?? '' ?>
<?php if ($medicion): ?><script src="<?= H::e($enl->recurso('sitio/medicion.js')) ?>" defer></script>
<?php endif ?>
<script src="<?= H::e($enl->recurso('sitio/sitio.js')) ?>" defer></script>
<?php foreach ($scripts ?? [] as $js): ?><script src="<?= H::e($enl->recurso($js)) ?>" defer></script>
<?php endforeach ?>
</head>
<body>
<?= Medicion::noscript($medicion) ?>
<?php require __DIR__ . '/_iconos.php'; ?>
<a class="saltar" href="#contenido">Saltar al contenido</a>
<?= $antes ?? '' ?>
<header class="cabecera"><div class="contenedor">
  <a class="marca" href="<?= H::e($enl->inicio()) ?>"><img src="<?= H::e($enl->recurso('sitio/img/logo-tinta.svg')) ?>" alt="Las cajas de Aída, inicio" width="120" height="53"></a>
  <a class="cabecera__accion cabecera__buscar" href="<?= H::e($enl->explorar()) ?>#q"><?= H::icono('buscar') ?><span class="texto-largo">Buscar</span><span class="solo-lector">Buscar en el archivo</span><kbd class="atajo" aria-hidden="true">/</kbd></a>
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
  <div class="pie__marca">
    <a href="/"><img class="pie__logo" src="<?= H::e($enl->recurso('sitio/img/logo-calado-blanco.svg')) ?>" alt="Las cajas de Aída: inicio del sitio" width="170" height="75"></a>
    <p>Archivo Aída Bortnik (1938–2013)<br><a href="mailto:lascajasdeaida@gmail.com">lascajasdeaida@gmail.com</a></p>
  </div>
  <nav class="pie__nav" aria-label="Pie de página">
    <ul class="pie__proyectos">
      <li><a href="/catalogo.html">Catálogo</a></li><li><a href="/muestra.html">Muestra</a></li><li><a href="/conservacion.html">Conservación</a></li>
      <li><a href="/digitalizacion.html">Digitalización</a></li><li><a href="/documental.html">Documental</a></li>
    </ul>
    <ul class="pie__archivo">
      <li><a href="<?= H::e($enl->sobre()) ?>">Sobre el archivo</a></li>
      <li><a href="<?= H::e($enl->sobre('citar')) ?>">Cómo citar</a></li>
      <li><a href="<?= H::e($enl->sobre('condiciones')) ?>">Condiciones de uso</a></li>
      <li><a href="<?= H::e($enl->sobre('accesibilidad')) ?>">Accesibilidad</a></li>
    </ul>
    <p class="pie__contacto"><a class="boton-pie" href="/contacto.html">Contacto</a></p>
  </nav>
  <div class="pie__legal">
    <p>© 2026 Las cajas de Aída — Archivo Aída Bortnik. Todos los derechos reservados.</p>
    <p class="pie__credito">Desarrollado por <a href="https://www.p3design.com" rel="noopener">P3Design</a></p>
  </div>
</div></footer>
</body>
</html>
