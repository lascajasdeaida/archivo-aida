<?php
/**
 * Ficha de un registro en el sitio público (docs/ux/sitio-publico.md §3 «Ficha», componentes.md §11, §12, §14).
 * Recibe $d (Aida\Sitio::fichaPor…), $enl (Aida\Web\Enlaces) y, opcionales, $antes y $robots para _sitio.php.
 * No muestra observaciones de conservación ni de digitalización, nomenclatura del Excel ni campos internos.
 */
use Aida\Web\Enlaces;
use Aida\Web\Html as H;

$r = $d['registro'];
$caja = $d['caja'];
$codigo = (string)$r['codigo'];
$anio = $d['anio'];
$tituloPagina = "{$d['titulo']} ($codigo)";
$seccion = 'ficha';
$fila = function (string $dt, ?string $dd): string {
    return $dd !== null && $dd !== '' ? "<div><dt>$dt</dt><dd>$dd</dd></div>" : '';
};
$vlinks = fn(array $terminos, string $faceta) => implode(', ', array_map(
    fn($t) => '<a href="' . H::e($enl->explorar([$faceta => $t['slug']])) . '">' . H::e($t['nombre']) . '</a>', $terminos));
$etiquetaFormato = fn(array $a) => in_array($a['formato'], ['pdf'], true) ? 'PDF' : strtoupper($a['formato']);
$citaAnio = $anio['etiqueta'] === null ? 's. f.'
    : ($anio['dudoso'] ? '[' . str_replace(' (sin confirmar)', '', $anio['etiqueta']) . '?]' : $anio['etiqueta']);
$cita = 'Bortnik, Aída. ' . ($d['sinTitulo'] ? '[' . $d['titulo'] . ']' : $d['titulo']) . ". $citaAnio. Archivo «Las cajas de Aída», caja "
    . $caja['numero'] . ", $codigo. " . Enlaces::canonica($codigo);
$varias = count($d['archivos']) > 1;
ob_start(); ?>
<div class="contenedor">
  <nav class="migas" aria-label="Estás en"><ol>
    <li><a href="<?= H::e($enl->inicio()) ?>">Inicio</a></li>
    <li><a href="<?= H::e($enl->caja($caja)) ?>">Caja <?= (int)$caja['numero'] ?>: <?= H::e($caja['nombre']) ?></a></li>
    <li aria-current="page"><?= H::e($codigo) ?></li>
  </ol></nav>

  <p class="volver" data-volver hidden><a href="<?= H::e($enl->explorar()) ?>"><?= H::icono('flechaIzq') ?>Volver a los resultados</a></p>
  <div class="cabeza-pagina">
    <p class="kicker">Caja <?= (int)$caja['numero'] ?> · Registro <?= (int)$r['numero'] ?> · Código <span class="codigo"><?= H::e($codigo) ?></span></p>
    <h1><?= H::nombre($d) ?></h1>
    <?php if ($d['sinTitulo']): ?><p class="meta">Este documento no tiene título en el catálogo; se lo nombra por su tipo.</p><?php endif ?>
    <div class="etqs">
      <?= $d['digital'] ? '<span class="etq etq--digital">' . H::icono('documento') . 'Documento digital</span>'
                        : '<span class="etq etq--ficha">' . H::icono('ficha') . 'Solo ficha</span>' ?>
      <?php if ($d['documento'] === 'pedido'): ?><span class="etq etq--pedido"><?= H::icono('candado') ?>Consulta a pedido</span><?php endif ?>
      <?php foreach (array_slice($d['tipos'], 0, 5) as $tp): ?><span class="etq etq--tipo"><?= H::e($tp['nombre']) ?></span><?php endforeach ?>
      <?php if ($anio['dudoso']): ?><span class="etq etq--duda"><?= H::icono('duda') ?>Año sin confirmar</span><?php endif ?>
    </div>
  </div>

  <div class="ficha">
    <aside class="ficha__documento documento" aria-labelledby="h-doc">
      <h2 id="h-doc">Documento</h2>
      <?php if ($d['documento'] === 'ver'): ?>
        <div class="vista vista--hoja" aria-hidden="true"></div>
        <div class="acciones">
          <?php foreach ($d['archivos'] as $i => $a): ?>
            <a class="boton <?= $i === 0 ? 'boton--primario' : 'boton--secundario' ?>" href="<?= H::e($enl->archivo($a['id'])) ?>"
               data-visor="<?= H::e($enl->archivo($a['id'])) ?>" data-visor-titulo="<?= H::e($codigo . ($varias ? ', parte ' . ($i + 1) : '')) ?>"
               data-descarga="<?= H::e($enl->archivo($a['id'], true)) ?>" aria-controls="visor" aria-expanded="false">
              <?= H::icono('ver') ?><?= $varias ? 'Ver parte ' . ($i + 1) : 'Ver documento' ?><?php if (!$varias && $r['paginas']): ?> <span class="peso">(<?= (int)$r['paginas'] ?> págs.)</span><?php endif ?></a>
          <?php endforeach ?>
          <?php foreach ($d['archivos'] as $i => $a): $peso = H::peso($a['peso']); ?>
            <a class="boton boton--secundario" href="<?= H::e($enl->archivo($a['id'], true)) ?>">
              <?= H::icono('descargar') ?>Descargar <?= $etiquetaFormato($a) ?><?= $varias ? ' (parte ' . ($i + 1) . ')' : '' ?><?php if ($peso): ?> <span class="peso">(<?= H::e($peso) ?>)</span><?php endif ?></a>
          <?php endforeach ?>
        </div>
      <?php elseif ($d['documento'] === 'pedido'): ?>
        <div class="vista vista--pedido" aria-hidden="true"><?= H::icono('candado') ?></div>
        <div class="aviso aviso--pedido" role="note"><?= H::icono('candado') ?>
          <p><b>Consulta a pedido</b>La ficha es pública, pero el documento se consulta pidiéndolo al archivo.
            <?= $d['digital'] ? 'Está digitalizado.' : 'Todavía no está digitalizado.' ?></p></div>
        <div class="acciones"><a class="boton boton--primario" href="<?= H::e($enl->consulta($codigo)) ?>"><?= H::icono('sobre') ?>Pedir consulta de este documento</a></div>
        <p class="meta" style="margin:0">Te respondemos por correo. No hace falta crear una cuenta.</p>
      <?php else: ?>
        <div class="vista vista--vacia" aria-hidden="true"><?= H::icono('ficha') ?></div>
        <div class="aviso aviso--ficha" role="note"><?= H::icono('ficha') ?>
          <p><b>Todavía no está digitalizado</b>El archivo se digitaliza por etapas; por ahora podés ver la ficha del catálogo.</p></div>
        <div class="acciones"><a class="boton boton--secundario" href="<?= H::e($enl->consulta($codigo, 'original')) ?>"><?= H::icono('sobre') ?>Consultar por este documento</a></div>
      <?php endif ?>
    </aside>

    <div>
      <h2>Ficha del catálogo</h2>
      <dl class="catalogo">
        <?= $fila('Título', $d['sinTitulo'] ? '<span class="sin-titulo">Sin título</span>' : H::e($d['titulo'])) ?>
        <?= $fila('Tipo de documento', $vlinks($d['tipos'], 'tipo')) ?>
        <?= $fila('Autor/es', H::e($r['autor'])) ?>
        <?= $fila('Fecha', H::e($r['fecha_texto'])) ?>
        <?= $fila('Año', $anio['etiqueta'] === null ? null
            : ($anio['decada'] !== null && !$anio['dudoso'] ? '<a href="' . H::e($enl->explorar(['decada' => $anio['decada']])) . '">' . H::e($anio['etiqueta']) . '</a>' : H::anio($anio))
              . ($anio['original'] !== null ? '<br><span class="meta">En el catálogo: «' . H::e($anio['original']) . '»</span>' : '')) ?>
        <?= $fila('Lugar', H::e($r['lugar'])) ?>
        <?= $fila('Páginas', $r['paginas'] ? (string)(int)$r['paginas'] : null) ?>
        <?= $fila('Descriptores', $vlinks($d['descriptores'], 'descriptor')) ?>
        <?= $fila('ISBN', H::e($r['isbn'])) ?>
        <?= $fila('Caja', '<a href="' . H::e($enl->caja($caja)) . '">Caja ' . (int)$caja['numero'] . ': ' . H::e($caja['nombre']) . '</a>') ?>
        <?= $fila('Acceso', $d['documento'] === 'pedido' ? 'Consulta a pedido' : 'Libre') ?>
      </dl>

      <h2 style="margin-top:40px">Descripción</h2>
      <?php $resumen = trim((string)$r['resumen']); ?>
      <?php if ($resumen !== ''): foreach (preg_split('/\R{2,}/u', $resumen) as $parrafo): ?>
        <p><?= nl2br(H::e(trim($parrafo))) ?></p>
      <?php endforeach; else: ?><p class="meta">Sin descripción.</p><?php endif ?>
      <?php if (trim((string)$r['obs_editorial']) !== ''): ?><h3>Observaciones</h3><p><?= nl2br(H::e(trim((string)$r['obs_editorial']))) ?></p><?php endif ?>

      <h2 id="citar" style="margin-top:40px">Cómo citar</h2>
      <p class="cita" id="cita"><?= H::e($cita) ?></p>
      <div class="acciones">
        <button class="boton boton--secundario" type="button" data-copiar="#cita" data-copiado="Cita copiada."><?= H::icono('copiar') ?>Copiar cita</button>
        <button class="boton boton--secundario" type="button" data-copiar-texto="<?= H::e(Enlaces::canonica($codigo)) ?>" data-copiado="Enlace copiado."><?= H::icono('enlace') ?>Copiar enlace</button>
      </div>
      <p role="status" class="meta" id="copiado"></p>
    </div>
  </div>

  <?php if ($d['documento'] === 'ver'): ?>
  <section id="visor" class="visor" hidden aria-label="Visor del documento <?= H::e($codigo) ?>">
    <div class="visor__barra" role="toolbar" aria-label="Controles del visor">
      <div class="grupo"><button class="visor__boton" type="button" data-visor-pantalla aria-label="Pantalla completa"><?= H::icono('pantalla') ?></button></div>
      <span class="separador"></span>
      <div class="grupo">
        <a class="visor__boton" href="<?= H::e($enl->archivo($d['archivos'][0]['id'], true)) ?>" data-visor-descargar
           aria-label="Descargar <?= $etiquetaFormato($d['archivos'][0]) ?><?= ($p = H::peso($d['archivos'][0]['peso'])) ? ' (' . H::e($p) . ')' : '' ?>"><?= H::icono('descargar') ?></a>
        <button class="visor__boton visor__boton--texto" type="button" data-visor-cerrar><?= H::icono('cerrar') ?>Cerrar</button>
      </div>
    </div>
    <div class="visor__zona" data-visor-zona><!-- el iframe se crea al abrir el visor: los PDF pesan hasta 780 MB --></div>
    <p class="visor__pie"><kbd>Esc</kbd> cerrar</p>
  </section>
  <?php endif ?>

  <?php if ($d['anterior'] || $d['siguiente']): ?>
  <nav class="vecinos" aria-label="Documentos vecinos en la caja">
    <?php if ($v = $d['anterior']): ?><a href="<?= H::e($enl->registro($v)) ?>"><?= H::icono('flechaIzq') ?><span><small>Anterior · <?= H::e($v['codigo']) ?></small><?= H::e(mb_strimwidth($v['titulo'], 0, 60, '…')) ?></span></a><?php endif ?>
    <?php if ($v = $d['siguiente']): ?><a class="sig" href="<?= H::e($enl->registro($v)) ?>"><span><small>Siguiente · <?= H::e($v['codigo']) ?></small><?= H::e(mb_strimwidth($v['titulo'], 0, 60, '…')) ?></span><?= H::icono('flechaDer') ?></a><?php endif ?>
  </nav>
  <?php endif ?>
</div>
<?php $contenido = ob_get_clean();
require __DIR__ . '/_sitio.php';
