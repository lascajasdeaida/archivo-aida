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
 */

require dirname(__DIR__) . '/src/bootstrap.php';

const CREAR_TABLA = "CREATE TABLE IF NOT EXISTS migracion (
  version      CHAR(3)      NOT NULL,
  nombre       VARCHAR(120) NOT NULL,
  checksum     CHAR(40)     NULL,
  duracion_ms  INT UNSIGNED NULL,
  aplicada_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$args = array_slice($argv, 1);
$simular = in_array('--simular', $args, true);
$soloEstado = in_array('--estado', $args, true);
foreach ($args as $a) {
    if (!in_array($a, ['--simular', '--estado'], true)) {
        fwrite(STDERR, "Uso: php bin/migrar.php [--simular | --estado]\n");
        exit(1);
    }
}

$db = aida_db();
$dir = dirname(__DIR__) . '/db/migraciones';

$archivos = [];
foreach (glob("$dir/*.sql") ?: [] as $f) {
    if (preg_match('/^(\d{3})_[a-z0-9_]+\.sql$/', basename($f), $m)) {
        $archivos[$m[1]] = $f;
    } else {
        fwrite(STDERR, 'Se ignora ' . basename($f) . ": el nombre tiene que ser NNN_descripcion.sql\n");
    }
}
ksort($archivos, SORT_STRING);

$hayTabla = (int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'migracion'")->fetchColumn() > 0;
if (!$hayTabla && !$simular && !$soloEstado) {
    $db->exec(CREAR_TABLA);
    $hayTabla = true;
}
$aplicadas = [];
if ($hayTabla) {
    foreach ($db->query('SELECT version, nombre, checksum, aplicada_en FROM migracion') as $r) {
        $aplicadas[$r['version']] = $r;
    }
}

$pendientes = [];
foreach ($archivos as $v => $f) {
    if (!isset($aplicadas[$v])) {
        $pendientes[$v] = $f;
    } elseif ($aplicadas[$v]['checksum'] !== null && $aplicadas[$v]['checksum'] !== sha1_file($f)) {
        echo "Ojo: " . basename($f) . " cambió después de aplicada. Una migración aplicada no se edita: hacé una nueva.\n";
    }
}

if ($soloEstado) {
    foreach ($archivos as $v => $f) {
        echo isset($aplicadas[$v])
            ? sprintf("  aplicada   %s  (%s)\n", basename($f), $aplicadas[$v]['aplicada_en'])
            : sprintf("  PENDIENTE  %s\n", basename($f));
    }
    foreach (array_diff_key($aplicadas, $archivos) as $v => $r) {
        echo "  aplicada   {$r['nombre']}  (el archivo ya no está en db/migraciones)\n";
    }
    exit(0);
}

if (!$pendientes) {
    echo "La base está al día (" . count($aplicadas) . " migraciones aplicadas).\n";
    exit(0);
}

echo ($simular ? 'SIMULACIÓN — no se modifica la base. ' : '') . count($pendientes) . " pendiente(s):\n";
foreach ($pendientes as $v => $f) {
    $sentencias = sentencias((string)file_get_contents($f));
    echo '· ' . basename($f) . ' (' . count($sentencias) . " sentencias)\n";
    if ($simular) {
        foreach ($sentencias as $s) {
            echo '    ' . preg_replace('/\s+/', ' ', mb_strimwidth($s, 0, 160, '…')) . "\n";
        }
        continue;
    }
    $t0 = hrtime(true);
    foreach ($sentencias as $i => $s) {
        try {
            $st = $db->query($s);
            // Vaciar cualquier resultado para que la conexión quede libre (EXECUTE puede devolver filas)
            while ($st->columnCount() && $st->fetch()) {
            }
            $st->closeCursor();
        } catch (PDOException $e) {
            fwrite(STDERR, "  Falló la sentencia " . ($i + 1) . ": " . $e->getMessage() . "\n  " . mb_strimwidth($s, 0, 300, '…') . "\n");
            fwrite(STDERR, "  No se registró la migración. Corregí el problema y volvé a correr (es idempotente).\n");
            exit(1);
        }
    }
    $ms = (int)((hrtime(true) - $t0) / 1e6);
    $db->prepare('INSERT INTO migracion (version, nombre, checksum, duracion_ms) VALUES (?,?,?,?)')
       ->execute([$v, basename($f), sha1_file($f), $ms]);
    echo "  aplicada en {$ms} ms\n";
}

/**
 * Parte un archivo SQL en sentencias. Respeta comillas y comentarios (-- y /* *\/),
 * así un «;» dentro de un texto no corta la sentencia. No admite DELIMITER (no usamos
 * triggers ni procedimientos: muchos hostings no los permiten).
 * @return list<string>
 */
function sentencias(string $sql): array
{
    $out = [];
    $buf = '';
    $n = strlen($sql);
    for ($i = 0; $i < $n; $i++) {
        $c = $sql[$i];
        $sig = $sql[$i + 1] ?? '';
        if ($c === '-' && $sig === '-' && (($sql[$i + 2] ?? ' ') === ' ' || ($sql[$i + 2] ?? '') === "\n")) {
            $fin = strpos($sql, "\n", $i);
            $i = $fin === false ? $n : $fin;
            $buf .= "\n";
            continue;
        }
        if ($c === '/' && $sig === '*') {
            $fin = strpos($sql, '*/', $i + 2);
            $i = $fin === false ? $n : $fin + 1;
            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') {
            $j = $i + 1;
            while ($j < $n) {
                if ($sql[$j] === '\\' && $c !== '`') {
                    $j += 2;
                    continue;
                }
                if ($sql[$j] === $c) {
                    if (($sql[$j + 1] ?? '') === $c) { // comilla duplicada
                        $j += 2;
                        continue;
                    }
                    break;
                }
                $j++;
            }
            $buf .= substr($sql, $i, $j - $i + 1);
            $i = $j;
            continue;
        }
        if ($c === ';') {
            if (trim($buf) !== '') {
                $out[] = trim($buf);
            }
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    if (trim($buf) !== '') {
        $out[] = trim($buf);
    }
    return $out;
}
