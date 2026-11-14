<?php
declare(strict_types=1);

/**
 * Importa el Excel del catálogo.
 *
 *   php bin/importar.php ruta/al/catalogo.xlsx [--cajas=1,2,6] [--simular]
 *
 * --simular  corre todo y muestra el reporte, pero no guarda nada.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

$args = array_slice($argv, 1);
$archivo = null;
$cajas = null;
$simular = false;
foreach ($args as $a) {
    if ($a === '--simular') {
        $simular = true;
    } elseif (str_starts_with($a, '--cajas=')) {
        $cajas = array_map('intval', array_filter(explode(',', substr($a, 8))));
    } elseif ($a[0] !== '-') {
        $archivo = $a;
    }
}
if (!$archivo || !is_file($archivo)) {
    fwrite(STDERR, "Uso: php bin/importar.php catalogo.xlsx [--cajas=1,2,6] [--simular]\n");
    exit(1);
}

$rep = (new Aida\Importador(aida_db()))->importar($archivo, $cajas, $simular);
echo $rep->texto();
