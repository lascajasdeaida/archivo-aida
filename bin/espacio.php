<?php
declare(strict_types=1);

/**
 * Informe de espacio de los archivos digitalizados, para dimensionar el hosting (también en Almacenamiento, backoffice).
 *
 *   php bin/espacio.php          tabla por caja, total, promedios y proyección a todas las cajas
 *   php bin/espacio.php --json   lo mismo en JSON (bytes)
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Aida\Archivos\Espacio;
use Aida\Archivos\Origenes;

$cfg = aida_config();
$inf = (new Espacio(aida_db(), Origenes::cajasTotal($cfg), Origenes::drive($cfg)->visorMaxBytes()))->informe();
if (in_array('--json', $argv, true)) {
    echo json_encode($inf, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
    exit;
}
$g = [Espacio::class, 'gb'];
$t = $inf['total'];
$p = $inf['proyeccion'];
printf("ESPACIO DE LOS ARCHIVOS DIGITALIZADOS (origen: %s)\n\n", Origenes::desdeConfig($cfg)->nombre());
printf("  %-6s %-34s %9s %9s %8s %10s %10s %10s %7s\n", 'Caja', 'Nombre', 'Registros', 'Digitaliz', 'Archivos', 'Espacio', 'Prom./reg', 'Mayor', '>' . $g($inf['pesado']));
foreach ($inf['cajas'] as $c) {
    printf("  %-6s %-34s %9d %9d %8d %10s %10s %10s %7d\n", 'C' . $c['numero'], mb_strimwidth($c['nombre'], 0, 34, '…'),
        $c['registros'], $c['digitalizados'], $c['archivos'], $g($c['bytes']), $g($c['promedio']), $g($c['mayor']), $c['pesados']);
}
printf("\n  Total actual            %s en %d archivos de %d registros (%d de %d cajas)\n", $g($t['bytes']), $t['archivos'], $t['digitalizados'], $t['cajas_con_archivos'], $t['cajas_base']);
printf("  Promedio por registro   %s (por archivo %s, por caja con archivos %s)\n", $g($t['promedio_registro']), $g($t['promedio_archivo']), $g($t['promedio_caja']));
printf("  Archivo más pesado      %s · %d archivos de más de %s (solo descarga con Drive)\n", $g($t['mayor']), $t['pesados'], $g($inf['pesado']));
if ($t['sin_peso']) {
    printf("  Sin peso conocido       %d archivos (no cuentan en el total)\n", $t['sin_peso']);
}
printf("\n  Proyección a %d cajas / %d registros (avance actual %s %%)\n", $p['cajas'], $p['registros'], $p['avance'] === null ? '—' : number_format((float)$p['avance'], 1, ',', '.'));
printf("    por registro          %s  (promedio por registro × %d registros)\n", $g($p['por_registro']), $p['registros']);
printf("    por caja              %s  (promedio por caja × %d cajas)\n", $g($p['por_caja']), $p['cajas']);
printf("    rango                 %s a %s, sin margen ni versiones livianas para web\n", $g($p['desde']), $g($p['hasta']));
