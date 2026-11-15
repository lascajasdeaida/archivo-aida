<?php
declare(strict_types=1);

/**
 * Recalcula los años del filtro con Texto::anio(), las mismas reglas que la importación (docs/decisiones.md, «Años»):
 * el resumen del registro (anio, anio_hasta, anio_sin_confirmar) y sus años en registro_anio.
 * Por defecto, los registros cargados a mano (origen = 'backoffice'); los del Excel se recalculan en cada importación.
 * Con --todos, también los del Excel (por ejemplo, después de la migración 004 sin volver a importar).
 *
 *   php bin/recalcular-anios.php [--todos] [--simular]
 *
 * Los cambios quedan en el historial dentro de un proceso de tipo `correccion`
 * («Recálculo de años»), que se deshace completo desde Importaciones.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Aida\Anios;
use Aida\Correccion;
use Aida\Historial;
use Aida\Texto;

$args = array_slice($argv, 1);
$simular = in_array('--simular', $args, true);
$todos = in_array('--todos', $args, true);
if (array_diff($args, ['--simular', '--todos'])) {
    fwrite(STDERR, "Uso: php bin/recalcular-anios.php [--todos] [--simular]\n");
    exit(1);
}
$db = aida_db();
$db->beginTransaction();
try {
    $regs = $db->query("SELECT id, codigo, anio_texto, anio, anio_hasta, anio_sin_confirmar FROM registro
                        WHERE " . ($todos ? '' : "origen = 'backoffice' AND ") . "eliminado_en IS NULL ORDER BY caja_id, numero")->fetchAll();
    $cor = new Correccion($db);
    $pid = $cor->abrirProceso($todos ? 'Recálculo de años de todos los registros (bin/recalcular-anios.php --todos)'
                                     : 'Recálculo de años de registros cargados a mano (bin/recalcular-anios.php)');
    $h = new Historial($db, null, 'backoffice', $pid);
    $tabla = new Anios($db);
    $n = 0;
    $soloTabla = 0;
    $sinAnio = 0;
    foreach ($regs as $r) {
        $an = Texto::anio($r['anio_texto']);
        $nuevo = ['anio' => $an['desde'], 'anio_hasta' => $an['hasta'], 'anio_sin_confirmar' => (int)$an['sin_confirmar']];
        $viejo = ['anio' => $r['anio'] === null ? null : (int)$r['anio'], 'anio_hasta' => $r['anio_hasta'] === null ? null : (int)$r['anio_hasta'],
                  'anio_sin_confirmar' => (int)$r['anio_sin_confirmar']];
        if ($an['formato'] === 'ilegible') {
            $sinAnio++;
        }
        $antes = $tabla->leer((int)$r['id']);
        if ($nuevo === $viejo) {
            // Mismo resumen: puede faltar pasar la tabla a la lista real («1998/2006/2007/2008»). Es un derivado, no se audita.
            if ($tabla->sincronizar((int)$r['id'])) {
                $soloTabla++;
                printf("  %-8s «%s»: filtro %s → %s\n", $r['codigo'], (string)$r['anio_texto'],
                    Texto::aniosEtiqueta($antes) ?? 'sin año', Texto::aniosEtiqueta($tabla->leer((int)$r['id'])) ?? 'sin año');
            }
            continue;
        }
        // La importación recalcula los derivados del año igual que acá, así que no hace falta bloquear.
        // Historial pone al día registro_anio (y lo vuelve atrás si se deshace el proceso).
        $h->actualizar('registro', (int)$r['id'], $nuevo, false);
        $n++;
        printf("  %-8s «%s»: %s → %s\n", $r['codigo'], (string)$r['anio_texto'],
            Texto::aniosEtiqueta($antes) ?? 'sin año', Texto::aniosEtiqueta($tabla->leer((int)$r['id'])) ?? 'sin año');
    }
    $cor->cerrarProceso($pid, array_filter(['registros revisados' => count($regs), 'años recalculados' => $n,
        'listas de años puestas al día' => $soloTabla, 'años que no se pudieron leer' => $sinAnio]));
    if ($simular || $n + $soloTabla === 0) {
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
echo ($simular ? 'SIMULACIÓN (no se guardó nada). ' : '') . count($regs) . ($todos ? ' registros' : ' registros cargados a mano')
   . "; $n con el año recalculado; $soloTabla con la lista de años puesta al día"
   . ($sinAnio ? "; $sinAnio con un texto que no es un año" : '') . '.'
   . (!$simular && $n ? " Proceso #$pid (se deshace desde Importaciones)." : '') . "\n";
