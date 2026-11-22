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
 *   php bin/livianos.php --reintentar       vuelve a probar los que antes «no achicaban»
 *
 * Necesita Ghostscript (gs) y 'archivos' => ['livianos' => ['raiz' => …]] en config.php.
 * Corre con prioridad baja (nice/ionice): el servidor es compartido.
 * Si la versión liviana no pesa al menos un 20 % menos que el original, no se guarda (no tiene sentido).
 */

require dirname(__DIR__) . '/src/bootstrap.php';

$simular = in_array('--simular', $argv, true);
$reintentar = in_array('--reintentar', $argv, true);
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
    if (is_file("$raiz/{$f['ruta']}") || (!$reintentar && $f['liviano_bytes'] !== null && (int)$f['liviano_bytes'] === 0)) {
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
    // Segundo intento: PDF que declaran una página enorme (escaneos guardados a 72 o 96 ppi nominales) no se achican por
    // resolución. Ahí se limita por píxeles: el lado mayor de cada imagen a LADO_MAX (≈ A4 a 170 dpi) y se recomprime.
    if ($codigo === 0 && is_file($sal) && filesize($sal) > $po * 0.8 && ($res = resolucionPorPixeles($orig)) !== null) {
        @unlink($sal);
        $cmd2 = str_replace(['-dColorImageResolution=' . $dpi, '-dGrayImageResolution=' . $dpi, '-dJPEGQ=72'],
            ["-dColorImageResolution=$res -dColorImageDownsampleThreshold=1.0 -dPassThroughJPEGImages=false",
             "-dGrayImageResolution=$res -dGrayImageDownsampleThreshold=1.0", '-dJPEGQ=75'], $cmd);
        exec($cmd2, $salida, $codigo);
    }
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

/** Lado mayor permitido, en píxeles, para las imágenes de la versión liviana (≈ A4 a 170 dpi). */
const LADO_MAX = 2000;

/**
 * Resolución a pedirle a Ghostscript para que el lado mayor de las imágenes quede en LADO_MAX píxeles,
 * calculada con el tamaño declarado de la página y el de la imagen más grande. null si no hace falta o no se puede leer.
 */
function resolucionPorPixeles(string $pdf): ?int
{
    $txt = file_get_contents($pdf);
    if ($txt === false || !preg_match('~/MediaBox\s*\[\s*([\d.-]+)\s+([\d.-]+)\s+([\d.]+)\s+([\d.]+)\s*\]~', $txt, $m)) {
        return null;
    }
    $anchoPt = (float)$m[3] - (float)$m[1];
    $altoPt = (float)$m[4] - (float)$m[2];
    $maxW = $maxH = 0;
    // Diccionarios de imagen (sin anidar): /Width y /Height en cualquier orden
    if (preg_match_all('~<<(?:(?!<<|>>).){0,600}?/Subtype\s*/Image(?:(?!<<|>>).){0,600}?>>~s', $txt, $mm)) {
        foreach ($mm[0] as $d) {
            if (preg_match('~/Width\s+(\d+)~', $d, $a) && preg_match('~/Height\s+(\d+)~', $d, $b)) {
                if ((int)$a[1] * (int)$b[1] > $maxW * $maxH) { $maxW = (int)$a[1]; $maxH = (int)$b[1]; }
            }
        }
    }
    unset($txt);
    if ($maxW === 0 || $anchoPt <= 0 || $altoPt <= 0) {
        return null;
    }
    // ppi nominal de la imagen en su página. Puede estar girada (escaneo apaisado en página vertical): se toma
    // la orientación en la que ancho y alto dan el mismo ppi.
    $derecho = [$maxW / ($anchoPt / 72), $maxH / ($altoPt / 72)];
    $girado = [$maxW / ($altoPt / 72), $maxH / ($anchoPt / 72)];
    $dif = fn(array $p) => max($p) / max(0.001, min($p));
    $ppi = array_sum($dif($girado) < $dif($derecho) ? $girado : $derecho) / 2;
    $lado = max($maxW, $maxH);
    if ($lado <= LADO_MAX) {
        return null;
    }
    return max(36, (int)floor($ppi * LADO_MAX / $lado));
}
