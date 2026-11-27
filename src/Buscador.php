<?php
declare(strict_types=1);

namespace Aida;

/**
 * Buscador del sitio público y del backoffice: texto libre + filtros combinables,
 * resultados paginados por relevancia y conteos por faceta.
 *
 *   $b = new Buscador(aida_db());                         // modo público
 *   $b = new Buscador(aida_db(), Buscador::BACKOFFICE);   // incluye restringidos “a revisar”, no publicados y observaciones internas
 *   $r = $b->buscar('jovenes "historia oficial"', ['caja' => [2, 31], 'anio_desde' => 1980, 'tipo' => 12], pagina: 1);
 *
 * Texto (ver docs/busqueda.md):
 *  - Tildes y mayúsculas no importan: lo resuelve la colación utf8mb4_unicode_ci de las columnas.
 *  - Palabras de 3 letras o más: índice FULLTEXT, con prefijo (“secuestr” encuentra “secuestro”).
 *  - Palabras más cortas que innodb_ft_min_token_size y stopwords de InnoDB (“tv”, “yo”, “the”):
 *    no están en el índice, así que se buscan como palabra completa con REGEXP. No depende de la config del servidor.
 *  - "Frases entre comillas" (o “”): frase exacta.
 *  - C14R3 (también C14R03): el registro exacto. C14: los registros de la caja 14.
 *  - Un año (1983) también encuentra los registros con ese año en la columna Año.
 *  - -palabra: excluye.
 *  - Cada término tiene que aparecer en el registro, en sus tipos/descriptores o en el nombre de su caja.
 *
 * Años: un registro responde solo a los años de registro_anio (src/Anios.php, Texto::anio): “1981/82” aparece filtrando
 * 1981 y 1982; “1998/2006/2007/2008” en esos cuatro años y no filtrando 2000. anio = tiene ese año;
 * anio_desde/anio_hasta = tiene algún año dentro del pedido. La faceta de décadas cuenta el registro una vez en cada
 * década en la que tiene algún año. registro.anio (el menor) queda para ordenar. Los resultados traen anio_sin_confirmar
 * y anio_etiqueta con la lista tal cual (“1998, 2006–2008”, “1972 (sin confirmar)”) para mostrar.
 *
 * Filtros: caja (número o lista), anio, anio_desde, anio_hasta, decada (1980 = 1980–1989; también lista: [1980, 1990] = años 80
 * o 90), sin_anio (true: los que no tienen año; con decada, suma), tipo y descriptor (id o lista de ids de vocabulario,
 * dentro de un mismo filtro alcanza con uno), etapa (id o lista de ids de periodo: los registros de cajas de esa etapa),
 * acceso, digitalizado (0/1). En el backoffice además 'bajas' => true.
 * Las facetas se cuentan sin el filtro de la propia faceta (para poder sumar opciones). La de décadas sí respeta el rango
 * desde/hasta (es otro filtro de la misma faceta “Año”, que se aplica con su botón).
 * Orden: relevancia (con texto), codigo (caja y número), anio, anio_desc, digital (con documento digital primero).
 */
final class Buscador
{
    public const PUBLICO = 'publico';
    public const BACKOFFICE = 'backoffice';

    /** Stopwords por defecto de InnoDB (iguales en MySQL 8 y MariaDB). */
    private const STOPWORDS = ['a', 'about', 'an', 'are', 'as', 'at', 'be', 'by', 'com', 'de', 'en', 'for', 'from', 'how',
        'i', 'in', 'is', 'it', 'la', 'of', 'on', 'or', 'that', 'the', 'this', 'to', 'was', 'what', 'when', 'where', 'who',
        'will', 'with', 'und', 'www'];

    private const COLS_PUBLICO = 'r.codigo, r.titulo, r.autor, r.lugar, r.fecha_texto, r.anio_texto, r.resumen, r.isbn, r.obs_editorial, r.edicion_catalogo';
    private const COLS_INTERNO = 'r.obs_conservacion, r.obs_digitalizacion, r.seleccion_digit, r.excel_nomenclatura, r.excel_privado';
    private const LETRA = '0-9A-Za-zÁÉÍÓÚÜÑáéíóúüñÀÈÌÒÙàèìòùÂÊÎÔÛâêîôûÄËÏÖäëïö';

    private int $minToken;
    /** @var array<string,true> */
    private array $stopwords;

    public function __construct(private readonly \PDO $db, private readonly string $modo = self::PUBLICO)
    {
        if (!in_array($modo, [self::PUBLICO, self::BACKOFFICE], true)) {
            throw new \InvalidArgumentException("Modo de búsqueda desconocido: $modo");
        }
        // Se leen del servidor: si el hosting tiene otro mínimo o su propia lista de stopwords, el buscador se adapta.
        $this->minToken = max(1, (int)$db->query('SELECT @@innodb_ft_min_token_size')->fetchColumn());
        $this->stopwords = array_fill_keys(self::STOPWORDS, true);
        $tabla = (string)$db->query('SELECT @@innodb_ft_server_stopword_table')->fetchColumn();
        if ($tabla !== '' && preg_match('~^[A-Za-z0-9_$]+/[A-Za-z0-9_$]+$~', $tabla)) {
            try {
                [$base, $t] = explode('/', $tabla);
                $this->stopwords = [];
                foreach ($db->query("SELECT value FROM `$base`.`$t`") as $f) {
                    $this->stopwords[Texto::clave((string)$f['value'])] = true;
                }
            } catch (\PDOException) {
                $this->stopwords = array_fill_keys(self::STOPWORDS, true);
            }
        }
    }

    // ------------------------------------------------------------------
    // Interpretación del texto
    // ------------------------------------------------------------------

    /**
     * Separa la consulta en sus partes.
     * @return array{palabras:list<string>, cortas:list<string>, frases:list<string>, frases_cortas:list<string>,
     *               codigos:list<string>, cajas:list<int>, anios:list<int>, excluir:list<string>, excluir_cortas:list<string>}
     */
    public function interpretar(string $q): array
    {
        $out = ['palabras' => [], 'cortas' => [], 'frases' => [], 'frases_cortas' => [], 'codigos' => [], 'cajas' => [],
                'anios' => [], 'excluir' => [], 'excluir_cortas' => []];
        $q = mb_substr(trim($q), 0, 200);
        // Frases: "…", “…”, “…”
        $q = (string)preg_replace_callback('/"([^"]*)"|«([^»]*)»|“([^”]*)”/u', function (array $m) use (&$out) {
            $palabras = $this->palabras(implode(' ', array_slice($m, 1)));
            if (count($palabras) === 1) {
                $this->clasificar($palabras[0], $out, false);
            } elseif ($palabras) {
                $indexables = array_filter($palabras, fn($p) => $this->indexable($p));
                $out[$indexables ? 'frases' : 'frases_cortas'][] = implode(' ', $palabras);
            }
            return ' ';
        }, $q);
        foreach (preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) as $tok) {
            $excluir = str_starts_with($tok, '-') && mb_strlen($tok) > 1;
            $tok = ltrim($tok, '-+');
            if (!$excluir && ($c = Texto::parsearCodigo($tok)) && $c[2] === 0) {
                $out['codigos'][] = Texto::codigo($c[0], $c[1]);
                continue;
            }
            if (!$excluir && preg_match('/^c0*(\d{1,3})$/i', $tok, $m)) {
                $out['cajas'][] = (int)$m[1];
                continue;
            }
            foreach ($this->palabras($tok) as $p) {
                $this->clasificar($p, $out, $excluir);
            }
        }
        foreach ($out as $k => $v) {
            $out[$k] = array_values(array_unique($v));
        }
        return $out;
    }

    /** @return list<string> palabras en minúscula, solo letras y números */
    private function palabras(string $s): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($s, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function indexable(string $p): bool
    {
        return mb_strlen($p) >= $this->minToken && !isset($this->stopwords[Texto::clave($p)]);
    }

    private function clasificar(string $p, array &$out, bool $excluir): void
    {
        if ($excluir) {
            $out[$this->indexable($p) ? 'excluir' : 'excluir_cortas'][] = $p;
            return;
        }
        if ($this->indexable($p)) {
            $out['palabras'][] = $p;
            if (preg_match('/^(1[89]\d\d|20\d\d)$/', $p)) {
                $out['anios'][] = (int)$p;
            }
        } else {
            $out['cortas'][] = $p;
        }
    }

    // ------------------------------------------------------------------
    // Búsqueda
    // ------------------------------------------------------------------

    /**
     * El texto se evalúa una sola vez (consulta 1: candidatos con su relevancia). Filtros, facetas, orden y
     * paginación se resuelven sobre esos candidatos en PHP, y la consulta 3 trae solo los datos de la página.
     * Con el tamaño del archivo (≈1.100 registros hoy, unos pocos miles al final) es más rápido que repetir
     * el texto en cada conteo de faceta. Ver docs/busqueda.md.
     *
     * $antes: además de la página pedida, trae los resultados de hasta esa cantidad de páginas anteriores (9 como mucho) en la misma
     * búsqueda (lista acumulada de “Mostrar más”), en 'resultados_antes'.
     *
     * @param array<string,mixed> $filtros
     * @return array{total:int, pagina:int, por_pagina:int, paginas:int, orden:string, resultados:list<array>, resultados_antes:list<int>, facetas:array, consulta:array, ms:float}
     */
    public function buscar(string $q, array $filtros = [], int $pagina = 1, int $porPagina = 20, ?string $orden = null, int $antes = 0): array
    {
        $t0 = hrtime(true);
        $consulta = $this->interpretar($q);
        $filtros = $this->normalizarFiltros($filtros);
        $porPagina = max(1, min(100, $porPagina));
        $pagina = max(1, $pagina);

        // 1. Candidatos: visibilidad + texto
        [$wSql, $wPar] = $this->where($this->condicionesTexto($consulta), $filtros);
        [$rel, $relPar] = $this->relevancia($consulta);
        $candidatos = $this->filas("SELECT r.id, r.caja_id, r.numero, r.anio, r.anio_hasta, r.anio_sin_confirmar, r.acceso, r.digitalizado, c.numero AS caja, c.nombre AS caja_nombre,
                $rel AS relevancia
            FROM registro r JOIN caja c ON c.id = r.caja_id WHERE $wSql", array_merge($relPar, $wPar));

        // 2. Tipos y descriptores de los candidatos (para filtrar y contar)
        $vocab = [];
        $nombres = [];
        $sqlVocab = 'SELECT rv.registro_id, v.id, v.tipo, v.nombre FROM registro_vocabulario rv JOIN vocabulario v ON v.id = rv.vocabulario_id';
        $esCandidato = array_flip(array_column($candidatos, 'id'));
        // Con muchos candidatos es más rápido traer todo que armar listas IN (...)
        $lotes = count($candidatos) > 2000 ? [[]] : array_chunk(array_keys($esCandidato), 1000);
        foreach ($lotes as $ids) {
            $sql = $ids ? $sqlVocab . ' WHERE rv.registro_id IN (' . $this->marcas($ids) . ')' : $sqlVocab;
            foreach ($this->filas($sql, $ids) as $v) {
                if (isset($esCandidato[$v['registro_id']])) {
                    $k = $v['tipo'] === 'tipo_documento' ? 'tipo' : 'descriptor';
                    $vocab[(int)$v['registro_id']][$k][] = (int)$v['id'];
                    $nombres[(int)$v['id']] = $v['nombre'];
                }
            }
        }
        // 2b. Años de los candidatos (registro_anio)
        $anios = [];
        $sqlAnios = 'SELECT registro_id, anio, sin_confirmar FROM registro_anio';
        foreach ($lotes as $ids) {
            $sql = $ids ? $sqlAnios . ' WHERE registro_id IN (' . $this->marcas($ids) . ')' : $sqlAnios;
            foreach ($this->filas($sql, $ids) as $a) {
                if (isset($esCandidato[$a['registro_id']])) {
                    $anios[(int)$a['registro_id']][(int)$a['anio']] = (bool)(int)$a['sin_confirmar'];
                }
            }
        }
        // 2c. Etapas de cada caja (caja_periodo: pocas filas)
        $etapas = [];
        foreach ($this->filas('SELECT caja_id, periodo_id FROM caja_periodo', []) as $cp) {
            $etapas[(int)$cp['caja_id']][] = (int)$cp['periodo_id'];
        }
        foreach ($candidatos as &$c) {
            $c['id'] = (int)$c['id'];
            $c['etapa'] = $etapas[(int)$c['caja_id']] ?? [];
            $c['anios'] = $anios[$c['id']] ?? [];
            ksort($c['anios']);
            $c['caja'] = (int)$c['caja'];
            $c['anio'] = $c['anio'] === null ? null : (int)$c['anio'];
            $c['anio_hasta'] = $c['anio'] === null ? null : (int)($c['anio_hasta'] ?? $c['anio']);
            $c['anio_sin_confirmar'] = (int)$c['anio_sin_confirmar'];
            $c['digitalizado'] = (int)$c['digitalizado'];
            $c['relevancia'] = (float)$c['relevancia'];
            $c['tipo'] = $vocab[$c['id']]['tipo'] ?? [];
            $c['descriptor'] = $vocab[$c['id']]['descriptor'] ?? [];
        }
        unset($c);

        $facetas = $this->facetas($candidatos, $filtros, $nombres);
        $final = $filtros ? array_values(array_filter($candidatos, fn($c) => $this->cumple($c, $filtros))) : $candidatos;

        // Orden y página
        $hayTexto = $rel !== '0';
        $orden = in_array($orden, ['relevancia', 'anio', 'anio_desc', 'codigo', 'digital'], true) ? $orden : ($hayTexto ? 'relevancia' : 'codigo');
        $porCodigo = fn($a, $b) => [$a['caja'], $a['numero']] <=> [$b['caja'], $b['numero']];
        usort($final, match ($orden) {
            'relevancia' => fn($a, $b) => ($b['relevancia'] <=> $a['relevancia']) ?: $porCodigo($a, $b),
            'anio' => fn($a, $b) => [$a['anio'] === null, $a['anio']] <=> [$b['anio'] === null, $b['anio']] ?: $porCodigo($a, $b),
            'anio_desc' => fn($a, $b) => [$a['anio'] === null, -(int)$a['anio']] <=> [$b['anio'] === null, -(int)$b['anio']] ?: $porCodigo($a, $b),
            'digital' => fn($a, $b) => ($b['digitalizado'] <=> $a['digitalizado']) ?: $porCodigo($a, $b),
            default => $porCodigo,
        });
        $total = count($final);
        $paginas = max(1, (int)ceil($total / $porPagina));
        $pagina = min($pagina, $paginas);
        $enPagina = array_slice($final, ($pagina - 1) * $porPagina, $porPagina);
        $antes = max(0, min(9, $antes, $pagina - 1)); // hasta 10 páginas en total
        $idsAntes = $antes ? array_map(fn($c) => (int)$c['id'], array_slice($final, ($pagina - 1 - $antes) * $porPagina, $antes * $porPagina)) : [];

        // 3. Datos para mostrar, solo de la página
        $resultados = [];
        if ($enPagina) {
            $ids = array_column($enPagina, 'id');
            $extra = $this->modo === self::BACKOFFICE ? ', r.publicado, r.eliminado_en, r.excel_privado' : '';
            $datos = [];
            foreach ($this->filas("SELECT r.id, r.codigo, r.titulo, r.autor, r.lugar, r.fecha_texto, r.anio_texto,
                       LEFT(r.resumen, 280) AS resumen,
                       (SELECT GROUP_CONCAT(v.nombre ORDER BY rv.orden SEPARATOR ' · ') FROM registro_vocabulario rv
                          JOIN vocabulario v ON v.id = rv.vocabulario_id WHERE rv.registro_id = r.id AND v.tipo = 'tipo_documento') AS tipos
                       $extra
                    FROM registro r WHERE r.id IN (" . $this->marcas($ids) . ')', $ids) as $d) {
                $datos[(int)$d['id']] = $d;
            }
            foreach ($enPagina as $c) {
                $d = $datos[$c['id']] ?? [];
                $resultados[] = [
                    'id' => $c['id'], 'codigo' => $d['codigo'] ?? null, 'titulo' => $d['titulo'] ?? null,
                    'autor' => $d['autor'] ?? null, 'lugar' => $d['lugar'] ?? null, 'fecha_texto' => $d['fecha_texto'] ?? null,
                    'anio' => $c['anio'], 'anio_hasta' => $c['anio_hasta'], 'anio_sin_confirmar' => $c['anio_sin_confirmar'],
                    'anios' => array_keys($c['anios']),
                    'anio_etiqueta' => Texto::aniosEtiqueta($c['anios']),
                    'anio_texto' => $d['anio_texto'] ?? null, 'resumen' => $d['resumen'] ?? null,
                    'tipos' => $d['tipos'] ?? null, 'acceso' => $c['acceso'], 'digitalizado' => $c['digitalizado'],
                    'caja_numero' => $c['caja'], 'caja_nombre' => $c['caja_nombre'], 'relevancia' => round($c['relevancia'], 3),
                ] + array_intersect_key($d, ['publicado' => 1, 'eliminado_en' => 1, 'excel_privado' => 1]);
            }
        }

        return [
            'total' => $total,
            'resultados_antes' => $idsAntes,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'paginas' => $paginas,
            'orden' => $orden,
            'resultados' => $resultados,
            'facetas' => $facetas,
            'consulta' => $consulta,
            'ms' => round((hrtime(true) - $t0) / 1e6, 2),
        ];
    }

    /**
     * Cajas cuyo nombre o resumen coincide con el texto (para mostrar arriba de los registros).
     * @return list<array{id:int, numero:int, nombre:string, resumen:?string, relevancia:float}>
     */
    public function cajas(string $q, int $limite = 10): array
    {
        $c = $this->interpretar($q);
        $expr = $this->expresion($c);
        if ($expr === '') {
            return $c['cajas'] ? $this->cajasPorNumero($c['cajas']) : [];
        }
        $vis = $this->modo === self::PUBLICO ? 'c.publicada = 1 AND c.eliminado_en IS NULL' : 'c.eliminado_en IS NULL';
        $st = $this->db->prepare("SELECT c.id, c.numero, c.nombre, LEFT(c.resumen, 400) AS resumen,
                   3 * MATCH(c.nombre) AGAINST(? IN BOOLEAN MODE) + MATCH(c.nombre, c.resumen) AGAINST(? IN BOOLEAN MODE) AS relevancia
            FROM caja c WHERE $vis AND MATCH(c.nombre, c.resumen) AGAINST(? IN BOOLEAN MODE)
            ORDER BY relevancia DESC, c.numero LIMIT " . max(1, min(70, $limite)));
        $st->execute([$expr, $expr, $this->expresion($c, true)]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function cajasPorNumero(array $numeros): array
    {
        $vis = $this->modo === self::PUBLICO ? 'c.publicada = 1 AND c.eliminado_en IS NULL' : 'c.eliminado_en IS NULL';
        $st = $this->db->prepare("SELECT c.id, c.numero, c.nombre, LEFT(c.resumen, 400) AS resumen, 1 AS relevancia FROM caja c
            WHERE $vis AND c.numero IN (" . $this->marcas($numeros) . ') ORDER BY c.numero');
        $st->execute($numeros);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------------
    // Construcción de SQL
    // ------------------------------------------------------------------

    /** Expresión booleana de FULLTEXT. $todas = cada palabra obligatoria; si no, solo suma relevancia. */
    private function expresion(array $c, bool $todas = false): string
    {
        $partes = [];
        foreach ($c['palabras'] as $p) {
            // la palabra exacta suma más que solo el prefijo
            $partes[] = $todas ? "+$p*" : "$p $p*";
        }
        foreach ($c['frases'] as $f) {
            $partes[] = ($todas ? '+' : '') . '"' . $f . '"';
        }
        return implode(' ', $partes);
    }

    /** @return array{0:string,1:list<mixed>} */
    private function relevancia(array $c): array
    {
        $sql = [];
        $par = [];
        foreach ($c['codigos'] as $cod) {
            $sql[] = '100 * (r.codigo = ?)';
            $par[] = $cod;
        }
        $expr = $this->expresion($c);
        if ($expr !== '') {
            $sql[] = '3 * MATCH(r.titulo) AGAINST(? IN BOOLEAN MODE)';
            $sql[] = 'MATCH(' . self::COLS_PUBLICO . ') AGAINST(? IN BOOLEAN MODE)';
            array_push($par, $expr, $expr);
            if ($this->modo === self::BACKOFFICE) {
                $sql[] = '0.5 * MATCH(' . self::COLS_INTERNO . ') AGAINST(? IN BOOLEAN MODE)';
                $par[] = $expr;
            }
        }
        foreach ($c['cortas'] as $p) {
            // las palabras cortas que están en el título suman
            $sql[] = 'IFNULL(r.titulo REGEXP ?, 0)';
            $par[] = $this->patronPalabra($p);
        }
        return $sql ? ['(' . implode(' + ', $sql) . ')', $par] : ['0', []];
    }

    /**
     * Una condición por término (todas tienen que cumplirse).
     * @return list<array{0:string,1:list<mixed>}>
     */
    private function condicionesTexto(array $c): array
    {
        $conds = [];
        $ftPub = 'MATCH(' . self::COLS_PUBLICO . ') AGAINST(? IN BOOLEAN MODE)';
        $ftInt = 'MATCH(' . self::COLS_INTERNO . ') AGAINST(? IN BOOLEAN MODE)';
        $bo = $this->modo === self::BACKOFFICE;
        $textoPub = 'CONCAT_WS(\' \', ' . self::COLS_PUBLICO . ($bo ? ', ' . self::COLS_INTERNO : '') . ')';
        $vocab = 'EXISTS (SELECT 1 FROM registro_vocabulario rv JOIN vocabulario v ON v.id = rv.vocabulario_id
                          WHERE rv.registro_id = r.id AND %s)';

        foreach ($c['palabras'] as $p) {
            $sql = [$ftPub];
            $par = ["+$p*"];
            if ($bo) {
                $sql[] = $ftInt;
                $par[] = "+$p*";
            }
            $sql[] = sprintf($vocab, '(v.nombre LIKE ? OR v.nombre LIKE ?)');
            $sql[] = '(c.nombre LIKE ? OR c.nombre LIKE ?)';
            array_push($par, "$p%", "% $p%", "$p%", "% $p%");
            if (in_array((int)$p, $c['anios'], true) && preg_match('/^\d{4}$/', $p)) {
                $sql[] = 'EXISTS (SELECT 1 FROM registro_anio ra WHERE ra.registro_id = r.id AND ra.anio = ?)';
                $par[] = (int)$p;
            }
            $conds[] = ['(' . implode(' OR ', $sql) . ')', $par];
        }
        foreach ($c['frases'] as $f) {
            $sql = [$ftPub];
            $par = ['+"' . $f . '"'];
            if ($bo) {
                $sql[] = $ftInt;
                $par[] = '+"' . $f . '"';
            }
            $conds[] = ['(' . implode(' OR ', $sql) . ')', $par];
        }
        foreach ($c['frases_cortas'] as $f) {
            $conds[] = ["$textoPub LIKE ?", ['%' . $f . '%']];
        }
        foreach ($c['cortas'] as $p) {
            $pat = $this->patronPalabra($p);
            $conds[] = ["($textoPub REGEXP ? OR " . sprintf($vocab, 'v.nombre REGEXP ?') . ' OR c.nombre REGEXP ?)', [$pat, $pat, $pat]];
        }
        if ($c['codigos']) {
            $conds[] = ['r.codigo IN (' . $this->marcas($c['codigos']) . ')', $c['codigos']];
        }
        if ($c['cajas']) {
            $conds[] = ['c.numero IN (' . $this->marcas($c['cajas']) . ')', $c['cajas']];
        }
        foreach ($c['excluir'] as $p) {
            $conds[] = ["NOT $ftPub", ["+$p*"]];
        }
        foreach ($c['excluir_cortas'] as $p) {
            $conds[] = ["IFNULL($textoPub, '') NOT REGEXP ?", [$this->patronPalabra($p)]];
        }
        return $conds;
    }

    /** Palabra completa, sin importar tildes (la colación ya ignora mayúsculas). Funciona igual en ICU (MySQL) y PCRE (MariaDB). */
    private function patronPalabra(string $p): string
    {
        $clases = ['a' => '[aáàâä]', 'e' => '[eéèêë]', 'i' => '[iíìîï]', 'o' => '[oóòôö]', 'u' => '[uúùûü]', 'n' => '[nñ]'];
        $pat = '';
        foreach (mb_str_split(Texto::clave($p)) as $ch) {
            $pat .= $clases[$ch] ?? preg_quote($ch);
        }
        $b = '[^' . self::LETRA . ']';
        return "(^|$b)$pat($b|$)";
    }

    private function normalizarFiltros(array $f): array
    {
        $ints = fn($v) => array_values(array_unique(array_filter(array_map('intval', (array)$v), fn($n) => $n > 0)));
        $out = [];
        if (isset($f['caja']) && $f['caja'] !== '' && ($v = $ints($f['caja']))) {
            $out['caja'] = $v;
        }
        foreach (['tipo', 'descriptor', 'etapa'] as $k) {
            if (isset($f[$k]) && $f[$k] !== '' && ($v = $ints($f[$k]))) {
                $out[$k] = $v;
            }
        }
        foreach (['anio', 'anio_desde', 'anio_hasta'] as $k) {
            if (isset($f[$k]) && $f[$k] !== '' && (int)$f[$k] > 0) {
                $out[$k] = (int)$f[$k];
            }
        }
        // Década (1980 = años 80) o lista de décadas (dentro de la faceta, O); se cruza con el rango desde/hasta (Y)
        if (isset($f['decada']) && $f['decada'] !== '' && ($v = $ints($f['decada']))) {
            $out['decada'] = array_values(array_unique(array_map(fn($d) => intdiv($d, 10) * 10, $v)));
        }
        if (!empty($f['sin_anio'])) {
            $out['sin_anio'] = true;
        }
        $accesos = $this->modo === self::PUBLICO ? ['publico', 'restringido'] : ['publico', 'restringido', 'a_revisar'];
        if (isset($f['acceso']) && ($v = array_values(array_intersect((array)$f['acceso'], $accesos)))) {
            $out['acceso'] = $v;
        }
        if (isset($f['digitalizado']) && $f['digitalizado'] !== '' && $f['digitalizado'] !== null) {
            $out['digitalizado'] = filter_var($f['digitalizado'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }
        if ($this->modo === self::BACKOFFICE && !empty($f['bajas'])) {
            $out['bajas'] = true;
        }
        return $out;
    }

    /**
     * WHERE de la consulta de candidatos: visibilidad según el modo + condiciones de texto.
     * @return array{0:string,1:list<mixed>}
     */
    private function where(array $texto, array $f): array
    {
        $w = [];
        $p = [];
        if ($this->modo === self::PUBLICO) {
            // Público: sin bajas, publicado, caja publicada; “a revisar” no se muestra hasta decidir (docs/backoffice.md §2)
            $w[] = "r.eliminado_en IS NULL AND r.publicado = 1 AND r.acceso <> 'a_revisar' AND c.eliminado_en IS NULL AND c.publicada = 1";
        } elseif (empty($f['bajas'])) {
            $w[] = 'r.eliminado_en IS NULL AND c.eliminado_en IS NULL';
        }
        foreach ($texto as [$sql, $par]) {
            $w[] = $sql;
            array_push($p, ...$par);
        }
        return [$w ? implode("\n  AND ", $w) : '1 = 1', $p];
    }

    /** ¿El candidato pasa los filtros? $sin = faceta que no se aplica (para contarla). */
    private function cumple(array $c, array $f, ?string $sin = null): bool
    {
        if (!$f || $f === ['bajas' => true]) {
            return true;
        }
        if ($sin !== 'caja' && isset($f['caja']) && !in_array($c['caja'], $f['caja'], true)) {
            return false;
        }
        if ($sin !== 'decada' && (isset($f['decada']) || isset($f['sin_anio']))) {
            // Décadas elegidas (O entre ellas) y “Sin año”: el registro tiene algún año en alguna, o no tiene años
            $toca = isset($f['sin_anio']) && !$c['anios'];
            foreach ($c['anios'] as $a => $_) {
                if (isset($f['decada']) && in_array(intdiv($a, 10) * 10, $f['decada'], true)) {
                    $toca = true;
                    break;
                }
            }
            if (!$toca) {
                return false;
            }
        }
        // Solo los años del registro (registro_anio): una lista salteada no responde a los años del medio
        if (isset($f['anio']) && !isset($c['anios'][$f['anio']])) {
            return false;
        }
        if (isset($f['anio_desde']) || isset($f['anio_hasta'])) {
            $d = $f['anio_desde'] ?? PHP_INT_MIN;
            $h = $f['anio_hasta'] ?? PHP_INT_MAX;
            $toca = false;
            foreach ($c['anios'] as $a => $_) {
                if ($a >= $d && $a <= $h) {
                    $toca = true;
                    break;
                }
            }
            if (!$toca) {
                return false;
            }
        }
        foreach (['tipo', 'descriptor', 'etapa'] as $k) {
            if ($sin !== $k && isset($f[$k]) && !array_intersect($c[$k], $f[$k])) {
                return false;
            }
        }
        if ($sin !== 'acceso' && isset($f['acceso']) && !in_array($c['acceso'], $f['acceso'], true)) {
            return false;
        }
        if ($sin !== 'digitalizado' && isset($f['digitalizado']) && $c['digitalizado'] !== $f['digitalizado']) {
            return false;
        }
        return true;
    }

    /**
     * Conteos por faceta, cada uno sin su propio filtro (así se pueden sumar opciones de la misma faceta).
     * @return array<string, list<array{valor:int|string|null, etiqueta?:string, cantidad:int}>>
     */
    private function facetas(array $candidatos, array $f, array $nombres): array
    {
        $cuentas = ['caja' => [], 'decada' => [], 'tipo' => [], 'descriptor' => [], 'etapa' => [], 'acceso' => [], 'digitalizado' => []];
        $etiquetaCaja = [];
        foreach ($candidatos as $c) {
            if ($this->cumple($c, $f, 'caja')) {
                $cuentas['caja'][$c['caja']] = ($cuentas['caja'][$c['caja']] ?? 0) + 1;
                $etiquetaCaja[$c['caja']] = $c['caja_nombre'];
            }
            if ($this->cumple($c, $f, 'decada')) {
                // Cuenta una vez en cada década en la que tiene algún año (“1978/1982”: años 70 y 80;
                // “1998/2006”: años 90 y 2000)
                $decadas = !$c['anios'] ? ['sin_anio']
                    : array_unique(array_map(fn($a) => intdiv($a, 10) * 10, array_keys($c['anios'])));
                foreach ($decadas as $d) {
                    $cuentas['decada'][$d] = ($cuentas['decada'][$d] ?? 0) + 1;
                }
            }
            foreach (['tipo', 'descriptor', 'etapa'] as $k) {
                if ($this->cumple($c, $f, $k)) {
                    foreach (array_unique($c[$k]) as $id) {
                        $cuentas[$k][$id] = ($cuentas[$k][$id] ?? 0) + 1;
                    }
                }
            }
            foreach (['acceso', 'digitalizado'] as $k) {
                if ($this->cumple($c, $f, $k)) {
                    $cuentas[$k][$c[$k]] = ($cuentas[$k][$c[$k]] ?? 0) + 1;
                }
            }
        }
        $out = [];
        ksort($cuentas['caja']);
        foreach ($cuentas['caja'] as $v => $n) {
            $out['caja'][] = ['valor' => $v, 'etiqueta' => $etiquetaCaja[$v], 'cantidad' => $n];
        }
        uksort($cuentas['decada'], fn($a, $b) => [$a === 'sin_anio', $a] <=> [$b === 'sin_anio', $b]);
        foreach ($cuentas['decada'] as $v => $n) {
            $out['decada'][] = ['valor' => $v === 'sin_anio' ? null : (int)$v, 'etiqueta' => $v === 'sin_anio' ? 'Sin año' : "Años {$v}", 'cantidad' => $n];
        }
        foreach (['tipo', 'descriptor'] as $k) {
            $filas = [];
            foreach ($cuentas[$k] as $v => $n) {
                $filas[] = ['valor' => (int)$v, 'etiqueta' => $nombres[$v] ?? '', 'cantidad' => $n];
            }
            usort($filas, fn($a, $b) => ($b['cantidad'] <=> $a['cantidad']) ?: strcmp(Texto::clave($a['etiqueta']), Texto::clave($b['etiqueta'])));
            $out[$k] = $filas;
        }
        ksort($cuentas['etapa']);
        $out['etapa'] = [];
        foreach ($cuentas['etapa'] as $v => $n) {
            $out['etapa'][] = ['valor' => (int)$v, 'cantidad' => $n];
        }
        $etiquetas = ['acceso' => ['publico' => 'Público', 'restringido' => 'Restringido', 'a_revisar' => 'A revisar'],
                      'digitalizado' => [1 => 'Digitalizado', 0 => 'Sin digitalizar']];
        foreach (['acceso', 'digitalizado'] as $k) {
            $out[$k] = [];
            foreach ($etiquetas[$k] as $v => $e) {
                if (isset($cuentas[$k][$v])) {
                    $out[$k][] = ['valor' => $v, 'etiqueta' => $e, 'cantidad' => $cuentas[$k][$v]];
                }
            }
        }
        return $out + ['caja' => [], 'decada' => []];
    }

    private function marcas(array $v): string
    {
        return implode(',', array_fill(0, count($v), '?'));
    }

    private function filas(string $sql, array $p): array
    {
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }
}
