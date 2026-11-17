<?php
declare(strict_types=1);

namespace Aida\Web;

use Aida\Buscador;
use Aida\Consultas;
use Aida\Sitio;
use Aida\Texto;

/**
 * Controladores del sitio público. Front controller: public/sitio.php (con reescritura, todas las URL legibles
 * llegan ahí; sin reescritura, sitio.php?ruta=…). Rutas y plantillas: docs/sitio-publico.md.
 *
 * Reglas:
 *  - Solo lectura, salvo el formulario de consulta (POST con CSRF por cookie, campo trampa y tiempo mínimo).
 *  - Al público no se le abre sesión: la cookie de sesión es solo del backoffice.
 *  - Lo que no se muestra en el sitio no existe (Sitio en modo público): 404, o 410 si existió y se despublicó.
 */
final class SitioPublico
{
    public const POR_PAGINA = 20;

    /** Facetas de Explorar en el orden de docs/ux/sitio-publico.md §4.1 (etapa va después de Año, como en la maqueta). */
    public const FACETAS = [
        'disponible' => ['titulo' => 'Disponibilidad', 'abierta' => true],
        'acceso' => ['titulo' => 'Acceso', 'abierta' => false],
        'tipo' => ['titulo' => 'Tipo de documento', 'abierta' => true, 'buscar' => true],
        'decada' => ['titulo' => 'Año', 'abierta' => true],
        'etapa' => ['titulo' => 'Etapa', 'abierta' => false],
        'caja' => ['titulo' => 'Caja / obra', 'abierta' => false, 'buscar' => true],
        'descriptor' => ['titulo' => 'Descriptores (temas)', 'abierta' => false, 'buscar' => true],
    ];

    public const ORDENES = [
        'relevancia' => ['Más relevantes', 'relevancia'], 'caja' => ['Caja y número', 'codigo'],
        'anio-asc' => ['Año: más antiguos', 'anio'], 'anio-desc' => ['Año: más recientes', 'anio_desc'],
        'digital' => ['Con documento digital primero', 'digital'],
    ];

    private const FRASE = 'Más de 70 cajas de un corpus creativo donde las palabras importan más que las imágenes y los sueños más que las ficciones';

    private static ?Enlaces $enl = null;
    private static ?Sitio $sitio = null;

    public static function despachar(string $ruta): void
    {
        self::$enl = Enlaces::desdePedido();
        self::$sitio = new Sitio(aida_db());
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: SAMEORIGIN');

        $ruta = trim(rawurldecode($ruta), '/');
        $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($metodo, ['GET', 'HEAD', 'POST'], true)) {
            http_response_code(405);
            header('Allow: GET, HEAD, POST');
            exit;
        }
        if ($metodo === 'POST' && !preg_match('~^(consulta/[^/]+|muestra/consulta)$~', $ruta)) {
            http_response_code(405);
            header('Allow: GET, HEAD');
            exit;
        }

        try {
            match (true) {
                $ruta === '' => self::inicio(),
                $ruta === 'explorar' => self::explorar(),
                $ruta === 'explorar.json' => self::explorarJson(),
                $ruta === 'cajas' => self::cajas(),
                (bool)preg_match('~^caja/(\d{1,4})(?:-([a-z0-9-]*))?$~', $ruta, $m) => self::caja($m[1], $m[2] ?? ''),
                (bool)preg_match('~^registro/([^/]+)$~', $ruta, $m) => self::registro($m[1]),
                $ruta === 'muestra/consulta' && $metodo === 'POST' => self::consultaMuestra(),
                (bool)preg_match('~^consulta/([^/]+)$~', $ruta, $m) => self::consulta($m[1]),
                (bool)preg_match('~^consulta/([^/]+)/enviada$~', $ruta, $m) => self::consultaEnviada($m[1]),
                $ruta === 'sobre' => self::sobre(),
                $ruta === 'sobre-el-archivo' => self::redirigir(self::$enl->sobre(), 301),
                $ruta === 'sitemap.xml' => self::sitemap(),
                $ruta === 'robots.txt' => self::robots(),
                default => self::noEncontrado(),
            };
        } catch (\Throwable $e) {
            error_log('[aida-sitio] ' . $e);
            self::pagina('error', ['tituloPagina' => 'Error', 'robots' => 'noindex'], 500);
        }
    }

    // ------------------------------------------------------------------
    // Inicio
    // ------------------------------------------------------------------

    private static function inicio(): void
    {
        $s = self::$sitio;
        $b = new Buscador(aida_db());
        $r = $b->buscar('', [], 1, 1);
        $terminos = $s->terminos();
        $tipos = self::opcionesTermino($r['facetas']['tipo'], $terminos['tipo_documento']);
        usort($tipos, fn($a, $b) => $b['n'] <=> $a['n']);
        self::pagina('inicio', [
            'cifras' => $s->cifras(),
            'totalArchivo' => $s->totalCajasArchivo(),
            'etapas' => $s->etapas(),
            'tipos' => array_slice($tipos, 0, 10),
            'totalTipos' => count($tipos),
            'decadas' => self::opcionesDecada($r['facetas']['decada']),
            'destacados' => $s->destacadosDigitales(6),
            'cajas' => array_slice($s->cajas(), 0, 9),
            'frase' => self::FRASE,
            'seccion' => 'inicio',
            'meta' => ['descripcion' => self::FRASE . '.', 'canonical' => Enlaces::absoluta('')],
        ]);
    }

    // ------------------------------------------------------------------
    // Explorar
    // ------------------------------------------------------------------

    private static function explorar(): void
    {
        self::normalizarUrlExplorar();
        $e = self::leerEstado($_GET);
        // El código exacto (C14R3) va directo a la ficha
        if ($e['q'] !== '' && ($c = Texto::parsearCodigo($e['q'])) && $c[2] === 0
            && ($f = self::$sitio->fichaPorCodigo($e['q']))) {
            self::redirigir(self::$enl->registro($f['registro']), 302);
        }
        $datos = ['e' => $e, 'seccion' => 'explorar', 'robots' => 'noindex,follow',
                  'meta' => ['descripcion' => 'Buscador del archivo “Las cajas de Aída”: documentos de Aída Bortnik por tipo, año, caja y tema.',
                             'canonical' => Enlaces::absoluta('explorar')]];
        try {
            $datos = self::resultados($e) + $datos;
            self::pagina('explorar', $datos);
        } catch (\Throwable $ex) {
            error_log('[aida-sitio] explorar: ' . $ex);
            self::pagina('explorar', $datos + ['error' => true], 500);
        }
    }

    /** JSON para “Mostrar más” y los filtros sin recargar: fragmentos ya armados con las mismas plantillas. */
    private static function explorarJson(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache');
        header('X-Robots-Tag: noindex');
        $e = self::leerEstado($_GET);
        try {
            $d = self::resultados($e) + ['enl' => self::$enl];
            $p = fn(string $v, array $extra = []) => self::parcial("publico/$v", $extra + $d);
            echo json_encode([
                'total' => $d['r']['total'],
                'conteo' => $d['conteo'],
                'pagina' => $d['r']['pagina'],
                'paginas' => $d['r']['paginas'],
                'url' => self::$enl->explorar(self::parametros($d['e'])),
                'filtros' => $d['nFiltros'],
                'items' => $p('_explorar_items', ['tarjetas' => $d['tarjetasPagina']]),
                'resultados' => $p('_explorar_resultados'),
                'paginacion' => $p('_explorar_paginacion'),
                'facetas' => $p('_explorar_facetas'),
                'chips' => $p('_explorar_chips'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $ex) {
            error_log('[aida-sitio] explorar.json: ' . $ex);
            http_response_code(500);
            echo json_encode(['error' => 'No pudimos cargar los resultados. Tus filtros siguen guardados.'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Si la URL trae listas a la manera de un formulario sin JS (tipo[]=a&tipo[]=b) o campos vacíos, redirige a la
     * forma canónica (tipo=a,b): así la URL siempre se puede compartir y “atrás” funciona igual con y sin JS.
     */
    private static function normalizarUrlExplorar(): void
    {
        $sucia = false;
        foreach ($_GET as $k => $v) {
            if ($k === 'ruta') {
                continue;
            }
            if (is_array($v) || $v === '' || !in_array($k, ['q', 'orden', 'pagina', 'desde', 'hasta', 'primera', ...array_keys(self::FACETAS)], true)) {
                $sucia = true;
                break;
            }
        }
        if ($sucia) {
            self::redirigir(self::$enl->explorar(self::parametros(self::leerEstado($_GET))), 302);
        }
    }

    /**
     * Estado de Explorar desde la URL. Valores desconocidos se ignoran (un enlace viejo nunca da error).
     * @return array{q:string, sel:array<string,list<string>>, desde:?int, hasta:?int, orden:string, pagina:int, primera:int}
     */
    public static function leerEstado(array $g): array
    {
        $lista = function ($v): array {
            $v = is_array($v) ? $v : explode(',', (string)$v);
            $out = [];
            foreach ($v as $x) {
                $x = mb_substr(trim((string)$x), 0, 80);
                if ($x !== '' && !in_array($x, $out, true)) {
                    $out[] = $x;
                }
            }
            return array_slice($out, 0, 60);
        };
        $sel = [];
        foreach (array_keys(self::FACETAS) as $k) {
            $sel[$k] = $lista($g[$k] ?? []);
        }
        $sel['disponible'] = array_values(array_intersect($sel['disponible'], ['digital', 'ficha']));
        $sel['acceso'] = array_values(array_intersect($sel['acceso'], ['libre', 'a-pedido']));
        $sel['decada'] = array_values(array_filter($sel['decada'], fn($d) => $d === 'sin-fecha' || preg_match('/^(19|20)\d0$/', $d)));
        $sel['caja'] = array_values(array_filter($sel['caja'], fn($n) => ctype_digit($n) && (int)$n > 0));
        $anio = function ($v): ?int {
            $v = is_scalar($v) ? (int)$v : 0;
            return $v >= 1900 && $v <= 2100 ? $v : null;
        };
        $desde = $anio($g['desde'] ?? null);
        $hasta = $anio($g['hasta'] ?? null);
        if ($desde && $hasta && $desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        $q = is_scalar($g['q'] ?? null) ? mb_substr(trim((string)$g['q']), 0, 200) : '';
        $orden = is_scalar($g['orden'] ?? null) ? (string)$g['orden'] : '';
        if (!isset(self::ORDENES[$orden]) || ($orden === 'relevancia' && $q === '')) {
            $orden = $q !== '' ? 'relevancia' : 'caja';
        }
        $pagina = is_scalar($g['pagina'] ?? null) ? max(1, min(500, (int)$g['pagina'])) : 1;
        $primera = is_scalar($g['primera'] ?? null) ? max(1, min($pagina, (int)$g['primera'])) : $pagina;
        return ['q' => $q, 'sel' => $sel, 'desde' => $desde, 'hasta' => $hasta, 'orden' => $orden, 'pagina' => $pagina, 'primera' => $primera];
    }

    /** Parámetros de URL de un estado (sin los vacíos ni el orden por defecto). @return array<string, string|list<string>> */
    public static function parametros(array $e, array $cambios = []): array
    {
        $e = array_replace_recursive($e, $cambios);
        $p = [];
        if ($e['q'] !== '') {
            $p['q'] = $e['q'];
        }
        foreach ($e['sel'] as $k => $v) {
            if ($v) {
                $p[$k] = array_values($v);
            }
        }
        if ($e['desde']) {
            $p['desde'] = $e['desde'];
        }
        if ($e['hasta']) {
            $p['hasta'] = $e['hasta'];
        }
        if ($e['orden'] !== ($e['q'] !== '' ? 'relevancia' : 'caja')) {
            $p['orden'] = $e['orden'];
        }
        if (($e['pagina'] ?? 1) > 1) {
            $p['pagina'] = $e['pagina'];
        }
        return $p;
    }

    /** Filtros de Buscador para un estado. */
    private static function filtros(array $e, array $terminos, array $etapas): array
    {
        $s = $e['sel'];
        $f = [];
        if (count($s['disponible']) === 1) {
            $f['digitalizado'] = $s['disponible'][0] === 'digital' ? 1 : 0;
        }
        if (count($s['acceso']) === 1) {
            $f['acceso'] = [$s['acceso'][0] === 'a-pedido' ? 'restringido' : 'publico'];
        }
        foreach (['tipo' => 'tipo_documento', 'descriptor' => 'descriptor'] as $k => $voc) {
            if ($s[$k]) {
                $ids = [];
                foreach ($s[$k] as $slug) {
                    array_push($ids, ...($terminos[$voc][$slug]['ids'] ?? []));
                }
                $f[$k] = $ids ?: [-1]; // un slug que ya no existe no devuelve nada (y se ve el chip para quitarlo)
            }
        }
        if ($s['decada']) {
            $dec = array_map('intval', array_filter($s['decada'], fn($d) => $d !== 'sin-fecha'));
            if ($dec) {
                $f['decada'] = $dec;
            }
            if (in_array('sin-fecha', $s['decada'], true)) {
                $f['sin_anio'] = true;
            }
        }
        if ($e['desde']) {
            $f['anio_desde'] = $e['desde'];
        }
        if ($e['hasta']) {
            $f['anio_hasta'] = $e['hasta'];
        }
        if ($s['caja']) {
            $f['caja'] = array_map('intval', $s['caja']);
        }
        if ($s['etapa']) {
            $porSlug = array_column(array_filter($etapas, fn($x) => $x['id'] !== null), 'id', 'slug');
            $f['etapa'] = array_values(array_filter(array_map(fn($sl) => $porSlug[$sl] ?? null, $s['etapa']))) ?: [-1];
        }
        return $f;
    }

    /** Busca y arma todo lo que necesitan las plantillas de Explorar. */
    private static function resultados(array $e): array
    {
        $s = self::$sitio;
        $b = new Buscador(aida_db());
        $terminos = $s->terminos();
        $etapas = $s->etapas();
        $f = self::filtros($e, $terminos, $etapas);
        $r = $b->buscar($e['q'], $f, $e['pagina'], self::POR_PAGINA, self::ORDENES[$e['orden']][1]);
        $tarjetasPagina = $s->tarjetasDe(array_column($r['resultados'], 'id'));
        // Con “Mostrar más” (JS) la URL guarda desde qué página se ve (primera=1&pagina=3): se arma la lista acumulada,
        // hasta 10 páginas, para que recargar o volver muestre lo mismo.
        $tarjetas = $tarjetasPagina;
        $e['primera'] = max($e['primera'], $r['pagina'] - 9);
        if ($e['primera'] < $r['pagina']) {
            $antes = [];
            for ($pg = $e['primera']; $pg < $r['pagina']; $pg++) {
                array_push($antes, ...array_column($b->buscar($e['q'], $f, $pg, self::POR_PAGINA, self::ORDENES[$e['orden']][1])['resultados'], 'id'));
            }
            $tarjetas = array_merge($s->tarjetasDe($antes), $tarjetasPagina);
        } else {
            $e['primera'] = $r['pagina'];
        }
        $todo = $b->buscar('', [], 1, 1)['total'];

        $fa = $r['facetas'];
        $opciones = [
            'disponible' => self::opcionesFijas(['digital' => 'Con documento digital', 'ficha' => 'Solo ficha (sin digitalizar)'],
                ['digital' => self::cant($fa['digitalizado'], 1), 'ficha' => self::cant($fa['digitalizado'], 0)]),
            'acceso' => self::opcionesFijas(['libre' => 'Consulta libre', 'a-pedido' => 'Consulta a pedido'],
                ['libre' => self::cant($fa['acceso'], 'publico'), 'a-pedido' => self::cant($fa['acceso'], 'restringido')]),
            'tipo' => self::opcionesTermino($fa['tipo'], $terminos['tipo_documento']),
            'decada' => self::opcionesDecada($fa['decada']),
            'etapa' => [],
            'caja' => array_map(fn($c) => ['v' => (string)$c['valor'], 'nombre' => $c['valor'] . '. ' . $c['etiqueta'], 'n' => $c['cantidad']], $fa['caja']),
            'descriptor' => self::opcionesTermino($fa['descriptor'], $terminos['descriptor']),
        ];
        $conEtapa = array_filter($etapas, fn($x) => $x['id'] !== null && $x['documentos'] > 0);
        $nEtapa = array_column($fa['etapa'], 'cantidad', 'valor');
        foreach ($conEtapa as $x) {
            $opciones['etapa'][] = ['v' => $x['slug'], 'nombre' => $x['nombre'] . ($x['desde'] ? " ({$x['desde']}–{$x['hasta']})" : ''), 'n' => $nEtapa[$x['id']] ?? 0];
        }
        usort($opciones['tipo'], fn($a, $b) => $b['n'] <=> $a['n'] ?: strcmp($a['nombre'], $b['nombre']));
        usort($opciones['descriptor'], fn($a, $b) => $b['n'] <=> $a['n'] ?: strcmp($a['nombre'], $b['nombre']));
        // Las opciones en 0 se ocultan, salvo las elegidas (que se agregan aunque ya no existan, para poder quitarlas)
        foreach ($opciones as $k => &$ops) {
            if (!in_array($k, ['disponible', 'acceso'], true)) {
                $ops = array_values(array_filter($ops, fn($o) => $o['n'] > 0 || in_array($o['v'], $e['sel'][$k], true)));
            }
            $vs = array_column($ops, 'v');
            foreach ($e['sel'][$k] as $v) {
                if (!in_array($v, $vs, true)) {
                    $ops[] = ['v' => $v, 'nombre' => self::nombreValor($k, $v, $terminos, $etapas), 'n' => 0];
                }
            }
            foreach ($ops as &$o) {
                $o['elegida'] = in_array($o['v'], $e['sel'][$k], true);
            }
            unset($o);
        }
        unset($ops);
        // Etapa: la faceta existe solo cuando hay cajas publicadas asignadas a alguna etapa (D2)
        $facetas = array_filter(self::FACETAS, fn($k) => $k !== 'etapa' || $opciones['etapa'], ARRAY_FILTER_USE_KEY);

        $chips = self::chips($e, $opciones, $terminos, $etapas);
        $hayFiltros = $chips !== [];
        $conteo = !$hayFiltros && $r['total'] === $todo
            ? Html::numero($r['total']) . ' documentos en el archivo'
            : Html::numero($r['total']) . ($r['total'] === 1 ? ' resultado' : ' resultados');
        $sugerencias = [];
        if ($r['total'] === 0) {
            foreach ($chips as $ch) {
                $e2 = self::leerEstado(self::parametrosDesdeUrl($ch['quitar']));
                $sugerencias[] = $ch + ['n' => $b->buscar($e2['q'], self::filtros($e2, $terminos, $etapas), 1, 1)['total']];
            }
        }
        return [
            'e' => $e, 'r' => $r, 'tarjetas' => $tarjetas, 'tarjetasPagina' => $tarjetasPagina, 'facetas' => $facetas, 'opciones' => $opciones, 'chips' => $chips,
            'conteo' => $conteo, 'todo' => $todo, 'sugerencias' => $sugerencias,
            'nFiltros' => count(array_filter($chips, fn($c) => $c['faceta'] !== 'q')),
        ];
    }

    private static function cant(array $faceta, int|string $valor): int
    {
        foreach ($faceta as $o) {
            if ((string)$o['valor'] === (string)$valor) {
                return (int)$o['cantidad'];
            }
        }
        return 0;
    }

    private static function opcionesFijas(array $nombres, array $n): array
    {
        $out = [];
        foreach ($nombres as $v => $nombre) {
            $out[] = ['v' => $v, 'nombre' => $nombre, 'n' => $n[$v] ?? 0];
        }
        return $out;
    }

    /**
     * Opciones de tipo o descriptor por slug (los términos con la misma grafía se juntan; la cantidad es la del que
     * más tiene, para no contar dos veces un registro que tenga los dos).
     */
    private static function opcionesTermino(array $faceta, array $terminos): array
    {
        $slugDe = [];
        foreach ($terminos as $slug => $t) {
            foreach ($t['ids'] as $id) {
                $slugDe[$id] = $slug;
            }
        }
        $out = [];
        foreach ($faceta as $o) {
            $slug = $slugDe[(int)$o['valor']] ?? null;
            if ($slug === null) {
                continue; // descartado, fusionado o basura: no se ofrece
            }
            $out[$slug] ??= ['v' => $slug, 'nombre' => $terminos[$slug]['nombre'], 'n' => 0];
            $out[$slug]['n'] = max($out[$slug]['n'], (int)$o['cantidad']);
        }
        return array_values($out);
    }

    private static function opcionesDecada(array $faceta): array
    {
        return array_map(fn($o) => $o['valor'] === null
            ? ['v' => 'sin-fecha', 'nombre' => 'Sin fecha', 'n' => (int)$o['cantidad']]
            : ['v' => (string)$o['valor'], 'nombre' => 'Década de ' . $o['valor'], 'n' => (int)$o['cantidad']], $faceta);
    }

    private static function nombreValor(string $k, string $v, array $terminos, array $etapas): string
    {
        return match ($k) {
            'tipo' => $terminos['tipo_documento'][$v]['nombre'] ?? $v,
            'descriptor' => $terminos['descriptor'][$v]['nombre'] ?? $v,
            'decada' => $v === 'sin-fecha' ? 'Sin fecha' : 'Década de ' . $v,
            'etapa' => array_column($etapas, 'nombre', 'slug')[$v] ?? $v,
            'caja' => 'Caja ' . $v,
            'disponible' => $v === 'digital' ? 'Con documento digital' : 'Solo ficha (sin digitalizar)',
            'acceso' => $v === 'libre' ? 'Consulta libre' : 'Consulta a pedido',
            default => $v,
        };
    }

    /** Filtros activos, cada uno con la URL que lo quita. @return list<array{faceta:string, titulo:string, valor:string, quitar:string}> */
    private static function chips(array $e, array $opciones, array $terminos, array $etapas): array
    {
        $out = [];
        $e1 = ['pagina' => 1] + $e;
        if ($e['q'] !== '') {
            $out[] = ['faceta' => 'q', 'titulo' => 'Búsqueda', 'valor' => '“' . $e['q'] . '”',
                      'quitar' => self::$enl->explorar(self::parametros(['q' => ''] + $e1))];
        }
        foreach (self::FACETAS as $k => $def) {
            foreach ($e['sel'][$k] as $v) {
                $nombre = null;
                foreach ($opciones[$k] as $o) {
                    if ($o['v'] === $v) {
                        $nombre = $o['nombre'];
                    }
                }
                $sel = $e['sel'];
                $sel[$k] = array_values(array_diff($sel[$k], [$v]));
                $out[] = ['faceta' => $k, 'titulo' => $def['titulo'], 'valor' => $nombre ?? self::nombreValor($k, $v, $terminos, $etapas),
                          'quitar' => self::$enl->explorar(self::parametros(['sel' => $sel] + $e1))];
            }
        }
        if ($e['desde'] || $e['hasta']) {
            $out[] = ['faceta' => 'rango', 'titulo' => 'Años', 'valor' => ($e['desde'] ?: '…') . '–' . ($e['hasta'] ?: '…'),
                      'quitar' => self::$enl->explorar(self::parametros(['desde' => null, 'hasta' => null] + $e1))];
        }
        return $out;
    }

    /** Parámetros de una URL de Explorar armada por Enlaces (para recalcular las sugerencias). */
    private static function parametrosDesdeUrl(string $url): array
    {
        parse_str((string)parse_url($url, PHP_URL_QUERY), $p);
        return $p;
    }

    // ------------------------------------------------------------------
    // Cajas, caja y ficha
    // ------------------------------------------------------------------

    private static function cajas(): void
    {
        $todas = self::$sitio->cajas();
        $q = is_scalar($_GET['q'] ?? null) ? mb_substr(trim((string)$_GET['q']), 0, 80) : '';
        $orden = in_array($_GET['orden'] ?? '', ['numero', 'nombre', 'anio', 'digit'], true) ? $_GET['orden'] : 'numero';
        $lista = $q === '' ? $todas : array_values(array_filter($todas, fn($c) => str_contains(Texto::clave($c['nombre'] . ' ' . $c['numero']), Texto::clave($q))
            || (ctype_digit($q) && (int)$q === $c['numero'])));
        if ($orden === 'nombre') {
            usort($lista, fn($a, $b) => strcmp(Texto::clave($a['nombre']), Texto::clave($b['nombre'])));
        } elseif ($orden === 'anio') {
            // Por el primer año de la caja; las que no tienen años, al final
            usort($lista, fn($a, $b) => [$a['desde'] === null, $a['desde'], $a['numero']] <=> [$b['desde'] === null, $b['desde'], $b['numero']]);
        } elseif ($orden === 'digit') {
            usort($lista, fn($a, $b) => $b['digitales'] <=> $a['digitales'] ?: $a['numero'] <=> $b['numero']);
        }
        self::pagina('cajas', [
            'cajas' => $lista, 'total' => count($todas), 'totalArchivo' => self::$sitio->totalCajasArchivo(), 'q' => $q, 'orden' => $orden,
            'seccion' => 'cajas',
            'robots' => $q !== '' || $orden !== 'numero' ? 'noindex,follow' : null,
            'meta' => ['descripcion' => 'Las cajas del archivo de Aída Bortnik: cada una reúne el material de una obra o un proyecto, tal como ella lo guardó.',
                       'canonical' => Enlaces::absoluta('cajas')],
        ]);
    }

    private static function caja(string $numero, string $slug): void
    {
        $d = self::$sitio->cajaPorNumero((int)$numero);
        if (!$d) {
            self::noEncontrado();
        }
        $ruta = Enlaces::rutaCaja($d['caja']);
        if ($slug !== Sitio::slug($d['caja']['nombre']) || $numero !== (string)(int)$d['caja']['numero']) {
            self::redirigir(self::$enl->caja($d['caja']), 301);
        }
        $c = $d['caja'];
        $resumen = trim((string)$c['resumen']);
        $cf = $d['cifras'];
        $desc = $resumen !== '' ? self::recortar($resumen, 160)
            : "Caja {$c['numero']} del archivo de Aída Bortnik: {$cf['documentos']} documentos" . ($cf['desde'] ? ", {$cf['desde']}–{$cf['hasta']}" : '') . '.';
        self::pagina('caja', ['d' => $d, 'meta' => ['descripcion' => $desc, 'canonical' => Enlaces::absoluta($ruta)]]);
    }

    private static function registro(string $codigo): void
    {
        $p = Texto::parsearCodigo($codigo);
        if ($p === null || $p[2] !== 0) {
            self::noEncontrado();
        }
        $canon = Texto::codigo($p[0], $p[1]);
        if ($canon !== $codigo) {
            self::redirigir(self::$enl->ruta(Enlaces::rutaRegistro($canon)), 301); // c14r3, C14R03 → C14R3
        }
        $d = self::$sitio->fichaPorCodigo($codigo);
        if (!$d) {
            $estado = self::$sitio->estadoCodigo($codigo);
            if ($estado && $estado['estado'] === 'oculto') {
                self::pagina('no_disponible', ['caja' => $estado['caja'], 'codigo' => $canon, 'robots' => 'noindex'], 410);
            }
            self::noEncontrado();
        }
        $r = $d['registro'];
        $resumen = trim((string)$r['resumen']);
        $desc = $resumen !== '' ? self::recortar($resumen, 160)
            : "{$d['titulo']}. Caja {$d['caja']['numero']}: {$d['caja']['nombre']}, código $canon. Archivo “Las cajas de Aída”.";
        self::pagina('ficha', ['d' => $d, 'meta' => ['descripcion' => $desc, 'canonical' => Enlaces::canonica($canon), 'og_tipo' => 'article']]);
    }

    // ------------------------------------------------------------------
    // Consulta a pedido
    // ------------------------------------------------------------------

    private static function consulta(string $codigo): void
    {
        $d = self::$sitio->fichaPorCodigo($codigo);
        if (!$d) {
            self::noEncontrado();
        }
        $canon = (string)$d['registro']['codigo'];
        if ($canon !== $codigo) {
            self::redirigir(self::$enl->consulta($canon, ($_GET['motivo'] ?? '') === 'original' ? 'original' : null), 301);
        }
        $tipo = $d['documento'] === 'pedido' ? 'a_pedido' : 'original';
        $errores = [];
        $valores = ['modalidad' => 'digital'];
        $aviso = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            [$errores, $valores, $aviso, $id] = self::recibirConsulta($d, $tipo);
            if ($id !== null) {
                self::redirigir(self::$enl->ruta('consulta/' . rawurlencode($canon) . '/enviada', $id > 0 ? ['n' => $id, 'k' => self::firma("consulta|$id")] : []), 303);
            }
        }
        [$token, $instante] = self::tokenFormulario();
        $tarjeta = self::$sitio->tarjetasDe([(int)$d['registro']['id']])[0] ?? null;
        header('Cache-Control: private, no-store');
        self::pagina('consulta', [
            'd' => $d, 't' => $tarjeta, 'tipo' => $tipo, 'errores' => $errores, 'valores' => $valores, 'aviso' => $aviso,
            'token' => $token, 'instante' => $instante, 'robots' => 'noindex,nofollow',
            'meta' => ['descripcion' => "Pedido de consulta del documento $canon del archivo “Las cajas de Aída”.", 'canonical' => Enlaces::absoluta('consulta/' . $canon)],
        ], $errores || $aviso ? 422 : 200);
    }

    /**
     * Procesa el POST del formulario. Devuelve [errores, valores, aviso general, id guardado]. id 0 = campo trampa
     * completado (se responde como si se hubiera recibido, sin guardar: a un robot no se le explica nada).
     * @return array{0: array<string,string>, 1: array<string,mixed>, 2: ?string, 3: ?int}
     */
    private static function recibirConsulta(array $d, string $tipo): array
    {
        $cookie = (string)($_COOKIE['aida_form'] ?? '');
        $token = (string)($_POST['_t'] ?? '');
        [$errores, $valores] = Consultas::validar($_POST);
        if ($cookie === '' || !hash_equals($cookie, $token)) {
            return [[], $valores, 'El formulario venció o no es válido. Revisá los datos y volvé a enviarlo.', null];
        }
        if (trim((string)($_POST['sitio_web'] ?? '')) !== '') {
            return [[], $valores, null, 0];
        }
        $inst = explode('.', (string)($_POST['_i'] ?? ''), 2);
        $t = (int)($inst[0] ?? 0);
        if (count($inst) !== 2 || !hash_equals(self::firma("form|$t|$cookie"), $inst[1]) || time() - $t > 3 * 3600) {
            return [[], $valores, 'El formulario venció. Revisá los datos y volvé a enviarlo.', null];
        }
        if (time() - $t < 3) {
            return [[], $valores, 'Lo enviaste muy rápido. Esperá unos segundos y volvé a enviarlo.', null];
        }
        if (!\Aida\Turnstile::verificar('consulta')) {
            return [[], $valores, 'No pudimos verificar que no seas un robot. Esperá a que aparezca el tilde y volvé a enviarlo.', null];
        }
        if ($errores) {
            return [$errores, $valores, null, null];
        }
        $c = new Consultas(aida_db());
        $ip = isset($_SERVER['REMOTE_ADDR']) ? self::firma('ip|' . $_SERVER['REMOTE_ADDR']) : null;
        if ($c->excedeLimite($ip)) {
            return [[], $valores, 'Recibimos varios pedidos seguidos desde tu conexión. Probá de nuevo en una hora.', null];
        }
        return [[], $valores, null, $c->guardar($d['registro'], $tipo, $valores, $ip)];
    }

    /**
     * Formulario «¿Querés llevar la muestra a tu espacio?» del sitio institucional (mismo dominio). Responde JSON.
     * Defensas: mismo origen, campo trampa, Turnstile y como mucho 5 envíos por hora por conexión. Se manda por correo
     * a config 'muestra' => ['destino' => ...] (por defecto lascajasdeaida@gmail.com) con Responder a = quien consulta.
     */
    private static function consultaMuestra(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        $responder = static function (int $codigo, array $cuerpo): never {
            http_response_code($codigo);
            echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
            exit;
        };
        $origen = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($origen === '' || parse_url($origen, PHP_URL_HOST) !== preg_replace('/:\d+$/', '', $host)) {
            $responder(403, ['ok' => false, 'error' => 'Origen no permitido.']);
        }
        if (trim((string)($_POST['sitio_web'] ?? '')) !== '') {
            $responder(200, ['ok' => true]);   // a un robot no se le explica nada
        }
        $campos = ['espacio' => 200, 'ciudad' => 120, 'fechas' => 120, 'superficie' => 40, 'nombre' => 120, 'correo' => 200, 'telefono' => 60, 'mensaje' => 4000];
        $v = [];
        foreach ($campos as $k => $max) {
            $v[$k] = trim(mb_substr(str_replace("\0", '', (string)($_POST[$k] ?? '')), 0, $max));
        }
        $errores = [];
        foreach (['espacio' => 'el espacio o institución', 'ciudad' => 'la ciudad', 'nombre' => 'tu nombre'] as $k => $txt) {
            if ($v[$k] === '') {
                $errores[$k] = "Falta $txt.";
            }
        }
        if (!filter_var($v['correo'], FILTER_VALIDATE_EMAIL)) {
            $errores['correo'] = 'Escribí un correo válido.';
        }
        if ($errores) {
            $responder(422, ['ok' => false, 'errores' => $errores]);
        }
        if (!\Aida\Turnstile::verificar('muestra')) {
            $responder(422, ['ok' => false, 'error' => 'No pudimos verificar que no seas un robot. Esperá a que aparezca el tilde y volvé a enviarlo.']);
        }
        if (self::muestraExcedeLimite()) {
            $responder(429, ['ok' => false, 'error' => 'Recibimos varios envíos seguidos desde tu conexión. Probá de nuevo en una hora o escribinos a lascajasdeaida@gmail.com.']);
        }
        $etq = ['espacio' => 'Espacio o institución', 'ciudad' => 'Ciudad', 'fechas' => 'Fechas tentativas', 'superficie' => 'Superficie aproximada (m²)',
                'nombre' => 'Nombre de contacto', 'correo' => 'Correo', 'telefono' => 'Teléfono', 'mensaje' => 'Mensaje'];
        $texto = "Nueva consulta desde el sitio: «¿Querés llevar la muestra a tu espacio?»\n\n";
        foreach ($etq as $k => $e) {
            $texto .= "$e: " . ($v[$k] !== '' ? $v[$k] : '—') . "\n";
        }
        $texto .= "\nPara responder, contestá este correo: va a {$v['correo']}.\n";
        $destino = (string)(aida_config()['muestra']['destino'] ?? 'lascajasdeaida@gmail.com');
        $asunto = 'Muestra itinerante: consulta de ' . preg_replace('/[\r\n]+/', ' ', $v['espacio']) . ' (' . preg_replace('/[\r\n]+/', ' ', $v['ciudad']) . ')';
        if (!\Aida\Correo::enviar($destino, $asunto, $texto, $v['correo'])) {
            error_log('[aida-muestra] no se pudo mandar la consulta por correo');
            $responder(503, ['ok' => false, 'error' => 'No pudimos enviar la consulta. Escribinos directamente a lascajasdeaida@gmail.com.']);
        }
        $responder(200, ['ok' => true]);
    }

    /** Hasta 5 envíos por hora por conexión (IP firmada, en var/). */
    private static function muestraExcedeLimite(): bool
    {
        $dir = dirname(__DIR__, 2) . '/var/limites';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
            return false;
        }
        $f = $dir . '/muestra-' . substr(self::firma('ip|' . ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 32);
        $ahora = time();
        $marcas = array_filter(array_map('intval', is_file($f) ? (array)file($f, FILE_IGNORE_NEW_LINES) : []), fn($t) => $ahora - $t < 3600);
        if (count($marcas) >= 5) {
            return true;
        }
        $marcas[] = $ahora;
        @file_put_contents($f, implode("\n", $marcas), LOCK_EX);
        return false;
    }

    private static function consultaEnviada(string $codigo): void
    {
        $d = self::$sitio->fichaPorCodigo($codigo);
        if (!$d) {
            self::noEncontrado();
        }
        $n = is_scalar($_GET['n'] ?? null) ? (int)$_GET['n'] : 0;
        $numero = null;
        if ($n > 0 && hash_equals(self::firma("consulta|$n"), (string)($_GET['k'] ?? ''))) {
            $c = (new Consultas(aida_db()))->buscar($n);
            $numero = $c && $c['codigo'] === $d['registro']['codigo'] ? Consultas::numero($c) : null;
        }
        header('Cache-Control: private, no-store');
        self::pagina('consulta_enviada', ['d' => $d, 'numero' => $numero, 'robots' => 'noindex,nofollow',
            'meta' => ['descripcion' => 'Pedido de consulta recibido.', 'canonical' => Enlaces::absoluta('consulta/' . $d['registro']['codigo'])]]);
    }

    /**
     * CSRF sin sesión: cookie aleatoria (SameSite=Strict, HttpOnly) que el formulario repite en un campo oculto.
     * Más el instante en que se abrió, firmado, para el tiempo mínimo. @return array{0:string, 1:string}
     */
    private static function tokenFormulario(): array
    {
        $token = (string)($_COOKIE['aida_form'] ?? '');
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            $token = bin2hex(random_bytes(24));
            setcookie('aida_form', $token, [
                'expires' => time() + 6 * 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
        }
        $t = time();
        return [$token, $t . '.' . self::firma("form|$t|$token")];
    }

    /** HMAC con el secreto del sitio (config 'secreto' o var/secreto, que se crea solo y no se versiona). */
    public static function firma(string $dato): string
    {
        return hash_hmac('sha256', $dato, self::secreto());
    }

    private static function secreto(): string
    {
        static $s = null;
        if ($s !== null) {
            return $s;
        }
        $cfg = (string)(aida_config()['secreto'] ?? '');
        if (strlen($cfg) >= 32) {
            return $s = $cfg;
        }
        $dir = dirname(__DIR__, 2) . '/var';
        $f = "$dir/secreto";
        if (!is_file($f)) {
            if (!is_dir($dir)) {
                mkdir($dir, 0700, true);
            }
            file_put_contents($f, bin2hex(random_bytes(32)), LOCK_EX);
            chmod($f, 0600);
        }
        return $s = trim((string)file_get_contents($f));
    }

    // ------------------------------------------------------------------
    // Sobre, sitemap, robots, 404
    // ------------------------------------------------------------------

    private static function sobre(): void
    {
        self::pagina('sobre', ['cifras' => self::$sitio->cifras(), 'seccion' => 'sobre',
            'meta' => ['descripcion' => self::FRASE . '.', 'canonical' => Enlaces::absoluta('sobre')]]);
    }

    private static function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        $s = self::$sitio->sitemap();
        $url = fn(string $loc, ?string $mod = null) => '<url><loc>' . Html::e($loc) . '</loc>'
            . ($mod ? '<lastmod>' . date('Y-m-d', strtotime($mod)) . '</lastmod>' : '') . "</url>\n";
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (['', 'cajas', 'sobre'] as $r) {
            echo $url(Enlaces::absoluta($r));
        }
        foreach ($s['cajas'] as $c) {
            echo $url(Enlaces::absoluta(Enlaces::rutaCaja($c)), $c['modificado']);
        }
        foreach ($s['registros'] as $r) {
            echo $url(Enlaces::canonica($r['codigo']), $r['modificado']);
        }
        echo "</urlset>\n";
    }

    private static function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /admin/\nDisallow: /explorar.json\n\nSitemap: " . Enlaces::absoluta('sitemap.xml') . "\n";
    }

    private static function noEncontrado(): never
    {
        self::pagina('pagina_404', ['robots' => 'noindex'], 404);
    }

    // ------------------------------------------------------------------
    // Render
    // ------------------------------------------------------------------

    /** Página completa con el layout del sitio (las plantillas de vistas/publico/ terminan en _sitio.php). */
    private static function pagina(string $vista, array $datos, int $codigo = 200): never
    {
        http_response_code($codigo);
        header('Content-Type: text/html; charset=utf-8');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            exit;
        }
        $equipo = self::equipo();
        if ($equipo !== null) {
            header('Cache-Control: private, no-store'); // la página lleva el botón de edición: no se guarda en cachés compartidas
        }
        // Medición (docs/medicion.md): solo acá, nunca en el backoffice ni en su vista previa
        $medicion = Medicion::pagina($vista, $datos, $equipo !== null);
        echo self::parcial("publico/$vista", $datos + ['enl' => self::$enl, 'equipo' => $equipo, 'medicion' => $medicion]);
        exit;
    }

    /**
     * Si quien mira el sitio tiene abierta una sesión del backoffice con permiso de edición, sus datos y la URL del
     * backoffice (para el botón “Editar en el backoffice” de la ficha y la caja). Sin la cookie de sesión no se abre
     * ninguna sesión: el público no recibe cookies.
     *
     * @return array{nombre:string, admin:string}|null
     */
    private static function equipo(): ?array
    {
        if (empty($_COOKIE['aida_bo']) || !is_string($_COOKIE['aida_bo'])) {
            return null;
        }
        App::iniciarSesion();
        $u = App::usuario();
        $puede = App::puede('editar');
        session_write_close();
        if ($u === null || !$puede) {
            return null;
        }
        return ['nombre' => (string)($u['nombre'] ?: $u['email']), 'admin' => self::$enl->base . 'admin/'];
    }

    private static function parcial(string $vista, array $datos): string
    {
        extract($datos, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__, 2) . "/vistas/$vista.php";
        return (string)ob_get_clean();
    }

    private static function redirigir(string $url, int $codigo): never
    {
        header('Location: ' . $url, true, $codigo);
        exit;
    }

    private static function recortar(string $s, int $max): string
    {
        $s = trim((string)preg_replace('/\s+/u', ' ', $s));
        return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, $max - 1)) . '…' : $s;
    }
}
