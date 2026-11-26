<?php
declare(strict_types=1);

/**
 * Aplica las migraciones pendientes de db/migraciones/ (NNN_descripcion.sql), en orden.
 *
 *   php bin/migrar.php              aplica las pendientes
 *   php bin/migrar.php --simular    muestra qué se aplicaría y sus sentencias, sin tocar la base
 *   php bin/migrar.php --estado     lista aplicadas y pendientes
 *
 * Lo aplicado queda en la tabla `migracion`. Cada migración es idempotente: si una se corta
 * a mitad de camino, se vuelve a correr entera sin romper nada.
 * Una base creada con db/schema.sql ya trae registradas las migraciones que incluye.
 * Sin consola, se aplican desde el backoffice: Base de datos (solo administradores).
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Aida\Migrador;

$args = array_slice($argv, 1);
$simular = in_array('--simular', $args, true);
$soloEstado = in_array('--estado', $args, true);
foreach ($args as $a) {
    if (!in_array($a, ['--simular', '--estado'], true)) {
        fwrite(STDERR, "Uso: php bin/migrar.php [--simular | --estado]\n");
        exit(1);
    }
}

$m = new Migrador(aida_db());
foreach ($m->ignorados as $f) {
    fwrite(STDERR, "Se ignora $f: el nombre tiene que ser NNN_descripcion.sql\n");
}
foreach ($m->modificadas() as $f) {
    echo "Ojo: $f cambió después de aplicada. Una migración aplicada no se edita: hacé una nueva.\n";
}
$aplicadas = $m->aplicadas();

if ($soloEstado) {
    foreach ($m->archivos as $v => $f) {
        echo isset($aplicadas[$v])
            ? sprintf("  aplicada   %s  (%s)\n", basename($f), $aplicadas[$v]['aplicada_en'])
            : sprintf("  PENDIENTE  %s\n", basename($f));
    }
    foreach (array_diff_key($aplicadas, $m->archivos) as $r) {
        echo "  aplicada   {$r['nombre']}  (el archivo ya no está en db/migraciones)\n";
    }
    exit(0);
}

$pendientes = $m->pendientes();
if (!$pendientes) {
    echo "La base está al día (" . count($aplicadas) . " migraciones aplicadas).\n";
    exit(0);
}

echo ($simular ? 'SIMULACIÓN — no se modifica la base. ' : '') . count($pendientes) . " pendiente(s):\n";
foreach ($pendientes as $v => $f) {
    $sentencias = Migrador::sentencias((string)file_get_contents($f));
    echo '· ' . basename($f) . ' (' . count($sentencias) . " sentencias)\n";
    if ($simular) {
        foreach ($sentencias as $s) {
            echo '    ' . preg_replace('/\s+/', ' ', mb_strimwidth($s, 0, 160, '…')) . "\n";
        }
        continue;
    }
    try {
        echo '  aplicada en ' . $m->aplicar($v) . " ms\n";
    } catch (RuntimeException $e) {
        fwrite(STDERR, '  ' . $e->getMessage() . "\n  No se registró la migración. Corregí el problema y volvé a correr (es idempotente).\n");
        exit(1);
    }
}
