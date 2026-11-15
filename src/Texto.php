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
