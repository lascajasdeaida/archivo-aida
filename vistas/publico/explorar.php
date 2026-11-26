<?php
/**
 * Explorar el archivo: buscador + filtros combinables (docs/ux/sitio-publico.md §3–§4, maqueta explorar.html).
 * Todo funciona sin JS: el panel es un formulario GET, los chips y “Mostrar más” son enlaces reales y la URL guarda
 * el estado. Con JS (public/sitio/explorar.js) los filtros y “Mostrar más” se aplican sin recargar con explorar.json.
 * Recibe de SitioPublico::explorar(): $e (estado), $r (Buscador), $tarjetas, $facetas, $opciones, $chips, $conteo,
 * $todo, $sugerencias, $nFiltros, $enl y, si falló la búsqueda, $error.
 */
use Aida\Web\Html as H;
use Aida\Web\SitioPublico as SP;

$tituloPagina = $e['q'] !== '' ? "Buscar “{$e['q']}”" : 'Explorar el archivo';
$scripts = ['sitio/explorar.js'];
$error = $error ?? false;
$nFiltros = $nFiltros ?? 0;
ob_start(); ?>
<div class="contenedor">
  <nav class="migas" aria-label="Estás en"><ol><li><a href="<?= H::e($enl->inicio()) ?>">Inicio</a></li><li aria-current="page">Explorar el archivo</li></ol></nav>
  <h1>Explorar el archivo</h1>
  <form id="form-explorar" class="form-explorar" method="get" action="<?= H::e($enl->explorar()) ?>"
        data-json="<?= H::e($enl->explorarJson()) ?>" data-base="<?= H::e($enl->explorar()) ?>">
    <?php if (!$enl->reescritura): ?><input type="hidden" name="ruta" value="explorar"><?php endif ?>
    <div class="buscador" role="search" aria-label="Buscar en el archivo">
      <div class="buscador__campo">
        <label class="solo-lector" for="q">Buscar en el archivo</label>
        <input type="search" id="q" name="q" value="<?= H::e($e['q']) ?>" placeholder="Obra, persona, año o código" autocomplete="off" aria-keyshortcuts="/" aria-describedby="ayuda-q">
        <span class="buscador__cursor" aria-hidden="true"></span></div>
      <button class="boton boton--primario"><?= H::icono('buscar') ?>Buscar</button>
    </div>
    <p class="ayuda ayuda--buscador" id="ayuda-q"><span class="solo-con-js">Los resultados se actualizan mientras escribís.</span><span class="solo-sin-js">Escribí y apretá Enter.</span><span class="ayuda__atajo"> Tecla <kbd>/</kbd> para volver acá.</span></p>

    <div class="explorar">
      <div>
        <button type="button" class="boton boton--secundario boton-filtros" id="boton-filtros" aria-expanded="false" aria-controls="panel-filtros">
          <span class="boton-filtros__texto"><?= H::icono('filtro') ?>Filtros</span>
          <span class="boton-filtros__n" id="boton-filtros-n"><?= $nFiltros ?: '' ?></span></button>
        <div class="panel-filtros" id="panel-filtros" role="region" aria-labelledby="h-filtros">
          <div class="panel-filtros__cabeza"><h2 id="h-filtros-movil">Filtrar</h2>
            <button type="button" class="boton boton--secundario boton--chico cerrar-panel"><?= H::icono('cerrar') ?>Cerrar</button></div>
          <div class="panel-filtros__cuerpo">
            <h2 class="panel-filtros__titulo" id="h-filtros">Filtrar</h2>
            <div id="facetas"><?php if (!$error) { require __DIR__ . '/_explorar_facetas.php'; } ?></div>
            <p class="solo-sin-js"><button class="boton boton--secundario boton--ancho">Aplicar filtros</button></p>
          </div>
          <div class="panel-filtros__pie">
            <a class="boton boton--secundario" href="<?= H::e($enl->explorar($e['q'] !== '' ? ['q' => $e['q']] : [])) ?>" data-limpiar>Limpiar</a>
            <button class="boton boton--primario" id="ver-resultados">Ver <span id="ver-n"><?= H::numero($r['total'] ?? 0) ?></span> resultados</button>
          </div>
        </div>
      </div>

      <section aria-labelledby="conteo">
        <div class="barra-resultados">
          <h2 class="conteo" id="conteo" tabindex="-1"><?= $error ? 'Resultados' : H::e($conteo) ?></h2>
          <label class="orden">Ordenar por
            <select id="orden" name="orden">
              <?php foreach (SP::ORDENES as $v => [$nombre]): ?>
              <option value="<?= $v ?>"<?= $e['orden'] === $v ? ' selected' : '' ?><?= $v === 'relevancia' && $e['q'] === '' ? ' disabled' : '' ?>><?= H::e($nombre) ?></option>
              <?php endforeach ?>
            </select></label>
          <button class="boton boton--secundario boton--chico solo-sin-js">Ordenar</button>
        </div>
        <div id="chips"><?php if (!$error) { require __DIR__ . '/_explorar_chips.php'; } ?></div>
        <div class="columnas" aria-hidden="true"><span>Código</span><span>Documento</span><span>Caja</span><span>Año</span><span>Acceso</span></div>
        <div id="resultados"<?= $error ? '' : ' data-total="' . (int)($r['total'] ?? 0) . '"' ?>><?php require __DIR__ . '/_explorar_resultados.php'; ?></div>
        <p class="solo-lector" aria-live="polite" id="anuncio"></p>
      </section>
    </div>
  </form>
</div>
<?php $contenido = ob_get_clean();
require __DIR__ . '/_sitio.php';
