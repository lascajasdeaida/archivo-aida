<?php
declare(strict_types=1);

namespace Aida;

/** Utilidades de normalización de texto y de la nomenclatura C{caja}R{registro}. */
final class Texto
{
    /** Minúsculas, sin tildes, espacios colapsados. Para comparar encabezados y vocabularios. */
    public static function clave(?string $s): string
    {
        $s = trim((string)$s);
        $s = mb_strtolower($s, 'UTF-8');
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
                        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'º' => 'o', '°' => 'o']);
        return (string)preg_replace('/\s+/u', ' ', $s);
    }

    /** Celda a texto limpio o null. */
    public static function limpio(mixed $v): ?string
    {
        if ($v === null || is_bool($v)) {
            return null;
        }
        if (is_float($v) && floor($v) === $v) {
            $v = (int)$v;
        }
        $s = trim((string)$v);
        $s = (string)preg_replace('/[ \t]+/u', ' ', $s);
        // Vacío o solo signos («-», «.», «?»): no es un dato
        return ($s === '' || !preg_match('/[\p{L}\p{N}]/u', $s)) ? null : $s;
    }

    public static function entero(mixed $v): ?int
    {
        if (is_int($v)) {
            return $v;
        }
        if (is_float($v) && floor($v) === $v) {
            return (int)$v;
        }
        if (is_string($v) && preg_match('/^\s*(\d+)\s*$/', $v, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    /** Años que se aceptan como dato (fuera de esto es un error de tipeo o no es un año). */
    public const ANIO_MIN = 1900;
    public const ANIO_MAX = 2100;

    private const MESES = 'enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre'
        . '|january|february|march|april|may|june|july|august|september|october|november|december'
        . '|ene|feb|mar|abr|may|jun|jul|ago|sep|sept|set|oct|nov|dic|jan|apr|aug|dec';

    /**
     * Interpreta la columna Año (K) del Excel. Tabla de formatos en docs/decisiones.md («Años»).
     *
     * Devuelve los años a los que responde el registro y un resumen:
     *   anios          [año => sin confirmar (bool)], ordenado. Es lo que filtra (tabla registro_anio).
     *   desde, hasta   el menor y el mayor (resumen para ordenar y mostrar; registro.anio / anio_hasta)
     *   sin_confirmar  si alguno de los años es dudoso (registro.anio_sin_confirmar)
     *   formato        cómo se leyó:
     *   anio      «1972», 1972                         → 1972
     *   duda      «1972?», «¿1984?», «1977(¿?)»        → 1972, sin confirmar
     *   rango     «1960 a 2009», «Entre 1983 y 1986», «Desde 1971 hasta 1991», «1975-1979» → todos los años del rango
     *   lista     «1981/82», «2004/ 2005», «1981/82/83», «1969. 1970. 1971», «1998/2006/2007/2008», «1988 y 1989»
     *             → solo los años escritos (si son seguidos, es lo mismo que un rango)
     *   o         «1977 o 1978»                        → los dos, sin confirmar (es uno de los dos)
     *   fecha     «03/02/2006», «02/02/1982 (sobre)», «20 al 25 de junio de 1988» → el año de la fecha
     *   decada    «años 70», «década del 80», «1970s»  → 1970 a 1979
     *   varios    «1980? 1996/97/98/2006»: partes separadas por espacios, cada una con su propia duda
     *             → 1980 sin confirmar; 1996, 1997, 1998 y 2006 confirmados
     *   sin_fecha «sin fecha», «s/f», «Sin dato», «No tiene fecha», «sin precisar», solo signos → sin año
     *   vacio     celda vacía                          → sin año
     *   ilegible  cualquier otra cosa («Posterior a 1990…», «Año XXII…») → sin año; el importador lo informa
     * Con «?» en una parte, los años de esa parte quedan sin confirmar.
     * @return array{anios:array<int,bool>, desde:?int, hasta:?int, sin_confirmar:bool, formato:string}
     */
    public static function anio(mixed $v): array
    {
        $nada = fn(string $f) => ['anios' => [], 'desde' => null, 'hasta' => null, 'sin_confirmar' => false, 'formato' => $f];
        /** @param list<int> $lista */
        $ok = function (array $lista, bool $duda, string $f) use ($nada): array {
            $anios = [];
            foreach ($lista as $a) {
                if ($a < self::ANIO_MIN || $a > self::ANIO_MAX) {
                    return $nada('ilegible');
                }
                $anios[$a] = $duda;
            }
            return self::resumenAnios($anios, $f) ?? $nada('ilegible');
        };
        $rango = fn(int $d, int $h) => $d > $h ? range($h, $d) : range($d, $h);
        if (is_int($v) || (is_float($v) && floor($v) === $v)) {
            return $ok([(int)$v], false, 'anio');
        }
        if ($v === null || is_bool($v) || trim((string)$v) === '') {
            return $nada('vacio');
        }
        $s = trim((string)preg_replace('/\s+/u', ' ', (string)$v));
        if (!preg_match('/[\p{L}\p{N}]/u', $s)) {
            return $nada('sin_fecha');      // «?», «????», «-»
        }
        $k = self::clave($s);
        if (preg_match('~^(sin fecha|s/ ?f\.?|s/ ?d\.?|sin dato|sin datos|no tiene fecha|sin precisar|no figura|sin año|sin ano|n/d)$~u', $k)) {
            return $nada('sin_fecha');
        }
        $duda = str_contains($s, '?');
        // Lo que queda sin signos de duda ni paréntesis vacíos: «1977(¿?)» → «1977»
        $t = trim((string)preg_replace(['/[¿?]+/u', '/\(\s*\)/u'], ['', ''], $k));
        $t = trim($t, " .,;");

        // Año solo
        if (preg_match('/^(\d{4})$/', $t, $m)) {
            return $ok([(int)$m[1]], $duda, $duda ? 'duda' : 'anio');
        }
        // Década: «años 70», «década del 80», «1970s», «los 70»
        if (preg_match('/^(?:(?:los )?anos|(?:la )?decada(?: de(?:l| los)?)?|los) ?\'?(\d{2}|\d{4})s?$|^(\d{4})s$/u', $t, $m)) {
            $n = (int)($m[1] !== '' ? $m[1] : $m[2]);
            $n = $n < 100 ? 1900 + $n : $n;
            if ($n % 10 === 0) {
                return $ok(range($n, $n + 9), $duda, 'decada');
            }
        }
        // Fecha completa: «03/02/2006», «02/02/1982 (sobre)», «3-2-2006»
        if (preg_match('~^\d{1,2}[/.\-]\d{1,2}[/.\-](\d{4})(?: \([^)]*\))?$~u', $t, $m)) {
            return $ok([(int)$m[1]], $duda, 'fecha');
        }
        // Fecha escrita: «20 al 25 de junio de 1988», «junio de 1988», «30 marzo 1991»
        if (preg_match('/^(?:(?:\d{1,2}(?: (?:al|y|a) \d{1,2})? )?(?:de )?)?(?:' . self::MESES . ')\.?(?: de| del)? (\d{4})$/u', $t, $m)) {
            return $ok([(int)$m[1]], $duda, 'fecha');
        }
        // Rango con palabras: «Entre 1983 y 1986», «Desde 1971 hasta 1991», «1960 a 2009»
        if (preg_match('/^(?:entre (\d{4}) y|(?:desde |de )?(\d{4}) ?(?:a|al|hasta|-|–)) ?(\d{4})$/u', $t, $m)) {
            return $ok($rango((int)($m[1] !== '' ? $m[1] : $m[2]), (int)$m[3]), $duda, 'rango');
        }
        // Uno u otro, o dos años sueltos: «1977 o 1978» (sin confirmar), «1988 y 1989»
        if (preg_match('/^(\d{4}) (o|y) (\d{4})$/u', $t, $m)) {
            return $ok([(int)$m[1], (int)$m[3]], $duda || $m[2] === 'o', $m[2] === 'o' ? 'o' : 'lista');
        }
        // Listas con barra, punto o coma; rangos con guion: «1981/82», «2004/ 2005», «(1981.82,83)», «1975-1979»
        $u = trim($t, '() ');
        if (($l = self::listaAnios($u)) !== null) {
            return $ok($l[0], $duda, $l[1]);
        }
        // Varias partes separadas por espacios, cada una con su duda: «1980? 1996/97/98/2006»
        $partes = preg_split('/ +/u', $s) ?: [];
        if (count($partes) > 1) {
            $anios = [];
            foreach ($partes as $p) {
                $r = self::anio($p);
                if (!$r['anios'] || in_array($r['formato'], ['fecha', 'decada'], true)) {
                    return $nada('ilegible');
                }
                foreach ($r['anios'] as $a => $d) {
                    // Si un año aparece confirmado en alguna parte, queda confirmado
                    $anios[$a] = isset($anios[$a]) ? ($anios[$a] && $d) : $d;
                }
            }
            return self::resumenAnios($anios, 'varios') ?? $nada('ilegible');
        }
        return $nada('ilegible');
    }

    /**
     * «1981/82/83», «1998/2006/2007/2008», «1975-1979», «1999/00»: años separados por «/», «,» o «.» (cada uno cuenta solo)
     * o por guion (rango: todos los del medio). Dos dígitos siguen el siglo del año anterior («1999/00» = 1999 y 2000).
     * @return array{0:list<int>,1:string}|null [años, 'lista' | 'rango']
     */
    private static function listaAnios(string $u): ?array
    {
        if (!preg_match('~^\d{4}(?: ?[/,.\-–] ?(?:\d{4}|\d{2}))+$~u', $u)) {
            return null;
        }
        preg_match_all('~(\d+)|([/,.\-–])~u', $u, $mm, PREG_SET_ORDER);
        $anios = [];
        $prev = null;
        $guion = false;
        $hayGuion = false;
        foreach ($mm as $x) {
            if (($x[2] ?? '') !== '') {
                $guion = in_array($x[2], ['-', '–'], true);
                $hayGuion = $hayGuion || $guion;
                continue;
            }
            $n = (int)$x[1];
            if (strlen($x[1]) === 2) {
                $n = intdiv((int)$prev, 100) * 100 + $n;
                if ($n < $prev) {
                    $n += 100;
                }
            }
            if ($guion && $prev !== null) {
                array_push($anios, ...($n >= $prev ? range($prev, $n) : range($n, $prev)));
            } else {
                $anios[] = $n;
            }
            $prev = $n;
            $guion = false;
        }
        return [array_values(array_unique($anios)), $hayGuion && count($mm) === 3 ? 'rango' : 'lista'];
    }

    /**
     * @param array<int,bool> $anios año => sin confirmar
     * @return array{anios:array<int,bool>, desde:int, hasta:int, sin_confirmar:bool, formato:string}|null
     */
    private static function resumenAnios(array $anios, string $formato): ?array
    {
        if (!$anios) {
            return null;
        }
        ksort($anios);
        $k = array_keys($anios);
        return ['anios' => $anios, 'desde' => $k[0], 'hasta' => end($k), 'sin_confirmar' => in_array(true, $anios, true), 'formato' => $formato];
    }

    /** «1972», «1981–1982», con « (sin confirmar)» si corresponde. Resumen desde–hasta; para la lista real, aniosEtiqueta(). */
    public static function anioEtiqueta(?int $desde, ?int $hasta, bool $sinConfirmar): ?string
    {
        if ($desde === null) {
            return null;
        }
        $s = $hasta !== null && $hasta !== $desde ? "{$desde}–{$hasta}" : (string)$desde;
        return $sinConfirmar ? "$s (sin confirmar)" : $s;
    }

    /**
     * Los años a los que responde un registro, como se muestran: «1998, 2006–2008», «1981–1982 (sin confirmar)»,
     * «1980 (sin confirmar), 1996–1998, 2006». Los años seguidos se juntan en un tramo.
     * Si todos están sin confirmar, se aclara una sola vez al final; si no, en cada tramo dudoso.
     * @param array<int,bool|int> $anios año => sin confirmar
     */
    public static function aniosEtiqueta(array $anios): ?string
    {
        if (!$anios) {
            return null;
        }
        ksort($anios);
        $todos = !in_array(false, array_map('boolval', $anios), true);
        $tramos = [];
        foreach ($anios as $a => $d) {
            $d = (bool)$d;
            $u = count($tramos) - 1;
            if ($u >= 0 && $tramos[$u][1] === $a - 1 && ($todos || $tramos[$u][2] === $d)) {
                $tramos[$u][1] = $a;
            } else {
                $tramos[] = [$a, $a, $d];
            }
        }
        $txt = array_map(fn($t) => ($t[0] === $t[1] ? (string)$t[0] : "{$t[0]}–{$t[1]}") . (!$todos && $t[2] ? ' (sin confirmar)' : ''), $tramos);
        return implode(', ', $txt) . ($todos ? ' (sin confirmar)' : '');
    }

    /** Clave más laxa para encontrar equivalentes entre vocabularios: sin signos y sin plural («Referencias de época» = «Referencia de época»). */
    public static function claveFlexible(?string $s): string
    {
        $s = (string)preg_replace('/[^\p{L}\p{N} ]+/u', ' ', self::clave($s));
        $palabras = array_map(fn($w) => strlen($w) > 3 ? rtrim($w, 's') : $w,
            preg_split('/\s+/', trim($s)) ?: []);
        return implode(' ', $palabras);
    }

    /**
     * Para comparar un valor corregido a mano con lo que trae la planilla: no cuentan los espacios ni las
     * mayúsculas, sí las tildes («Aida Bortnik» ≠ «Aída Bortnik»). null y vacío son lo mismo.
     */
    public static function comparable(mixed $v): string
    {
        if ($v === null || is_bool($v)) {
            return '';
        }
        if (is_float($v) && floor($v) === $v) {
            $v = (int)$v;
        }
        return mb_strtolower(trim((string)preg_replace('/\s+/u', ' ', (string)$v)), 'UTF-8');
    }

    /** Código canónico de un registro: C14R3 (sin ceros a la izquierda). */
    public static function codigo(int $caja, int $registro): string
    {
        return "C{$caja}R{$registro}";
    }

    /**
     * Interpreta una nomenclatura escrita a mano o un nombre de archivo:
     * "C10R02", "c10r2", "C14R3.pdf", "C14R3_001.pdf" → [caja, registro, parte].
     * @return array{0:int,1:int,2:int}|null
     */
    public static function parsearCodigo(?string $s): ?array
    {
        if ($s === null) {
            return null;
        }
        $s = trim($s);
        if (!preg_match('/^C0*(\d+)\s*R0*(\d+)(?:[_\-\s]0*(\d+))?(?:\.[A-Za-z0-9]+)?$/i', $s, $m)) {
            return null;
        }
        return [(int)$m[1], (int)$m[2], isset($m[3]) && $m[3] !== '' ? (int)$m[3] : 0];
    }
}
