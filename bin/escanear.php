<?php
declare(strict_types=1);

/**
 * Asocia los archivos digitalizados a los registros.
 *
 *   php bin/escanear.php [carpeta] [--simular]          escanea la carpeta (por defecto, archivos_raiz de config)
 *   php bin/escanear.php --listado=archivos.tsv [--simular]
 *
 * El listado sirve cuando los archivos todavía no están en el servidor (p. ej., en Drive):
 * una línea por archivo «ruta<TAB>bytes<TAB>fecha», con la ruta relativa C14/C14R3.pdf.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

$simular = in_array('--simular', $argv, true);
$listado = null;
$carpeta = null;
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--listado=')) {
        $listado = substr($a, 10);
    } elseif ($a[0] !== '-') {
        $carpeta = $a;
    }
}

$esc = new Aida\Escaner(aida_db());
$rep = $listado !== null
    ? $esc->procesarListado($listado, $simular)
    : $esc->escanearCarpeta($carpeta ?? aida_config()['archivos_raiz'], $simular);
echo $rep->texto();
