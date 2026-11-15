<?php
declare(strict_types=1);

/**
 * Genera datos.js para los wireframes del sitio público con datos reales de la base.
 * Solo lee (sesión READ ONLY). Deja afuera lo que el sitio no mostraría:
 * registros dados de baja, no publicados y con acceso «a_revisar».
 *
 * Uso: php docs/ux/wireframes/generar-datos.php [ruta/a/config.php]
 */

$rutaConfig = $argv[1] ?? dirname(__DIR__, 3) . '/config/config.php';
if (!is_file($rutaConfig)) {
    fwrite(STDERR, "No encuentro la configuración: $rutaConfig\n");
    exit(1);
}
$c = (require $rutaConfig)['db'];
$db = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'] ?? 3306, $c['name']),
    $c['user'], $c['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$db->exec('SET SESSION TRANSACTION READ ONLY');

function corto(?string $s, int $max): ?string
{
    if ($s === null) return null;
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    if ($s === '') return null;
    return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, $max - 1)) . '…' : $s;
}

function slug(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim(substr($s, 0, 50), '-');
}

$visible = "r.eliminado_en IS NULL AND r.publicado = 1 AND r.acceso <> 'a_revisar'";

// Cajas
$cajas = [];
foreach ($db->query("
    SELECT c.numero, c.nombre, c.resumen,
           COUNT(r.id) regs, COALESCE(SUM(r.digitalizado),0) digit, COALESCE(SUM(r.acceso='restringido'),0) restr,
           MIN(r.anio) desde, MAX(r.anio) hasta
    FROM caja c LEFT JOIN registro r ON r.caja_id = c.id AND $visible
    WHERE c.eliminado_en IS NULL
    GROUP BY c.id ORDER BY c.numero") as $f) {
    $cajas[] = [
        'n' => (int)$f['numero'], 'nombre' => $f['nombre'], 'slug' => $f['numero'] . '-' . slug($f['nombre']),
        'regs' => (int)$f['regs'], 'digit' => (int)$f['digit'], 'restr' => (int)$f['restr'],
        'desde' => $f['desde'] ? (int)$f['desde'] : null, 'hasta' => $f['hasta'] ? (int)$f['hasta'] : null,
        'resumen' => corto($f['resumen'], 700),
    ];
}

// Vocabularios (se resuelven las fusiones)
$vocab = ['tipo_documento' => [], 'descriptor' => []];
foreach ($db->query("
    SELECT v.id, v.tipo, v.nombre, v.estado, COUNT(DISTINCT r.id) n
    FROM vocabulario v
    JOIN registro_vocabulario rv ON rv.vocabulario_id = v.id
    JOIN registro r ON r.id = rv.registro_id AND $visible
    WHERE v.fusionado_en IS NULL
    GROUP BY v.id ORDER BY n DESC, v.nombre") as $f) {
    $vocab[$f['tipo']][] = ['id' => (int)$f['id'], 'nombre' => $f['nombre'], 'n' => (int)$f['n'], 'estado' => $f['estado']];
}

// Registros: solo lo que necesita un listado
$rv = [];
foreach ($db->query("
    SELECT rv.registro_id, COALESCE(v.fusionado_en, v.id) vid, v.tipo
    FROM registro_vocabulario rv JOIN vocabulario v ON v.id = rv.vocabulario_id
    ORDER BY rv.registro_id, rv.orden") as $f) {
    $rv[$f['registro_id']][$f['tipo'] === 'tipo_documento' ? 'tp' : 'ds'][] = (int)$f['vid'];
}
$registros = [];
foreach ($db->query("
    SELECT r.id, r.codigo, c.numero caja, r.numero, r.titulo, r.autor, r.lugar, r.anio, r.anio_texto, r.digitalizado, r.acceso, r.resumen
    FROM registro r JOIN caja c ON c.id = r.caja_id
    WHERE $visible ORDER BY c.numero, r.numero") as $f) {
    $registros[] = [
        'c' => $f['codigo'], 'k' => (int)$f['caja'], 'nr' => (int)$f['numero'],
        't' => corto($f['titulo'], 160),
        'tp' => $rv[$f['id']]['tp'] ?? [], 'ds' => $rv[$f['id']]['ds'] ?? [],
        'a' => $f['anio'] ? (int)$f['anio'] : null, 'at' => $f['anio'] ? null : corto($f['anio_texto'], 30),
        'd' => (int)$f['digitalizado'], 'r' => $f['acceso'] === 'restringido' ? 1 : 0,
        'x' => corto($f['resumen'], 130),
        'au' => corto(trim(($f['autor'] ?? '') . ' ' . ($f['lugar'] ?? '')), 120), // solo para la búsqueda
    ];
}

// Fichas completas para los ejemplos de los wireframes
$ejemplos = ['C14R3', 'C2R11', 'C14R12', 'C2R7'];
$fichas = [];
$st = $db->prepare("
    SELECT r.codigo, c.numero caja, r.numero, r.titulo, r.autor, r.lugar, r.fecha_texto, r.anio, r.anio_texto, r.paginas,
           r.resumen, r.isbn, r.obs_editorial, r.acceso, r.digitalizado,
           (SELECT GROUP_CONCAT(CONCAT(ad.nombre_archivo, '|', COALESCE(ad.peso_bytes,0)) SEPARATOR ';')
              FROM archivo_digital ad WHERE ad.registro_id = r.id AND ad.presente = 1 AND ad.eliminado_en IS NULL) archivos
    FROM registro r JOIN caja c ON c.id = r.caja_id WHERE r.codigo = ?");
foreach ($ejemplos as $cod) {
    $st->execute([$cod]);
    if ($f = $st->fetch()) {
        $f['resumen'] = corto($f['resumen'], 1500);
        $fichas[$cod] = $f;
    }
}

$tot = $db->query("
    SELECT COUNT(*) regs, SUM(digitalizado) digit, SUM(acceso='restringido') restr,
           SUM(titulo IS NULL OR titulo='') sintit, MIN(anio) desde, MAX(anio) hasta, SUM(anio IS NULL) sinanio,
           COALESCE(SUM(paginas),0) paginas
    FROM registro r WHERE $visible")->fetch();

$datos = [
    'generado' => date('Y-m-d H:i'),
    'totales' => array_map('intval', $tot) + ['cajas' => count($cajas)],
    'cajas' => $cajas,
    'tipos' => $vocab['tipo_documento'],
    'descriptores' => $vocab['descriptor'],
    'registros' => $registros,
    'fichas' => $fichas,
];

$salida = __DIR__ . '/datos.js';
file_put_contents($salida,
    "/* Extracto de la base «aida» para los wireframes. Generado por generar-datos.php el {$datos['generado']}.\n" .
    "   Solo lo que mostraría el sitio público. No editar a mano. */\n" .
    'window.AIDA = ' . json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";\n");
printf("datos.js: %d cajas, %d registros, %d tipos, %d descriptores (%s KB)\n",
    count($cajas), count($registros), count($vocab['tipo_documento']), count($vocab['descriptor']),
    round(filesize($salida) / 1024));
