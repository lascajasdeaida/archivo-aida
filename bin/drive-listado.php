<?php
declare(strict_types=1);

/**
 * Carga el listado de Drive (ruta, id de Drive, bytes y, opcional, si está compartido) y escanea con él.
 *
 *   php bin/drive-listado.php listado.tsv --simular    muestra qué cambiaría, sin guardar nada (siempre primero)
 *   php bin/drive-listado.php listado.tsv              guarda el listado como vigente y escanea (origen «drive»)
 *
 * Formato en src/Archivos/ListadoDrive.php (TSV o CSV, con o sin encabezado). Mientras no haya acceso a la API de Drive,
 * el listado se arma a mano o con una planilla. Sin --simular, exige 'archivos' => ['origen' => 'drive'] en config.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Aida\Archivos\Origenes;
use Aida\Escaner;

$simular = in_array('--simular', $argv, true);
$archivo = null;
foreach (array_slice($argv, 1) as $a) {
    if ($a !== '' && $a[0] !== '-') {
        $archivo = $a;
    }
}
if ($archivo === null || !is_file($archivo)) {
    fwrite(STDERR, "Uso: php bin/drive-listado.php listado.tsv [--simular]\n");
    exit(2);
}
$cfg = aida_config();
if (!$simular && Origenes::desdeConfig($cfg)->nombre() !== Origenes::DRIVE) {
    fwrite(STDERR, "El origen configurado no es «drive»: se puede simular (--simular), pero para cargar el listado hay que poner\n"
        . "'archivos' => ['origen' => 'drive'] en config/config.php (docs/despliegue.md).\n");
    exit(1);
}
$drive = Origenes::drive($cfg);
try {
    $rep = (new Escaner(aida_db()))->escanear($drive->conListado($archivo), $simular);
    if (!$simular) {
        $drive->guardarListado($archivo, basename($archivo));
    }
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
echo $rep->texto();
if (!$simular) {
    echo "\nListado vigente: {$drive->rutaListado()}\n";
}
