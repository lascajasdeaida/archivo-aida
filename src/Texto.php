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
     * Devuelve ['desde' => int|null, 'hasta' => int|null, 'sin_confirmar' => bool, 'formato' => string]:
     *   anio      «1972», 1972                         → 1972–1972
     *   duda      «1972?», «¿1984?», «1977(¿?)»        → 1972–1972, sin confirmar
     *   rango     «1981/82», «2004/ 2005», «1981-1983», «1960 a 2009», «Entre 1983 y 1986», «Desde 1971 hasta 1991»
     *   lista     «1981/82/83», «1969. 1970. 1971»     → del menor al mayor
     *   o         «1977 o 1978»                        → rango, sin confirmar (es uno de los dos)
     *   fecha     «03/02/2006», «02/02/1982 (sobre)», «20 al 25 de junio de 1988» → el año de la fecha
     *   decada    «años 70», «década del 80», «1970s»  → 1970–1979
     *   sin_fecha «sin fecha», «s/f», «Sin dato», «No tiene fecha», «sin precisar», solo signos → sin año
     *   vacio     celda vacía                          → sin año
     *   ilegible  cualquier otra cosa («Posterior a 1990…», «Año XXII…») → sin año; el importador lo informa
     * Con «?» en cualquier formato, queda sin confirmar.
     * @return array{desde:?int, hasta:?int, sin_confirmar:bool, formato:string}
     */
    public static function anio(mixed $v): array
    {
        $nada = fn(string $f) => ['desde' => null, 'hasta' => null, 'sin_confirmar' => false, 'formato' => $f];
        $ok = function (int $d, int $h, bool $duda, string $f) use ($nada): array {
            if ($d > $h) {
                [$d, $h] = [$h, $d];
            }
            if ($d < self::ANIO_MIN || $h > self::ANIO_MAX) {
                return $nada('ilegible');
            }
            return ['desde' => $d, 'hasta' => $h, 'sin_confirmar' => $duda, 'formato' => $f];
        };
        if (is_int($v) || (is_float($v) && floor($v) === $v)) {
            return $ok((int)$v, (int)$v, false, 'anio');
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
            return $ok((int)$m[1], (int)$m[1], $duda, $duda ? 'duda' : 'anio');
        }
        // Década: «años 70», «década del 80», «1970s», «los 70»
        if (preg_match('/^(?:(?:los )?anos|(?:la )?decada(?: de(?:l| los)?)?|los) ?\'?(\d{2}|\d{4})s?$|^(\d{4})s$/u', $t, $m)) {
            $n = (int)($m[1] !== '' ? $m[1] : $m[2]);
            $n = $n < 100 ? 1900 + $n : $n;
            if ($n % 10 === 0) {
                return $ok($n, $n + 9, $duda, 'decada');
            }
        }
        // Fecha completa: «03/02/2006», «02/02/1982 (sobre)», «3-2-2006»
        if (preg_match('~^\d{1,2}[/.\-]\d{1,2}[/.\-](\d{4})(?: \([^)]*\))?$~u', $t, $m)) {
            return $ok((int)$m[1], (int)$m[1], $duda, 'fecha');
        }
        // Fecha escrita: «20 al 25 de junio de 1988», «junio de 1988», «30 marzo 1991»
        if (preg_match('/^(?:(?:\d{1,2}(?: (?:al|y|a) \d{1,2})? )?(?:de )?)?(?:' . self::MESES . ')\.?(?: de| del)? (\d{4})$/u', $t, $m)) {
            return $ok((int)$m[1], (int)$m[1], $duda, 'fecha');
        }
        // «Entre 1983 y 1986», «Desde 1971 hasta 1991», «1960 a 2009», «1977 o 1978», «1988 y 1989»
        if (preg_match('/^(?:entre |desde |de )?(\d{4}) ?(a|al|y|o|hasta|-|–) ?(\d{4})$/u', $t, $m)) {
            $o = $m[2] === 'o';
            return $ok((int)$m[1], (int)$m[3], $duda || $o, 'rango');
        }
        // Listas y rangos con barra, guion, punto o coma: «1981/82», «2004/ 2005», «(1981.82,83)», «1969. 1970. 1971»
        $u = trim($t, '() ');
        if (preg_match('~^\d{4}(?: ?[/,.\-–] ?(?:\d{4}|\d{2}))+$~u', $u)) {
            preg_match_all('/\d+/', $u, $mm);
            $anios = [];
            $prev = null;
            foreach ($mm[0] as $x) {
                if (strlen($x) === 2) {
                    // «1981/82»: mismo siglo que el anterior; «1999/00» pasa al siguiente
                    $n = intdiv((int)$prev, 100) * 100 + (int)$x;
                    if ($n < $prev) {
                        $n += 100;
                    }
                } else {
                    $n = (int)$x;
                }
                $anios[] = $prev = $n;
            }
            return $ok(min($anios), max($anios), $duda, count($anios) > 2 ? 'lista' : 'rango');
        }
        return $nada('ilegible');
    }

    /** «1972», «1981–1982», con « (sin confirmar)» si corresponde. Para mostrar en el backoffice y el sitio. */
    public static function anioEtiqueta(?int $desde, ?int $hasta, bool $sinConfirmar): ?string
    {
        if ($desde === null) {
            return null;
        }
        $s = $hasta !== null && $hasta !== $desde ? "{$desde}–{$hasta}" : (string)$desde;
        return $sinConfirmar ? "$s (sin confirmar)" : $s;
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
