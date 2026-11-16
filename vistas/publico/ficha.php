<?php
/**
 * Ficha de un registro en el sitio público (docs/ux/sitio-publico.md §3 “Ficha”, componentes.md §11, §12, §14).
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
$vecino = fn(?array $v) => $v ? ['href' => $enl->registro($v), 'corto' => (string)$v['codigo'], 'largo' => (string)$v['titulo']] : null;
$paso = ['etiqueta' => 'Documentos vecinos en la caja', 'ant' => $vecino($d['anterior']), 'sig' => $vecino($d['siguiente'])];
$fila = function (string $dt, ?string $dd): string {
    return $dd !== null && $dd !== '' ? "<div><dt>$dt</dt><dd>$dd</dd></div>" : '';
};
$vlinks = fn(array $terminos, string $faceta) => implode(', ', array_map(
    fn($t) => '<a href="' . H::e($enl->explorar([$faceta => $t['slug']])) . '">' . H::e($t['nombre']) . '</a>', $terminos));
$etiquetaFormato = fn(array $a) => in_array($a['formato'], ['pdf'], true) ? 'PDF' : strtoupper($a['formato']);
$citaAnio = $anio['etiqueta'] === null ? 's. f.'
    : ($anio['dudoso'] ? '[' . str_replace(' (sin confirmar)', '', $anio['etiqueta']) . '?]' : $anio['etiqueta']);
$citaAutor = trim((string)($r['autor'] ?? ''));
$citaAutor = ($citaAutor === '' || mb_stripos($citaAutor, 'bortnik') !== false || mb_strlen($citaAutor) > 80) ? 'Bortnik, Aída' : $citaAutor;
$cita = rtrim($citaAutor, '.') . '. ' . rtrim(($d['sinTitulo'] ? '[' . $d['titulo'] . ']' : $d['titulo']), '.') . '. ' . rtrim($citaAnio, '.') . '. Archivo “Las cajas de Aída”, caja '
    . $caja['numero'] . ", $codigo. " . Enlaces::canonica($codigo);
$varias = count($d['archivos']) > 1;
// Archivos que el origen no muestra en línea (Drive con PDF de más de 100 MB): solo descarga, con el peso a la vista
$conVisor = array_values(array_filter($d['archivos'], fn($a) => $a['visor'] ?? true));
$soloDescarga = array_values(array_filter($d['archivos'], fn($a) => !($a['visor'] ?? true)));
ob_start(); ?>
<div class="contenedor">
  <div class="barra-registro">
    <nav class="migas" aria-label="Estás en"><ol>
      <li><a href="<?= H::e($enl->inicio()) ?>">Inicio</a></li>
      <li><a href="<?= H::e($enl->caja($caja)) ?>">Caja <?= (int)$caja['numero'] ?>: <?= H::e($caja['nombre']) ?></a></li>
      <li aria-current="page"><span class="codigo"><?= H::e($codigo) ?></span></li>
    </ol></nav>
    <?php require __DIR__ . '/_paso_registro.php'; ?>
  </div>
  <p class="volver" data-volver hidden><a href="<?= H::e($enl->explorar()) ?>"><?= H::icono('flechaIzq') ?>Volver a los resultados</a></p>

  <header class="cabeza-registro">
    <p class="cabeza-registro__codigo"><span class="codigo"><?= H::e($codigo) ?></span> · Caja <?= (int)$caja['numero'] ?>, registro <?= (int)$r['numero'] ?></p>
    <h1><?= H::nombre($d) ?></h1>
    <?php if ($d['sinTitulo']): ?><p class="meta">Este documento no tiene título en el catálogo; se lo nombra por su tipo.</p><?php endif ?>
    <div class="etqs">
      <?= $d['digital'] ? '<span class="etq etq--digital">' . H::icono('documento') . 'Documento digital</span>'
                        : '<span class="etq etq--ficha">' . H::icono('ficha') . 'Solo ficha</span>' ?>
      <?php if ($d['documento'] === 'pedido'): ?><span class="etq etq--pedido"><?= H::icono('candado') ?>Consulta a pedido</span><?php endif ?>
      <?php if ($anio['dudoso']): ?><span class="etq etq--duda"><?= H::icono('duda') ?>Año sin confirmar</span><?php endif ?>
    </div>
  </header>

  <div class="ficha">
    <aside class="ficha__documento acciones-registro" aria-labelledby="h-doc">
      <h2 id="h-doc">Documento</h2>
      <?php if ($d['documento'] === 'ver'): ?>
        <?php if ($soloDescarga): ?>
        <div class="aviso aviso--ficha" role="note"><?= H::icono('descargar') ?>
          <p><b>Solo descarga</b><?= count($soloDescarga) === count($d['archivos']) && !$varias
              ? 'Este documento pesa ' . H::e((string)H::peso($soloDescarga[0]['peso'])) . ': es demasiado pesado para verlo en línea. Se descarga para leerlo.'
              : 'Algunas partes son demasiado pesadas para verlas en línea (' . H::e(implode(', ', array_map(fn($a) => (string)H::peso($a['peso']), $soloDescarga))) . '). Se descargan para leerlas.' ?></p></div>
        <?php endif ?>
        <ul class="acciones-registro__lista">
          <?php $primero = true; foreach ($d['archivos'] as $i => $a): if (!$a['visor']) { continue; } $peso = H::peso($a['peso']); ?>
          <li><a class="boton <?= $primero ? 'boton--primario' : 'boton--secundario' ?> boton--ancho" href="<?= H::e($enl->archivo($a['id'])) ?>"
               data-visor="<?= H::e($enl->archivo($a['id'])) ?>" data-visor-titulo="<?= H::e($codigo . ($varias ? ', parte ' . ($i + 1) : '')) ?>"
               data-descarga="<?= H::e($enl->archivo($a['id'], true)) ?>" aria-controls="visor" aria-expanded="false">
              <?= H::icono('ver') ?><?= $varias ? 'Ver parte ' . ($i + 1) : 'Ver documento' ?><?php if (!$varias && $r['paginas']): ?> <span class="peso">(<?= (int)$r['paginas'] ?> págs.<?= $peso ? ', ' . H::e($peso) : '' ?>)</span><?php elseif ($peso): ?> <span class="peso">(<?= H::e($peso) ?>)</span><?php endif ?></a></li>
          <?php $primero = false; endforeach ?>
          <?php foreach ($d['archivos'] as $i => $a): $peso = H::peso($a['peso']); ?>
          <li><a class="boton <?= !$a['visor'] && !$conVisor && $i === 0 ? 'boton--primario' : 'boton--secundario' ?> boton--ancho" href="<?= H::e($enl->archivo($a['id'], true)) ?>">
              <?= H::icono('descargar') ?>Descargar <?= $etiquetaFormato($a) ?><?= $varias ? ' (parte ' . ($i + 1) . ')' : '' ?><?php if ($peso): ?> <span class="peso">(<?= H::e($peso) ?>)</span><?php endif ?></a></li>
          <?php endforeach ?>
        </ul>
      <?php elseif ($d['documento'] === 'pedido'): ?>
        <div class="aviso aviso--pedido" role="note"><?= H::icono('candado') ?>
          <p><b>Consulta a pedido</b>La ficha es pública; el documento se pide al archivo.
            <?= $d['digital'] ? 'Está digitalizado.' : 'Todavía no está digitalizado.' ?> Te respondemos por correo, sin crear una cuenta.</p></div>
        <ul class="acciones-registro__lista">
          <li><a class="boton boton--primario boton--ancho" href="<?= H::e($enl->consulta($codigo)) ?>"><?= H::icono('sobre') ?>Pedir consulta</a></li>
        </ul>
      <?php else: ?>
        <div class="aviso aviso--ficha" role="note"><?= H::icono('ficha') ?>
          <p><b>Todavía no está digitalizado</b>Por ahora podés ver la ficha del catálogo.</p></div>
        <ul class="acciones-registro__lista">
          <li><a class="boton boton--secundario boton--ancho" href="<?= H::e($enl->consulta($codigo, 'original')) ?>"><?= H::icono('sobre') ?>Consultar por este documento</a></li>
        </ul>
      <?php endif ?>
      <ul class="acciones-registro__lista acciones-registro__lista--sec" aria-label="Citar y compartir">
        <li><button class="boton boton--secundario boton--ancho" type="button" data-copiar="#cita" data-copiado="Cita copiada."><?= H::icono('copiar') ?>Copiar cita</button></li>
        <li><button class="boton boton--secundario boton--ancho" type="button" data-copiar-texto="<?= H::e(Enlaces::canonica($codigo)) ?>" data-copiado="Enlace copiado."><?= H::icono('enlace') ?>Copiar enlace</button></li>
      </ul>
      <p role="status" class="meta" id="copiado"></p>
    </aside>

    <div class="ficha__datos">
      <h2>Ficha del catálogo</h2>
      <dl class="catalogo">
        <?= $fila('Código', '<span class="codigo">' . H::e($codigo) . '</span>') ?>
        <?= $fila('Título', $d['sinTitulo'] ? '<span class="sin-titulo">Sin título</span>' : H::e($d['titulo'])) ?>
        <?= $fila('Tipo de documento', $vlinks($d['tipos'], 'tipo')) ?>
        <?= $fila('Autor/es', H::e($r['autor'])) ?>
        <?= $fila('Fecha', H::e($r['fecha_texto'])) ?>
        <?= $fila('Año', $anio['etiqueta'] === null ? null
            : ($anio['decada'] !== null && !$anio['dudoso'] ? '<a href="' . H::e($enl->explorar(['decada' => $anio['decada']])) . '">' . H::e($anio['etiqueta']) . '</a>' : H::anio($anio))
              . ($anio['original'] !== null ? '<br><span class="meta">En el catálogo: “' . H::e($anio['original']) . '”</span>' : '')) ?>
        <?= $fila('Lugar', H::e($r['lugar'])) ?>
        <?= $fila('Páginas', $r['paginas'] ? (string)(int)$r['paginas'] : null) ?>
        <?= $fila('Descriptores', $vlinks($d['descriptores'], 'descriptor')) ?>
        <?= $fila('ISBN', H::e($r['isbn'])) ?>
        <?= $fila('Caja', '<a href="' . H::e($enl->caja($caja)) . '">Caja ' . (int)$caja['numero'] . ': ' . H::e($caja['nombre']) . '</a>') ?>
        <?= $fila('Acceso', $d['documento'] === 'pedido' ? 'Consulta a pedido' : 'Libre') ?>
      </dl>

      <h2>Descripción</h2>
      <div class="ficha__texto">
      <?php $resumen = trim((string)$r['resumen']); ?>
      <?php if ($resumen !== ''): foreach (preg_split('/\R{2,}/u', $resumen) as $parrafo): ?>
        <p><?= nl2br(H::e(trim($parrafo))) ?></p>
      <?php endforeach; else: ?><p class="meta">Sin descripción.</p><?php endif ?>
      <?php if (trim((string)$r['obs_editorial']) !== ''): ?><h3>Observaciones</h3><p><?= nl2br(H::e(trim((string)$r['obs_editorial']))) ?></p><?php endif ?>
      </div>

      <h2 id="citar">Cómo citar</h2>
      <p class="cita" id="cita"><?= H::e($cita) ?></p>
    </div>
  </div>

  <?php if ($d['documento'] === 'ver' && $conVisor): ?>
  <section id="visor" class="visor" hidden aria-label="Visor del documento <?= H::e($codigo) ?>">
    <div class="visor__barra" role="toolbar" aria-label="Controles del visor">
      <div class="grupo"><button class="visor__boton" type="button" data-visor-pantalla aria-label="Pantalla completa"><?= H::icono('pantalla') ?></button></div>
      <span class="separador"></span>
      <div class="grupo">
        <a class="visor__boton" href="<?= H::e($enl->archivo($conVisor[0]['id'], true)) ?>" data-visor-descargar
           aria-label="Descargar <?= $etiquetaFormato($conVisor[0]) ?><?= ($p = H::peso($conVisor[0]['peso'])) ? ' (' . H::e($p) . ')' : '' ?>"><?= H::icono('descargar') ?></a>
        <button class="visor__boton visor__boton--texto" type="button" data-visor-cerrar><?= H::icono('cerrar') ?>Cerrar</button>
      </div>
    </div>
    <div class="visor__zona" data-visor-zona tabindex="0" role="region" aria-label="Página del documento (se desplaza con las flechas)"><!-- el iframe se crea al abrir el visor: los PDF pesan hasta 780 MB --></div>
    <p class="visor__pie"><kbd>Esc</kbd> cerrar</p>
  </section>
  <?php endif ?>

  <div class="pie-registro"><?php $paso['etiqueta'] .= ' (al pie)'; require __DIR__ . '/_paso_registro.php'; ?></div>
</div>
<?php $contenido = ob_get_clean();
require __DIR__ . '/_sitio.php';
