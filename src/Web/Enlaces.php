<?php
declare(strict_types=1);

namespace Aida\Web;

use Aida\Sitio;

/**
 * Direcciones que usan las plantillas del sitio público (vistas/publico/). Las plantillas nunca arman una URL
 * a mano: así la misma plantilla sirve para el sitio y para la vista previa del backoffice.
 *
 *  - Sitio:        URLs legibles de docs/ux/sitio-publico.md §2 (/registro/C14R3, /caja/14-zsherezade…).
 *  - Vista previa: la ficha y la caja navegan dentro de la vista previa (?r=vista_previa&registro=ID);
 *                  las páginas que todavía no existen (inicio, explorar, consulta…) quedan con su URL pública
 *                  y vista-previa.js avisa que no están en la vista previa.
 *
 * $base es la ruta de public/ vista desde la página: «/» en el sitio, «../» desde /admin/.
 */
final class Enlaces
{
    public function __construct(public readonly string $base = '/', public readonly bool $vistaPrevia = false)
    {
    }

    public static function vistaPrevia(): self
    {
        return new self('../', true);
    }

    /** Archivo estático de public/ (CSS, JS). */
    public function recurso(string $ruta): string
    {
        $f = dirname(__DIR__, 2) . '/public/' . $ruta;
        $v = is_file($f) ? '?v=' . filemtime($f) : '';
        return $this->base . $ruta . $v;
    }

    /** @param array{id:int|string, codigo:string} $r */
    public function registro(array $r): string
    {
        return $this->vistaPrevia
            ? App::url('vista_previa', ['registro' => (int)$r['id']])
            : $this->base . 'registro/' . rawurlencode((string)$r['codigo']);
    }

    /** @param array{id:int|string, numero:int|string, nombre:?string} $c */
    public function caja(array $c): string
    {
        if ($this->vistaPrevia) {
            return App::url('vista_previa', ['caja' => (int)$c['id']]);
        }
        $slug = Sitio::slug($c['nombre'] ?? '');
        return $this->base . 'caja/' . (int)$c['numero'] . ($slug !== '' ? '-' . $slug : '');
    }

    /** Visor o descarga: siempre por id de archivo (public/archivo.php decide si se entrega). */
    public function archivo(int $id, bool $descargar = false): string
    {
        return $this->base . 'archivo.php?' . http_build_query(['id' => $id] + ($descargar ? ['descargar' => 1] : []));
    }

    public function inicio(): string
    {
        return $this->base;
    }

    public function cajas(): string
    {
        return $this->base . 'cajas';
    }

    /** @param array<string, scalar> $filtros */
    public function explorar(array $filtros = []): string
    {
        return $this->base . 'explorar' . ($filtros ? '?' . http_build_query($filtros) : '');
    }

    public function consulta(string $codigo, ?string $motivo = null): string
    {
        return $this->base . 'consulta/' . rawurlencode($codigo) . ($motivo ? '?motivo=' . rawurlencode($motivo) : '');
    }

    public function sobre(string $ancla = ''): string
    {
        return $this->base . 'sobre-el-archivo' . ($ancla !== '' ? '#' . $ancla : '');
    }

    /** Dirección pública canónica (para la cita), sea cual sea el modo. */
    public static function canonica(string $codigo): string
    {
        return 'https://lascajasdeaida.com.ar/registro/' . rawurlencode($codigo);
    }
}
