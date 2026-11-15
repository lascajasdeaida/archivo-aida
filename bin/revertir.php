<?php
declare(strict_types=1);

/**
 * Deshace cambios registrados en el historial.
 *
 *   php bin/revertir.php --cambio=123 [--forzar]      deshace un cambio puntual (id de auditoria)
 *   php bin/revertir.php --proceso=45 [--forzar]      deshace todo lo que hizo una importación
 *   php bin/revertir.php --ver=registro:C14R3         muestra el historial de un registro
 *
 * Sin --forzar, si un campo cambió de nuevo después, no se aplica nada y se listan los conflictos.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

$db = aida_db();
$opt = getopt('', ['cambio:', 'proceso:', 'ver:', 'forzar']);
$h = new Aida\Historial($db, null, 'backoffice');
$forzar = isset($opt['forzar']);

if (isset($opt['ver'])) {
    [$ent, $cod] = explode(':', $opt['ver']) + [null, null];
    $id = $ent === 'registro'
        ? $db->query('SELECT id FROM registro WHERE codigo = ' . $db->quote((string)$cod))->fetchColumn()
        : $cod;
    $st = $db->prepare('SELECT id, creado_en, origen, proceso_id, accion, cambios, revierte_id, revertido_en
                        FROM auditoria WHERE entidad = ? AND entidad_id = ? ORDER BY id');
    $st->execute([$ent, $id]);
    foreach ($st->fetchAll() as $r) {
        printf("#%d %s %-11s %-12s%s%s\n", $r['id'], $r['creado_en'], $r['origen'], $r['accion'],
            $r['revierte_id'] ? " (deshace #{$r['revierte_id']})" : '', $r['revertido_en'] ? '  [deshecho]' : '');
        foreach (json_decode((string)$r['cambios'], true) ?: [] as $campo => [$a, $d]) {
            $f = fn($v) => $v === null ? '—' : (is_array($v) ? implode(', ', $v) : mb_strimwidth((string)$v, 0, 50, '…'));
            printf("     %-20s %s → %s\n", $campo, $f($a), $f($d));
        }
    }
    exit;
}

if (isset($opt['cambio'])) {
    $db->beginTransaction();
    $r = $h->revertir((int)$opt['cambio'], $forzar);
    $r['ok'] ? $db->commit() : $db->rollBack();
} elseif (isset($opt['proceso'])) {
    $r = $h->revertirProceso((int)$opt['proceso'], $forzar);
} else {
    fwrite(STDERR, "Uso: php bin/revertir.php --cambio=ID | --proceso=ID [--forzar] | --ver=registro:C14R3\n");
    exit(1);
}
echo $r['mensaje'], "\n";
foreach ($r['conflictos'] as $c) {
    echo "  - $c\n";
}
exit($r['ok'] ? 0 : 2);
