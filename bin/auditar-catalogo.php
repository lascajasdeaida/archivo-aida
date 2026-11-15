<?php
declare(strict_types=1);

/**
 * Auditoría de calidad del catálogo. Solo lee la base: no modifica nada.
 * Pensado para correrlo después de cada importación y pasarle el informe al PM
 * o al equipo de digitalización.
 *
 *   php bin/auditar-catalogo.php                       informe en Markdown por pantalla
 *   php bin/auditar-catalogo.php --salida=informe.md   lo guarda en un archivo
 *   php bin/auditar-catalogo.php --ejemplos=20         cantidad de ejemplos por hallazgo (por defecto 12)
 *
 * Sale con código 0 aunque haya hallazgos: el informe es para revisar, no para cortar un proceso.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Aida\Texto;

$opt = getopt('', ['salida:', 'ejemplos:']);
$maxEj = max(1, (int)($opt['ejemplos'] ?? 12));

$db = aida_db();
// Garantía extra de solo lectura para toda la sesión
$db->exec('SET SESSION TRANSACTION READ ONLY');
$db->beginTransaction();

/** Consulta preparada → filas. */
$q = static function (string $sql, array $p = []) use ($db): array {
    $st = $db->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
};

// ---------------------------------------------------------------------
// Datos base (el catálogo es chico: se carga entero y se analiza en PHP)
// ---------------------------------------------------------------------
$cajas = $q('SELECT c.id, c.numero, c.nombre, c.nombre_pestania, c.resumen,
                    (SELECT COUNT(*) FROM registro r WHERE r.caja_id = c.id AND r.eliminado_en IS NULL) registros
             FROM caja c WHERE c.eliminado_en IS NULL ORDER BY c.numero');
$cajaNum = array_column($cajas, 'numero', 'id');

$registros = $q('SELECT r.id, r.caja_id, r.numero, r.codigo, r.titulo, r.autor, r.fecha_texto, r.anio, r.anio_texto
                 FROM registro r JOIN caja c ON c.id = r.caja_id
                 WHERE r.eliminado_en IS NULL AND c.eliminado_en IS NULL
                 ORDER BY c.numero, r.numero');

$vocab = $q('SELECT v.id, v.tipo, v.nombre, v.clave, v.estado, v.fusionado_en, d.tipo destino_tipo,
                    (SELECT COUNT(*) FROM registro_vocabulario rv JOIN registro r ON r.id = rv.registro_id
                     WHERE rv.vocabulario_id = v.id AND r.eliminado_en IS NULL) usos
             FROM vocabulario v LEFT JOIN vocabulario d ON d.id = v.fusionado_en
             ORDER BY v.tipo, v.clave');

$usosPorRegistro = [];
foreach ($q('SELECT rv.registro_id, v.tipo, v.nombre FROM registro_vocabulario rv
             JOIN vocabulario v ON v.id = rv.vocabulario_id ORDER BY rv.registro_id, v.tipo, rv.orden') as $f) {
    $usosPorRegistro[(int)$f['registro_id']][$f['tipo']][] = $f['nombre'];
}
$codigosPorTermino = [];
foreach ($q('SELECT rv.vocabulario_id, r.codigo FROM registro_vocabulario rv JOIN registro r ON r.id = rv.registro_id
             WHERE r.eliminado_en IS NULL ORDER BY r.caja_id, r.numero') as $f) {
    $codigosPorTermino[(int)$f['vocabulario_id']][] = $f['codigo'];
}

$db->commit();

// ---------------------------------------------------------------------
// Utilidades de presentación
// ---------------------------------------------------------------------
$md = [];
$h = static function (string $t, int $n = 2) use (&$md): void { $md[] = ''; $md[] = str_repeat('#', $n) . ' ' . $t; $md[] = ''; };
$p = static function (string $t) use (&$md): void { $md[] = $t; };
$celda = static fn(?string $s, int $max = 70): string =>
    $s === null ? '—' : str_replace(['|', "\n", "\r"], ['\|', ' ', ' '], mb_strimwidth($s, 0, $max, '…'));
$ejemplos = static function (array $codigos) use ($maxEj): string {
    $codigos = array_values(array_unique($codigos));
    $n = count($codigos);
    $txt = implode(', ', array_slice($codigos, 0, $maxEj));
    return $n > $maxEj ? $txt . ' … (+' . ($n - $maxEj) . ')' : $txt;
};
$tabla = static function (array $encabezados, array $filas) use (&$md): void {
    if (!$filas) {
        $md[] = '_Sin hallazgos._';
        return;
    }
    $md[] = '| ' . implode(' | ', $encabezados) . ' |';
    $md[] = '|' . str_repeat('---|', count($encabezados));
    foreach ($filas as $f) {
        $md[] = '| ' . implode(' | ', array_map('strval', $f)) . ' |';
    }
};
/** Clave para comparar nombres: sin tildes, mayúsculas, comillas ni signos. */
$base = static function (?string $s): string {
    $s = Texto::clave((string)$s);
    $s = (string)preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $s);
    return trim((string)preg_replace('/\s+/u', ' ', $s));
};
$resumen = [];

// =====================================================================
// 1. Cajas
// =====================================================================
$h('1. Cajas');

// 1.a Nombres parecidos, clasificados
$sinNumeral = static fn(string $b): string => trim((string)preg_replace('/(\s+(i{1,3}|iv|vi{0,3}|\d+[a-z]?))+$/u', '', $b));
$sinParen = static fn(string $s): string => trim((string)preg_replace('/\([^)]*\)/u', '', $s));
$grupos = [];
$iguales = [];
$n = count($cajas);
for ($i = 0; $i < $n; $i++) {
    for ($j = $i + 1; $j < $n; $j++) {
        $a = $cajas[$i];
        $b = $cajas[$j];
        $ba = $base($a['nombre']);
        $bb = $base($b['nombre']);
        if ($ba === '' || $bb === '') {
            continue;
        }
        $pa = $base($sinParen($a['nombre']));
        $pb = $base($sinParen($b['nombre']));
        $clase = null;
        if ($a['nombre'] === $b['nombre']) {
            $iguales[$a['nombre']][$a['numero']] = $b['numero'] ;
            $iguales[$a['nombre']][$b['numero']] = $a['numero'];
            continue;
        } elseif ($ba === $bb) {
            $clase = 'Misma obra, difiere solo en tildes, mayúsculas o comillas: unificar la grafía.';
        } elseif ($sinNumeral($ba) === $sinNumeral($bb)) {
            $clase = 'Misma obra en varias cajas numeradas (I, II…): **no unificar**, el numeral distingue las cajas.';
        } elseif ($pa !== '' && $pa === $pb) {
            $clase = 'Mismo título con aclaración distinta entre paréntesis: probablemente **versiones distintas**. No unificar sin revisar.';
        } elseif (mb_strlen($ba) >= 6 && mb_strlen($bb) >= 6
            && levenshtein($ba, $bb) / max(strlen($ba), strlen($bb)) <= 0.2) {
            $clase = 'Difieren en una o dos letras: parece error de tipeo de la misma obra. Unificar la grafía.';
        }
        if ($clase !== null) {
            $grupos[$clase][] = [$a, $b];
        }
    }
}
// Cajas que su propio resumen vincula («Cajas 3 y 5», «Cajas 23 y 24»)
$vinculadas = [];
foreach ($cajas as $c) {
    if (preg_match_all('/cajas?\s*(?:n[º°o]\s*)?(\d+)\s*y\s*(\d+)/iu', (string)$c['resumen'], $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            $vinculadas[(int)$x[1] . '-' . (int)$x[2]] = [(int)$x[1], (int)$x[2], (int)$c['numero']];
        }
    }
}
$porNumero = array_column($cajas, null, 'numero');
$h('1.a Nombres de cajas parecidos', 3);
$p('Pares de cajas con nombres iguales o parecidos, agrupados por lo que conviene hacer.');
$filas = [];
foreach ($grupos as $clase => $pares) {
    foreach ($pares as [$a, $b]) {
        $ev = [];
        foreach ($vinculadas as [$x, $y, $en]) {
            if (in_array((int)$a['numero'], [$x, $y], true) && in_array((int)$b['numero'], [$x, $y], true)) {
                $ev[] = "el resumen de la caja $en las presenta juntas";
            }
        }
        $filas[] = ["Caja {$a['numero']} «" . $celda($a['nombre'], 50) . "»", "Caja {$b['numero']} «" . $celda($b['nombre'], 50) . "»",
                    $clase, $ev ? implode('; ', $ev) : '—'];
    }
}
$tabla(['Caja', 'Caja', 'Diagnóstico', 'Evidencia en el resumen'], $filas);
$resumen['Pares de cajas con nombre parecido'] = count($filas);
if ($iguales) {
    $p('');
    $p('Cajas con el mismo nombre exacto (varias cajas de una misma obra; no hay nada que unificar): '
        . implode('; ', array_map(fn($nom, $cs) => '«' . $nom . '» (cajas ' . implode(', ', array_keys($cs)) . ')',
            array_keys($iguales), $iguales)) . '.');
}

$h('1.b Cajas que el resumen presenta juntas con nombres distintos', 3);
$filas = [];
foreach ($vinculadas as [$x, $y, $en]) {
    if (!isset($porNumero[$x], $porNumero[$y]) || $base($porNumero[$x]['nombre']) === $base($porNumero[$y]['nombre'])) {
        continue;
    }
    $filas[] = ["Cajas $x y $y", '«' . $celda($porNumero[$x]['nombre'], 45) . '» / «' . $celda($porNumero[$y]['nombre'], 45) . '»', "resumen de la caja $en"];
}
$tabla(['Cajas', 'Nombres', 'Dónde lo dice'], $filas);

// 1.c Nombre de la caja vs. título de la pestaña
$h('1.c Nombre de la caja distinto del título de su pestaña', 3);
$p('La pestaña del Excel se llama «Caja N Nombre SIGLA». Si el nombre de la columna B no coincide con el de la pestaña, uno de los dos tiene un error o la obra tiene dos títulos.');
$filas = [];
foreach ($cajas as $c) {
    $pest = (string)preg_replace('/^\s*caja\s*\d+\s*/iu', '', (string)$c['nombre_pestania']);
    $pest = trim((string)preg_replace('/\s+[A-ZÁÉÍÓÚÑ]{1,6}\.?$/u', '', trim($pest))); // sigla del responsable (SDF, GTF, PM, MTIT…)
    if ($pest === '' && $base($c['nombre']) !== 'sin nombre') {
        continue;
    }
    if (preg_match('/^[A-Z0-9]{2,}(\s+[A-Z0-9]{2,})*$/u', $pest)) {
        continue; // la pestaña solo tiene siglas del título (SYS, NDUS, TSM50A…)
    }
    $junto = static fn(string $s): string => str_replace([' ', 'ª'], ['', 'a'], $base(str_replace('ª', 'a', $s)));
    $bp = $junto($pest);
    $bn = $junto($c['nombre']);
    // La pestaña de Excel tiene 31 caracteres como máximo: se compara contra el comienzo del nombre
    $pref = mb_substr($bn, 0, mb_strlen($bp));
    if ($bp !== '' && $bn !== '' && levenshtein($bp, $pref) / max(1, strlen($bp)) <= 0.2) {
        continue;
    }
    $filas[] = ["Caja {$c['numero']}", '«' . $celda($c['nombre'], 60) . '»', '«' . $celda($c['nombre_pestania'], 40) . '»'];
}
$tabla(['Caja', 'Nombre (col. B)', 'Pestaña'], $filas);
$resumen['Cajas con nombre distinto de la pestaña'] = count($filas);

// 1.d Grafía del nombre
$h('1.d Grafía de los nombres de caja', 3);
$filas = [];
foreach ($cajas as $c) {
    $nom = (string)$c['nombre'];
    $obs = [];
    $letras = preg_match_all('/\p{L}/u', $nom);
    if ($letras > 3 && $letras === preg_match_all('/\p{Lu}/u', $nom)) {
        $obs[] = 'todo en mayúsculas';
    }
    if (preg_match('/\?/u', $nom) && !preg_match('/¿/u', $nom)) {
        $obs[] = 'signo de pregunta sin abrir (¿)';
    }
    if (preg_match('/[ìèòàù]/u', $nom)) {
        $obs[] = 'tilde invertida (ì, è…)';
    }
    if (preg_match('/^["“«].*["”»]/u', $nom) || preg_match('/["“”]/u', $nom)) {
        $obs[] = 'comillas en el nombre';
    }
    if (in_array($base($nom), ['sin nombre', 'sin titulo', 's n', ''], true)) {
        $obs[] = 'sin nombre';
    }
    if (preg_match('/\bFel[íi]z\b/u', $nom) && str_contains($nom, 'í')) {
        $obs[] = '«Felíz» lleva «Feliz» (sin tilde)';
    }
    if ($obs) {
        $filas[] = ["Caja {$c['numero']}", '«' . $celda($nom, 60) . '»', implode('; ', $obs)];
    }
}
$tabla(['Caja', 'Nombre', 'Observación'], $filas);

// 1.e Resumen de caja que no es un resumen
$h('1.e Resumen de caja vacío o que no es un resumen', 3);
$filas = [];
$sinResumen = [];
foreach ($cajas as $c) {
    $r = trim((string)$c['resumen']);
    if ($r === '') {
        $sinResumen[] = (string)$c['numero'];
    } elseif (mb_strlen($r) < 40) {
        $filas[] = ["Caja {$c['numero']}", '«' . $celda($r, 60) . '»'];
    }
}
$tabla(['Caja', 'Resumen'], $filas);
$p('');
$p('Cajas sin resumen (' . count($sinResumen) . '): ' . ($sinResumen ? implode(', ', $sinResumen) : '—') . '.');
$resumen['Cajas sin resumen'] = count($sinResumen);

// =====================================================================
// 2. Registros: campos básicos
// =====================================================================
$h('2. Registros sin datos básicos');
$sinTituloNiTipo = $sinTitulo = $sinTipo = $sinDesc = $sinAutor = [];
foreach ($registros as $r) {
    $u = $usosPorRegistro[(int)$r['id']] ?? [];
    $tit = Texto::limpio($r['titulo']);
    if ($tit === null) {
        $sinTitulo[] = $r['codigo'];
    }
    if (empty($u['tipo_documento'])) {
        $sinTipo[] = $r['codigo'];
        if ($tit === null) {
            $sinTituloNiTipo[] = $r['codigo'];
        }
    }
    if (empty($u['descriptor'])) {
        $sinDesc[] = $r['codigo'];
    }
    if (Texto::limpio($r['autor']) === null) {
        $sinAutor[] = $r['codigo'];
    }
}
$tabla(['Situación', 'Registros', 'Ejemplos'], [
    ['**Sin título ni tipo de documento** (la ficha queda sin encabezado)', count($sinTituloNiTipo), $ejemplos($sinTituloNiTipo)],
    ['Sin tipo de documento', count($sinTipo), $ejemplos($sinTipo)],
    ['Sin título (la ficha muestra el tipo)', count($sinTitulo), $ejemplos($sinTitulo)],
    ['Sin descriptores', count($sinDesc), $ejemplos($sinDesc)],
    ['Sin autor', count($sinAutor), $ejemplos($sinAutor)],
]);
$resumen['Registros sin título ni tipo'] = count($sinTituloNiTipo);
$resumen['Registros sin tipo'] = count($sinTipo);
$resumen['Registros sin descriptores'] = count($sinDesc);

// Títulos con dudas o grafías dispares dentro de la misma caja
$h('2.a Títulos con marcas de duda', 3);
$dudas = [];
foreach ($registros as $r) {
    if (preg_match('/\?{2,}|\(\s*¿?\?\s*\)/u', (string)$r['titulo'])) {
        $dudas[] = [$r['codigo'], '«' . $celda($r['titulo'], 60) . '»'];
    }
}
$tabla(['Registro', 'Título'], array_slice($dudas, 0, $maxEj * 2));

$h('2.b Mismo título escrito de formas distintas dentro de una caja', 3);
$p('Mismo título salvo mayúsculas, tildes, comillas o signos. Conviene unificar para que la búsqueda y el listado los agrupen.');
$porCaja = [];
foreach ($registros as $r) {
    if (Texto::limpio($r['titulo']) !== null) {
        $porCaja[$r['caja_id']][$base($r['titulo'])][trim((string)$r['titulo'])][] = $r['codigo'];
    }
}
$filas = [];
foreach ($porCaja as $cid => $titulos) {
    foreach ($titulos as $variantes) {
        if (count($variantes) < 2) {
            continue;
        }
        $txt = [];
        foreach ($variantes as $t => $cods) {
            $txt[] = '«' . $celda($t, 40) . '» (' . $ejemplos($cods) . ')';
        }
        $filas[] = ['Caja ' . $cajaNum[$cid], implode(' / ', $txt)];
    }
}
$tabla(['Caja', 'Variantes'], $filas);
$resumen['Títulos con grafías dispares en la misma caja'] = count($filas);

// =====================================================================
// 3. Años y fechas
// =====================================================================
$h('3. Años y fechas');
$p('`anio` solo se completa cuando la columna Año trae un año limpio (es el que filtra el sitio). El resto queda en `anio_texto`.');
$cat = [
    'Año único con duda («1972?», «¿1984?», «1989???»)' => [],
    'Rango o varios años («1981/82», «2004/ 2005», «Entre 1983 y 1986»)' => [],
    'Fecha completa en la columna Año («03/02/2006»)' => [],
    'Solo signos o «sin fecha»' => [],
    'Texto que no es un año («Año XXII de la publicación»)' => [],
];
$ejTexto = [];
$fueraDeRango = [];
$distintoDeFecha = [];
$fechaInvertida = [];
foreach ($registros as $r) {
    $at = $r['anio_texto'] === null ? null : trim((string)$r['anio_texto']);
    if ($r['anio'] === null && $at !== null && $at !== '') {
        $k = Texto::clave($at);
        $anios = preg_match_all('/\b(1[89]\d\d|20\d\d)\b/u', $at, $mm) ? $mm[1] : [];
        if (!preg_match('/[\p{L}\p{N}]/u', $at) || preg_match('/^(sin|no tiene|s\/)\s*(fecha|dato|precisar)/u', $k)) {
            $c = 'Solo signos o «sin fecha»';
        } elseif (preg_match('#\b\d{1,2}/\d{1,2}/\d{4}\b#', $at)) {
            $c = 'Fecha completa en la columna Año («03/02/2006»)';
        } elseif (count(array_unique($anios)) === 1 && preg_match('/^[¿(\s]*\d{4}[\s?¿()]*$/u', $at)) {
            $c = 'Año único con duda («1972?», «¿1984?», «1989???»)';
        } elseif ($anios || preg_match('#\d{4}\s*[/\-y,.]\s*\d{2}#u', $at)) {
            $c = 'Rango o varios años («1981/82», «2004/ 2005», «Entre 1983 y 1986»)';
        } else {
            $c = 'Texto que no es un año («Año XXII de la publicación»)';
        }
        $cat[$c][] = $r['codigo'];
        $ejTexto[$c][$at] = true;
    }
    $anio = $r['anio'] === null ? null : (int)$r['anio'];
    if ($anio !== null && ($anio < 1950 || $anio > (int)date('Y'))) {
        $fueraDeRango[] = [$r['codigo'], $anio, $celda($r['fecha_texto'], 50)];
    }
    // Año de la fecha del documento que no coincide con la columna Año
    if ($anio !== null && $r['fecha_texto'] !== null
        && preg_match_all('/\b(19[3-9]\d|20[0-2]\d)\b/u', (string)$r['fecha_texto'], $mf)) {
        $af = array_map('intval', array_unique($mf[1]));
        if (count($af) === 1 && abs($af[0] - $anio) >= 1) {
            $diag = 'revisar cuál de los dos vale';
            if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', trim((string)$r['fecha_texto']), $mx) && (int)$mx[1] === $anio % 100) {
                $diag = sprintf('parece cargada como aa/mm/dd y leída como dd/mm/aa: sería %s/%s/%d', substr($mx[3], 2), $mx[2], $anio);
                $fechaInvertida[] = $r['codigo'];
            }
            $distintoDeFecha[] = [$r['codigo'], $anio, '«' . $celda($r['fecha_texto'], 50) . '»', $diag];
        }
    }
}
$filas = [];
foreach ($cat as $c => $cods) {
    $vals = array_keys($ejTexto[$c] ?? []);
    $filas[] = [$c, count($cods), $ejemplos($cods), $celda(implode(' · ', array_slice($vals, 0, 6)), 90)];
}
$tabla(['Año sin valor filtrable', 'Registros', 'Ejemplos', 'Valores encontrados'], $filas);
$resumen['Registros con año no filtrable'] = array_sum(array_map('count', $cat));

$h('3.a Año distinto del que dice la fecha del documento', 3);
$p(sprintf('De estos, %d tienen el patrón «el día coincide con el año»: la fecha se escribió como aa/mm/dd (05/03/30 = 30/03/2005) y Excel la leyó como dd/mm/aa. Hay que corregirla en la planilla (o escribirla como texto).', count($fechaInvertida)));
$p('');
usort($distintoDeFecha, fn($x, $y) => strcmp($x[3], $y[3]) ?: strcmp($x[0], $y[0]));
$tabla(['Registro', 'Año (col. K)', 'Fecha como dice el documento', 'Diagnóstico'], array_slice($distintoDeFecha, 0, $maxEj * 4));
if (count($distintoDeFecha) > $maxEj * 4) {
    $p('… y ' . (count($distintoDeFecha) - $maxEj * 4) . ' más.');
}
$resumen['Año distinto de la fecha del documento'] = count($distintoDeFecha);
$resumen['Fechas leídas al revés (aa/mm/dd)'] = count($fechaInvertida);

$h('3.b Años fuera de 1950–hoy', 3);
$p('Pueden ser correctos (material de época), pero conviene mirarlos.');
$tabla(['Registro', 'Año', 'Fecha como dice el documento'], array_slice($fueraDeRango, 0, $maxEj * 2));

// =====================================================================
// 4. Autores
// =====================================================================
$h('4. Autores');
$variantesAB = [];
$largos = [];
$dudosos = [];
$cartas = [];
foreach ($registros as $r) {
    $a = Texto::limpio($r['autor']);
    if ($a === null) {
        continue;
    }
    if (mb_strlen($a) > 80 || preg_match('/\b(fueron|figura|conformado|dice|hacen)\b/u', $a)) {
        $largos[$a][] = $r['codigo'];
    }
    if (preg_match('/\?/u', $a)) {
        $dudosos[$a][] = $r['codigo'];
    }
    if (preg_match('/^\s*de\s*:?\s+.+\s+a\s*:?\s+/iu', $a)) {
        $cartas[$a][] = $r['codigo'];
    }
    // Cada mención de Aída dentro del campo (puede haber coautores)
    if (preg_match_all('/\bA[íi]da(?:\s+Beatriz)?(?:\s+Bor\w*)?(?:\s+Ferreira)?\b|\bBortnik\s+A[íi]da\b|\bBor(?:tnik|nik|tink)\b|\bAB\b/u', $a, $mm)) {
        foreach (array_unique($mm[0]) as $forma) {
            $variantesAB[$forma][] = $r['codigo'];
        }
    }
}
$h('4.a Formas de escribir «Aída Bortnik»', 3);
$p('Cada forma distinta con que aparece Aída en el campo Autor (sola o con coautores). La forma de autoridad propuesta es **Aída Bortnik**.');
uasort($variantesAB, fn($x, $y) => count($y) <=> count($x));
$filas = [];
foreach ($variantesAB as $forma => $cods) {
    $filas[] = ['«' . $forma . '»' . ($forma === 'Aída Bortnik' ? ' ✓' : ''), count(array_unique($cods)), $ejemplos($cods)];
}
$tabla(['Forma', 'Registros', 'Ejemplos'], $filas);
$resumen['Formas distintas de «Aída Bortnik»'] = count($variantesAB);

$h('4.b Autor con texto descriptivo o demasiado largo', 3);
$filas = [];
foreach ($largos as $a => $cods) {
    $filas[] = ['«' . $celda($a, 80) . '»', count($cods), $ejemplos($cods)];
}
$tabla(['Autor', 'Registros', 'Ejemplos'], $filas);
$resumen['Registros con autor descriptivo'] = array_sum(array_map('count', $largos));

$h('4.c Autor con remitente y destinatario («De X A: Y»)', 3);
$p('Son cartas: el autor es el remitente. El destinatario podría ir en el resumen o en un campo propio.');
$filas = [];
foreach ($cartas as $a => $cods) {
    $filas[] = ['«' . $celda($a, 60) . '»', $ejemplos($cods)];
}
$tabla(['Autor', 'Registros'], $filas);

$h('4.d Autor con signos de duda', 3);
$filas = [];
foreach ($dudosos as $a => $cods) {
    $filas[] = ['«' . $celda($a, 60) . '»', $ejemplos($cods)];
}
$tabla(['Autor', 'Registros'], $filas);

// =====================================================================
// 5. Vocabularios
// =====================================================================
$h('5. Vocabularios');
$activos = array_filter($vocab, fn($v) => $v['fusionado_en'] === null);
$propuestos = array_filter($activos, fn($v) => $v['estado'] === 'propuesto');
$p(sprintf('Términos activos: %d (%d tipos, %d descriptores). Propuestos sin curar: %d. Fusionados: %d.',
    count($activos),
    count(array_filter($activos, fn($v) => $v['tipo'] === 'tipo_documento')),
    count(array_filter($activos, fn($v) => $v['tipo'] === 'descriptor')),
    count($propuestos),
    count($vocab) - count($activos)));
$resumen['Términos propuestos sin curar'] = count($propuestos);
$etq = fn(string $t) => $t === 'descriptor' ? 'Descriptor' : 'Tipo';

// 5.a Variantes del mismo término
$h('5.a Variantes del mismo término (puntuación, plural, «?»)', 3);
$norm = static function (string $s) use ($base): string {
    $b = $base($s);
    $b = (string)preg_replace('/\b(\w{3,}?)(es|s)\b/u', '$1', $b); // plural simple
    return str_replace(' ', '', $b);
};
$porNorm = [];
foreach ($activos as $v) {
    $porNorm[$v['tipo'] . '|' . $norm($v['nombre'])][] = $v;
}
$filas = [];
foreach ($porNorm as $k => $vs) {
    if (count($vs) > 1) {
        $filas[] = [$etq($vs[0]['tipo']), implode(' / ', array_map(fn($v) => "«{$v['nombre']}» (#{$v['id']}, {$v['usos']})", $vs))];
    }
}
$tabla(['Vocabulario', 'Variantes (id, usos)'], $filas);
$resumen['Grupos de variantes en vocabularios'] = count($filas);

// 5.b Términos sospechosos
$h('5.b Términos que no parecen vocabulario', 3);
$filas = [];
foreach ($activos as $v) {
    $nom = (string)$v['nombre'];
    $obs = null;
    if (!preg_match('/\p{L}{2}/u', $nom)) {
        $obs = 'letra suelta o solo signos';
    } elseif (preg_match('/\?/u', $nom)) {
        $obs = 'signos de duda';
    } elseif (mb_strlen($nom) > 45 || preg_match('/[()"“”]/u', $nom)) {
        $obs = 'frase o texto pegado';
    } elseif (preg_match('/\.\s*$/u', $nom)) {
        $obs = 'punto final';
    } elseif (preg_match('/^\p{Ll}/u', $nom)) {
        $obs = 'empieza en minúscula';
    } elseif (preg_match('/\p{Ll}\p{Lu}/u', $nom)) {
        $obs = 'palabras pegadas';
    }
    if ($obs) {
        $filas[] = [$etq($v['tipo']), "«" . $celda($nom, 60) . "» (#{$v['id']})", $obs, (int)$v['usos'], $ejemplos($codigosPorTermino[(int)$v['id']] ?? [])];
    }
}
$tabla(['Vocabulario', 'Término', 'Motivo', 'Usos', 'Registros'], $filas);
$resumen['Términos sospechosos'] = count($filas);

// 5.c El mismo término en los dos vocabularios
$h('5.c El mismo término como tipo y como descriptor', 3);
$p('Suele indicar que se cargó en la columna equivocada. «Ambos» = registros que ya lo tienen en los dos vocabularios.');
$porClave = [];
foreach ($activos as $v) {
    $porClave[$norm($v['nombre'])][$v['tipo']] = $v;
}
$filas = [];
foreach ($porClave as $par) {
    if (isset($par['tipo_documento'], $par['descriptor'])) {
        $t = $par['tipo_documento'];
        $d = $par['descriptor'];
        $ct = $codigosPorTermino[(int)$t['id']] ?? [];
        $cd = $codigosPorTermino[(int)$d['id']] ?? [];
        $filas[] = ["«{$t['nombre']}» / «{$d['nombre']}»", (int)$t['usos'], (int)$d['usos'], count(array_intersect($ct, $cd)),
                    $ejemplos(array_diff($cd, $ct))];
    }
}
$tabla(['Término', 'Como tipo', 'Como descriptor', 'Ambos', 'Solo como descriptor'], $filas);

// 5.d Fusiones entre vocabularios distintos (rompen el importador)
$h('5.d Fusiones con destino en el otro vocabulario', 3);
$filas = [];
foreach ($vocab as $v) {
    if ($v['fusionado_en'] !== null && $v['destino_tipo'] !== $v['tipo']) {
        $filas[] = ["«{$v['nombre']}» (#{$v['id']}, {$etq($v['tipo'])})", "#{$v['fusionado_en']} ({$etq((string)$v['destino_tipo'])})"];
    }
}
$p('Una fusión de un descriptor en un tipo (o al revés) hace que el importador cargue el término en el vocabulario equivocado y puede fallar por clave duplicada.');
$tabla(['Término', 'Fusionado en'], $filas);

// 5.e Términos sin uso
$h('5.e Términos sin registros', 3);
$sinUso = array_filter($activos, fn($v) => (int)$v['usos'] === 0);
$p($sinUso ? implode(', ', array_map(fn($v) => "«{$v['nombre']}» ({$etq($v['tipo'])})", $sinUso)) : '_Sin hallazgos._');

// =====================================================================
// Resumen al principio
// =====================================================================
$cab = [
    '# Auditoría de calidad del catálogo',
    '',
    sprintf('Generado el %s con `bin/auditar-catalogo.php` sobre la base `%s`. Solo lectura: no se modificó nada.',
        date('d/m/Y H:i'), aida_config()['db']['name']),
    '',
    sprintf('Cajas: %d · Registros: %d · Términos de vocabulario: %d', count($cajas), count($registros), count($vocab)),
    '',
    '## Resumen',
    '',
    '| Indicador | Cantidad |',
    '|---|---|',
];
foreach ($resumen as $k => $v) {
    $cab[] = "| $k | $v |";
}
$salida = implode("\n", array_merge($cab, $md)) . "\n";

if (!empty($opt['salida'])) {
    file_put_contents((string)$opt['salida'], $salida);
    fwrite(STDERR, "Informe guardado en {$opt['salida']}\n");
} else {
    echo $salida;
}
