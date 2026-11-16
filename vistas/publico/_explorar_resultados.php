<?php
/**
 * Zona de resultados de Explorar con sus estados (docs/ux/sitio-publico.md §4.4): error, sin resultados (con lo que
 * daría quitar cada filtro) y la lista con “Mostrar más”. Recibe $r, $tarjetas, $sugerencias, $e, $enl y $error.
 */
use Aida\Web\Html as H;

if (!empty($error)): ?>
<div class="estado estado--error" role="alert"><h2><?= H::icono('alerta') ?> No pudimos cargar los resultados</h2>
  <p>Hubo un problema al consultar el archivo. Tus filtros siguen guardados.</p>
  <a class="boton boton--primario" href="" data-reintentar>Reintentar</a></div>
<?php elseif ($r['total'] === 0): ?>
<div class="estado" id="sin-resultados">
  <?php if ($e['q'] === '' && !$sugerencias): ?>
  <h2>Todavía no hay documentos publicados</h2>
  <p>El archivo se publica caja por caja, a medida que se revisa su catálogo.</p>
  <?php else: ?>
  <h2>No encontramos documentos con esa combinación</h2>
  <?php if ($sugerencias): ?><p>Probá quitar alguno de los filtros:</p>
  <ul><?php foreach ($sugerencias as $s): ?>
    <li><a class="boton boton--fantasma" href="<?= H::e($s['quitar']) ?>">Quitar “<?= H::e($s['titulo']) ?>: <?= H::e($s['valor']) ?>”</a> → <?= H::numero($s['n']) ?> <?= $s['n'] === 1 ? 'resultado' : 'resultados' ?></li>
  <?php endforeach ?></ul><?php endif ?>
  <p>Muchos documentos no tienen título: buscar por tipo (“carta”, “guion”) o por la obra suele dar más resultados.</p>
  <?php endif ?>
</div>
<?php else: ?>
<ul class="lista-registros" id="lista">
  <?php require __DIR__ . '/_explorar_items.php'; ?>
</ul>
<?php require __DIR__ . '/_explorar_paginacion.php'; ?>
<?php endif;
