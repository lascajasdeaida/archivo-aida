<?php
declare(strict_types=1);

/**
 * Genera la versión liviana de cada PDF (150 dpi, para leer en pantalla) a partir del original en su origen.
 * El original no se toca: queda donde está (Drive o disco) y se ofrece como «Descargar original».
 *
 *   php bin/livianos.php --simular          lista qué generaría (sin bajar nada)
 *   php bin/livianos.php                    genera las que faltan, de a una (se puede cortar y volver a correr)
 *   php bin/livianos.php --limite 5         solo las primeras 5 que falten
 *   php bin/livianos.php --solo C1R9        un registro (o una caja: --solo C1)
 *
 * Necesita Ghostscript (gs) y 'archivos' => ['livianos' => ['raiz' => …]] en config.php.
 * Corre con prioridad baja (nice/ionice): el servidor es compartido.
 * Si la versión liviana no pesa al menos un 20 % menos que el original, no se guarda (no tiene sentido).
 */

require dirname(__DIR__) . '/src/bootstrap.php';

$simular = in_array('--simular', $argv, true);
$limite = null;
$solo = null;
foreach ($argv as $i => $a) {
    if ($a === '--limite') { $limite = (int)($argv[$i + 1] ?? 0); }
    if ($a === '--solo') { $solo = strtoupper((string)($argv[$i + 1] ?? '')); }
}

$cfg = aida_config();
$raiz = rtrim((string)($cfg['archivos']['livianos']['raiz'] ?? ''), '/');
$dpi = (int)($cfg['archivos']['livianos']['dpi'] ?? 150);
if ($raiz === '') {
    fwrite(STDERR, "Falta 'archivos' => ['livianos' => ['raiz' => …]] en config/config.php\n");
    exit(2);
}
$gs = trim((string)shell_exec('command -v gs'));
if ($gs === '' && !$simular) {
    fwrite(STDERR, "No está Ghostscript (gs) instalado en este servidor.\n");
    exit(2);
}

$db = aida_db();
$filas = $db->query("SELECT a.id, a.ruta, a.peso_bytes, a.drive_id, a.liviano_bytes
    FROM archivo_digital a
    WHERE a.presente = 1 AND a.eliminado_en IS NULL AND LOWER(a.formato) = 'pdf'
    ORDER BY a.ruta")->fetchAll(PDO::FETCH_ASSOC);

$pend = [];
foreach ($filas as $f) {
    $codigo = strtoupper(pathinfo($f['ruta'], PATHINFO_FILENAME));
    if ($solo !== null && $codigo !== $solo && !str_starts_with(strtoupper($f['ruta']), $solo . '/')) {
        continue; // --solo C1R9 (un registro) o --solo C1 (una caja)
    }
    if (is_file("$raiz/{$f['ruta']}") || ($f['liviano_bytes'] !== null && (int)$f['liviano_bytes'] === 0)) {
        continue; // ya está, o ya se probó y no convenía (0 = «no achica»)
    }
    $pend[] = $f;
}
if ($limite) {
    $pend = array_slice($pend, 0, $limite);
}
$total = array_sum(array_map(fn($f) => (int)$f['peso_bytes'], $pend));
printf("%d PDF por procesar (%.1f GB en originales)%s\n", count($pend), $total / 1e9, $simular ? ' — SIMULACIÓN' : '');
if ($simular) {
    foreach (array_slice($pend, 0, 20) as $f) {
        printf("  %s  %.0f MB\n", $f['ruta'], $f['peso_bytes'] / 1e6);
    }
    exit(0);
}

$tmp = sys_get_temp_dir() . '/aida-livianos-' . getmypid();
@mkdir($tmp, 0700, true);
$upd = $db->prepare('UPDATE archivo_digital SET liviano_bytes = ?, liviano_en = NOW() WHERE id = ?');
$antes = $despues = 0;
foreach ($pend as $n => $f) {
    $t0 = microtime(true);
    $orig = "$tmp/original.pdf";
    $sal = "$tmp/liviano.pdf";
    @unlink($orig);
    @unlink($sal);
    // 1. Bajar el original
    if (!empty($f['drive_id'])) {
        $ok = bajar('https://drive.usercontent.google.com/download?id=' . rawurlencode($f['drive_id']) . '&export=download&confirm=t', $orig);
    } else {
        $src = rtrim((string)($cfg['archivos']['raiz'] ?? $cfg['archivos_raiz'] ?? ''), '/') . '/' . $f['ruta'];
        $ok = is_file($src) && copy($src, $orig);
    }
    if (!$ok || filesize($orig) < 1000 || file_get_contents($orig, false, null, 0, 5) !== '%PDF-') {
        printf("[%d/%d] %s: no se pudo bajar el original, se saltea\n", $n + 1, count($pend), $f['ruta']);
        continue;
    }
    // 2. Achicar (prioridad baja)
    $cmd = sprintf('nice -n 19 ionice -c3 %s -q -dNOPAUSE -dBATCH -dSAFER -sDEVICE=pdfwrite -dCompatibilityLevel=1.6 -dPDFSETTINGS=/ebook'
        . ' -dDownsampleColorImages=true -dDownsampleGrayImages=true -dColorImageResolution=%2$d -dGrayImageResolution=%2$d'
        . ' -dMonoImageResolution=300 -dColorImageDownsampleType=/Bicubic -dGrayImageDownsampleType=/Bicubic -dJPEGQ=72'
        . ' -dDetectDuplicateImages=true -dFastWebView=true -sOutputFile=%3$s %4$s 2>&1',
        escapeshellarg($gs), $dpi, escapeshellarg($sal), escapeshellarg($orig));
    exec($cmd, $salida, $codigo);
    $po = filesize($orig);
    if ($codigo !== 0 || !is_file($sal) || filesize($sal) < 1000) {
        printf("[%d/%d] %s: Ghostscript falló (%d), se saltea\n", $n + 1, count($pend), $f['ruta'], $codigo);
        continue;
    }
    $pl = filesize($sal);
    if ($pl > $po * 0.8) {
        $upd->execute([0, $f['id']]);
        printf("[%d/%d] %s: no achica lo suficiente (%.0f → %.0f MB), queda solo el original\n", $n + 1, count($pend), $f['ruta'], $po / 1e6, $pl / 1e6);
        continue;
    }
    // 3. Guardar (escritura atómica)
    $dest = "$raiz/{$f['ruta']}";
    @mkdir(dirname($dest), 0755, true);
    rename($sal, "$dest.tmp");
    rename("$dest.tmp", $dest);
    chmod($dest, 0644);
    $upd->execute([$pl, $f['id']]);
    $antes += $po;
    $despues += $pl;
    printf("[%d/%d] %s: %.1f → %.1f MB (%d%%) en %ds\n", $n + 1, count($pend), $f['ruta'], $po / 1e6, $pl / 1e6, round(100 * $pl / $po), microtime(true) - $t0);
}
@unlink("$tmp/original.pdf");
@unlink("$tmp/liviano.pdf");
@rmdir($tmp);
printf("Listo. %.2f GB → %.2f GB en lo procesado.\n", $antes / 1e9, $despues / 1e9);

function bajar(string $url, string $dest): bool
{
    $fp = fopen($dest, 'wb');
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_FILE => $fp, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 20,
                            CURLOPT_TIMEOUT => 3600, CURLOPT_FAILONERROR => true]);
    $ok = curl_exec($ch) !== false;
    fclose($fp);
    return $ok;
}
