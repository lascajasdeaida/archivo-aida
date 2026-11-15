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

    /** «105 MB», «2,1 MB», «850 KB». */
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

    /** Nombre del registro: el título, o los tipos en cursiva con «(sin título)» para lectores de pantalla. */
    public static function nombre(array $d): string
    {
        return $d['sinTitulo']
            ? '<span class="sin-titulo">' . self::e($d['titulo']) . '</span><span class="solo-lector"> (sin título)</span>'
            : self::e($d['titulo']);
    }
}
