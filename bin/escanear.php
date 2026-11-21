<?php
declare(strict_types=1);

/**
 * Asocia los archivos digitalizados a los registros.
 *
 *   php bin/escanear.php [--simular]                    escanea el origen configurado ('archivos.origen'):
 *                                                       disco → la carpeta; drive → el último listado de Drive cargado
 *   php bin/escanear.php carpeta [--simular]            escanea esa carpeta del disco
 *   php bin/escanear.php --listado=archivos.tsv [--simular]
 *
 * El --listado (formato viejo “ruta<TAB>bytes<TAB>fecha”) sirve para probar sin los archivos. Para cargar los id de
 * Drive se usa bin/drive-listado.php.
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
try {
    $rep = match (true) {
        $listado !== null => $esc->procesarListado($listado, $simular),
        $carpeta !== null => $esc->escanearCarpeta($carpeta, $simular),
        default => $esc->escanear(Aida\Archivos\Origenes::desdeConfig(aida_config()), $simular),
    };
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
echo $rep->texto();
