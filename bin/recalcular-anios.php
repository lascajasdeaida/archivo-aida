<?php
declare(strict_types=1);

/**
 * Recalcula el año del filtro (anio, anio_hasta, anio_sin_confirmar) de los registros cargados a mano
 * (origen = 'backoffice') con Texto::anio(), las mismas reglas que la importación (docs/decisiones.md, «Años»).
 * Los del Excel ya se recalculan en cada importación.
 *
 *   php bin/recalcular-anios.php [--simular]
 *
 * Los cambios quedan en el historial dentro de un proceso de tipo `correccion`
 * («Recálculo de años»), que se deshace completo desde Importaciones.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Aida\Correccion;
use Aida\Historial;
use Aida\Texto;

$simular = in_array('--simular', array_slice($argv, 1), true);
$db = aida_db();
$db->beginTransaction();
try {
    $regs = $db->query("SELECT id, codigo, anio_texto, anio, anio_hasta, anio_sin_confirmar FROM registro
                        WHERE origen = 'backoffice' AND eliminado_en IS NULL ORDER BY caja_id, numero")->fetchAll();
    $cor = new Correccion($db);
    $pid = $cor->abrirProceso('Recálculo de años de registros cargados a mano (bin/recalcular-anios.php)');
    $h = new Historial($db, null, 'backoffice', $pid);
    $n = 0;
    $sinAnio = 0;
    foreach ($regs as $r) {
        $an = Texto::anio($r['anio_texto']);
        $nuevo = ['anio' => $an['desde'], 'anio_hasta' => $an['hasta'], 'anio_sin_confirmar' => (int)$an['sin_confirmar']];
        $viejo = ['anio' => $r['anio'] === null ? null : (int)$r['anio'], 'anio_hasta' => $r['anio_hasta'] === null ? null : (int)$r['anio_hasta'],
                  'anio_sin_confirmar' => (int)$r['anio_sin_confirmar']];
        if ($an['formato'] === 'ilegible') {
            $sinAnio++;
        }
        if ($nuevo === $viejo) {
            continue;
        }
        // Registros del backoffice: la importación no los toca, así que no hace falta bloquear
        $h->actualizar('registro', (int)$r['id'], $nuevo, false);
        $n++;
        printf("  %-8s «%s»: %s → %s\n", $r['codigo'], (string)$r['anio_texto'],
            Texto::anioEtiqueta($viejo['anio'], $viejo['anio_hasta'], (bool)$viejo['anio_sin_confirmar']) ?? 'sin año',
            Texto::anioEtiqueta($nuevo['anio'], $nuevo['anio_hasta'], (bool)$nuevo['anio_sin_confirmar']) ?? 'sin año');
    }
    $cor->cerrarProceso($pid, array_filter(['registros revisados' => count($regs), 'años recalculados' => $n, 'años que no se pudieron leer' => $sinAnio]));
    if ($simular || $n === 0) {
        $db->rollBack();
    } else {
        $db->commit();
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
echo ($simular ? 'SIMULACIÓN (no se guardó nada). ' : '') . count($regs) . " registros cargados a mano; $n con el año recalculado"
   . ($sinAnio ? "; $sinAnio con un texto que no es un año" : '') . '.'
   . (!$simular && $n ? " Proceso #$pid (se deshace desde Importaciones)." : '') . "\n";
