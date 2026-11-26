<?php
declare(strict_types=1);

namespace Aida\Web;

use Aida\Sitio;

/**
 * Direcciones que usan las plantillas del sitio público (vistas/publico/). Las plantillas nunca arman una URL
 * a mano: así la misma plantilla sirve para el sitio y para la vista previa del backoffice.
 *
 * Tres modos (docs/sitio-publico.md, docs/despliegue.md):
 *  - Sitio con reescritura (producción): URLs legibles de docs/ux/sitio-publico.md §2 (/registro/C14R3, /caja/14-zsherezade…).
 *  - Sitio sin reescritura (la Mac del PM, con el WordPress de Local en public/): public/sitio.php?ruta=registro/C14R3.
 *    Mismas páginas; solo cambia la forma del enlace.
 *  - Vista previa (backoffice): la ficha y la caja navegan dentro de la vista previa (?r=vista_previa&registro=ID);
 *    las demás páginas quedan con su URL pública y vista-previa.js avisa que no están en la vista previa.
 *
 * $base es la ruta de public/ vista desde la página: “/” en el sitio (o “/subcarpeta/”), “../” desde /admin/.
 */
final class Enlaces
{
    /** Archivo del front controller del sitio, dentro de public/. */
    public const FRONT = 'sitio.php';

    /** Dominio de producción: canonical, Open Graph, sitemap y citas. Se cambia con config 'sitio' => ['url' => …]. */
    public const DOMINIO = 'https://lascajasdeaida.com.ar';

    public function __construct(
        public readonly string $base = '/',
        public readonly bool $vistaPrevia = false,
        public readonly bool $reescritura = true,
    ) {
    }

    public static function vistaPrevia(): self
    {
        return new self('../', true, self::reescrituraConfigurada() ?? true);
    }

    /**
     * Enlaces para la página pedida. Si la dirección pedida pasa por sitio.php (/sitio.php, /sitio.php?ruta=…), no hay
     * reescritura y los enlaces siguen esa forma; si no, son legibles. config 'sitio' => ['reescritura' => bool] manda.
     */
    public static function desdePedido(): self
    {
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/' . self::FRONT);
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        $base = $dir . '/';
        $camino = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $directo = str_ends_with($script, '/' . self::FRONT) && ($camino === $script || str_starts_with($camino, $script . '/'));
        return new self($base, false, self::reescrituraConfigurada() ?? !$directo);
    }

    private static function reescrituraConfigurada(): ?bool
    {
        $cfg = function_exists('aida_config') ? (aida_config()['sitio'] ?? []) : [];
        return isset($cfg['reescritura']) ? (bool)$cfg['reescritura'] : null;
    }

    /** Dominio para las direcciones absolutas (canonical, og:url, sitemap). */
    public static function dominio(): string
    {
        $cfg = function_exists('aida_config') ? (aida_config()['sitio'] ?? []) : [];
        return rtrim((string)($cfg['url'] ?? self::DOMINIO), '/');
    }

    /**
     * Dirección de una ruta del sitio. $ruta sin barra inicial: '' (inicio), 'explorar', 'registro/C2R5'.
     * @param array<string, scalar|list<scalar>> $params
     */
    public function ruta(string $ruta, array $params = []): string
    {
        $q = self::cadena($params);
        if ($this->reescritura) {
            return $this->base . $ruta . ($q !== '' ? '?' . $q : '');
        }
        $todo = ($ruta !== '' ? 'ruta=' . self::codificarRuta($ruta) : '') . ($q !== '' ? ($ruta !== '' ? '&' : '') . $q : '');
        return $this->base . self::FRONT . ($todo !== '' ? '?' . $todo : '');
    }

    /** Dirección absoluta y legible de una ruta (canonical): siempre la de producción, sea cual sea el modo. */
    public static function absoluta(string $ruta, array $params = []): string
    {
        $q = self::cadena($params);
        return self::dominio() . '/' . $ruta . ($q !== '' ? '?' . $q : '');
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
            : $this->ruta(self::rutaRegistro((string)$r['codigo']));
    }

    /** @param array{id:int|string, numero:int|string, nombre:?string} $c */
    public function caja(array $c): string
    {
        if ($this->vistaPrevia) {
            return App::url('vista_previa', ['caja' => (int)$c['id']]);
        }
        return $this->ruta(self::rutaCaja($c));
    }

    public static function rutaRegistro(string $codigo): string
    {
        return 'registro/' . rawurlencode($codigo);
    }

    /** @param array{numero:int|string, nombre:?string} $c */
    public static function rutaCaja(array $c): string
    {
        $slug = Sitio::slug($c['nombre'] ?? '');
        return 'caja/' . (int)$c['numero'] . ($slug !== '' ? '-' . $slug : '');
    }

    /** Visor o descarga: siempre por id de archivo (public/archivo.php decide si se entrega). */
    public function archivo(int $id, bool $descargar = false, bool $original = false): string
    {
        return $this->base . 'archivo.php?' . http_build_query(['id' => $id] + ($descargar ? ['descargar' => 1] : []) + ($original ? ['original' => 1] : []));
    }

    public function inicio(): string
    {
        return $this->ruta('');
    }

    public function cajas(array $params = []): string
    {
        return $this->ruta('cajas', $params);
    }

    /** @param array<string, scalar|list<scalar>> $filtros listas → “tipo=guion,carta” */
    public function explorar(array $filtros = []): string
    {
        return $this->ruta('explorar', $filtros);
    }

    /** Endpoint JSON de Explorar (resultados y facetas para “Mostrar más” y los filtros sin recargar). */
    public function explorarJson(array $filtros = []): string
    {
        return $this->ruta('explorar.json', $filtros);
    }

    public function consulta(string $codigo, ?string $motivo = null): string
    {
        return $this->ruta('consulta/' . rawurlencode($codigo), $motivo ? ['motivo' => $motivo] : []);
    }

    public function sobre(string $ancla = ''): string
    {
        return $this->ruta('sobre') . ($ancla !== '' ? '#' . $ancla : '');
    }

    /** Dirección pública canónica de un registro (para la cita), sea cual sea el modo. */
    public static function canonica(string $codigo): string
    {
        return self::absoluta(self::rutaRegistro($codigo));
    }

    /** “tipo” => ['guion', 'carta'] → “tipo=guion,carta”; vacíos afuera. */
    private static function cadena(array $params): string
    {
        $partes = [];
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $v = implode(',', array_map('strval', $v));
            }
            if ($v === null || $v === '' || $v === false) {
                continue;
            }
            $partes[] = rawurlencode((string)$k) . '=' . str_replace('%2C', ',', rawurlencode((string)$v));
        }
        return implode('&', $partes);
    }

    /** “registro/C2R5” → “registro/C2R5” (las barras quedan legibles en ?ruta=). */
    private static function codificarRuta(string $ruta): string
    {
        return str_replace('%2F', '/', rawurlencode(rawurldecode($ruta)));
    }
}
