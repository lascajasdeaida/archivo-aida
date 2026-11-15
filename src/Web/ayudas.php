<?php
declare(strict_types=1);

// Funciones globales para las vistas del backoffice.

/** Escapa para HTML. */
function e(mixed $v): string
{
    if (is_array($v)) {
        $v = implode(', ', $v);
    }
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fecha(?string $f, bool $hora = true): string
{
    if (!$f) {
        return '';
    }
    $t = strtotime($f);
    return $t ? date($hora ? 'd/m/Y H:i' : 'd/m/Y', $t) : $f;
}

function acceso_txt(string $a): string
{
    return ['publico' => 'Público', 'restringido' => 'Restringido', 'a_revisar' => 'A revisar'][$a] ?? $a;
}
