<?php
declare(strict_types=1);

namespace Aida\Web;

/** Ayudas de marcado para las plantillas del sitio público (vistas/publico/). */
final class Html
{
    /** Escapa texto para HTML. */
    public static function e(mixed $v): string
    {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Ícono del sprite (vistas/publico/_iconos.php). Siempre decorativo: el texto va al lado. */
    public static function icono(string $nombre): string
    {
        return '<svg class="icono" aria-hidden="true" focusable="false"><use href="#i-' . self::e($nombre) . '"/></svg>';
    }

    /** “105 MB”, “2,1 MB”, “850 KB”. */
    public static function peso(?int $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }
        if ($bytes < 1000 * 1000) {
            return number_format(max(1, $bytes / 1000), 0, ',', '.') . ' KB';
        }
        $mb = $bytes / 1000 / 1000;
        return number_format($mb, $mb < 10 ? 1 : 0, ',', '.') . ' MB';
    }

    public static function numero(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }

    /**
     * Año para mostrar (de Sitio::anio): dudoso con subrayado punteado e ícono (docs/diseno/componentes.md §4).
     * @param array{etiqueta:?string, dudoso:bool} $a
     */
    public static function anio(array $a, string $sinFecha = 'Sin fecha'): string
    {
        if ($a['etiqueta'] === null) {
            return self::e($sinFecha);
        }
        return $a['dudoso']
            ? '<span class="anio-dudoso">' . self::e($a['etiqueta']) . self::icono('duda') . '</span>'
            : self::e($a['etiqueta']);
    }

    /**
     * Nombre del registro: el título, o los tipos en cursiva con “(sin título)” para lectores de pantalla.
     * Con $q, resalta los términos buscados (<mark>).
     */
    public static function nombre(array $d, string $q = ''): string
    {
        return $d['sinTitulo']
            ? '<span class="sin-titulo">' . self::resaltar($d['titulo'], $q) . '</span><span class="solo-lector"> (sin título)</span>'
            : self::resaltar($d['titulo'], $q);
    }

    /** Variantes de cada letra para buscar sin tildes, como la base (utf8mb4_unicode_ci: “aida” = “Aída”, “n” = “ñ”). */
    private const VARIANTES = ['a' => 'aáàâäã', 'e' => 'eéèêë', 'i' => 'iíìîï', 'o' => 'oóòôöõ', 'u' => 'uúùûü', 'n' => 'nñ', 'c' => 'cç'];

    /** Expresión regular con los términos de la búsqueda (2 letras o más), sin tildes ni mayúsculas. Null si no hay. */
    public static function patron(string $q): ?string
    {
        $q = strtr(mb_strtolower($q), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ç' => 'c']);
        $terminos = array_unique(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [],
            fn($t) => mb_strlen($t) >= 2
        ));
        if (!$terminos) {
            return null;
        }
        usort($terminos, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $alternativas = array_map(function (string $t): string {
            $re = '';
            foreach (mb_str_split($t) as $ch) {
                $re .= isset(self::VARIANTES[$ch]) ? '[' . self::VARIANTES[$ch] . ']' : preg_quote($ch, '/');
            }
            return $re;
        }, $terminos);
        return '/(' . implode('|', $alternativas) . ')/iu';
    }

    /** Texto escapado con los términos de $q envueltos en <mark>. */
    public static function resaltar(?string $texto, string $q): string
    {
        $texto = (string)$texto;
        $re = $q === '' ? null : self::patron($q);
        if ($re === null) {
            return self::e($texto);
        }
        $partes = preg_split($re, $texto, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($partes === false) {
            return self::e($texto);
        }
        $out = '';
        foreach ($partes as $i => $p) {
            $out .= $i % 2 ? '<mark>' . self::e($p) . '</mark>' : self::e($p);
        }
        return $out;
    }

    /**
     * Fragmento de $texto alrededor de la primera coincidencia con $q (para mostrar por qué apareció un resultado).
     * Null si $q no aparece en el texto.
     */
    public static function fragmento(?string $texto, string $q, int $largo = 170): ?string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', (string)$texto));
        $re = $q === '' ? null : self::patron($q);
        if ($re === null || $texto === '' || !preg_match($re, $texto, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $pos = mb_strlen(substr($texto, 0, $m[0][1]));
        $desde = max(0, $pos - 50);
        if ($desde > 0 && ($esp = mb_strpos($texto, ' ', $desde)) !== false && $esp < $pos) {
            $desde = $esp + 1;
        }
        $trozo = mb_substr($texto, $desde, $largo);
        $corta = $desde + $largo < mb_strlen($texto);
        if ($corta && ($esp = mb_strrpos($trozo, ' ')) !== false && $esp > $pos - $desde) {
            $trozo = mb_substr($trozo, 0, $esp);
        }
        return ($desde > 0 ? '…' : '') . $trozo . ($corta ? '…' : '');
    }
}
