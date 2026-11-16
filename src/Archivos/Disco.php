<?php
declare(strict_types=1);

namespace Aida\Archivos;

use Aida\Entrega;
use Aida\Reporte;

/**
 * Archivos en una carpeta propia del servidor (C1/, C2/, … con C14R3.pdf). Es el origen por defecto.
 *
 * La ruta sale de la base y se resuelve con realpath dentro de la raíz, así que no hay forma de salirse de esa carpeta.
 * Se entrega desde PHP con Range (el visor del navegador pide los PDF pesados de a partes), ETag y validación de caché.
 */
final class Disco implements Origen
{
    private const BLOQUE = 1048576; // 1 MB por lectura

    public function __construct(private readonly string $raiz)
    {
    }

    public function nombre(): string
    {
        return Origenes::DISCO;
    }

    public function descripcion(): string
    {
        return rtrim($this->raiz, '/');
    }

    public function listar(): array
    {
        $raiz = rtrim($this->raiz, '/');
        if ($raiz === '' || !is_dir($raiz)) {
            throw new \RuntimeException("No existe la carpeta $raiz");
        }
        $archivos = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            /** @var \SplFileInfo $f */
            if (!$f->isFile() || str_starts_with($f->getFilename(), '.')) {
                continue;
            }
            $archivos[] = [
                'ruta'  => ltrim(substr($f->getPathname(), strlen($raiz)), '/'),
                'bytes' => $f->getSize(),
                'mtime' => date('Y-m-d H:i:s', $f->getMTime()),
            ];
        }
        return $archivos;
    }

    public function avisos(): array
    {
        return [];
    }

    public function metadatos(array $fila): array
    {
        return [];
    }

    public function controles(\PDO $db, Reporte $rep): void
    {
    }

    public function entregable(array $a): bool
    {
        return true; // que esté en el disco se verifica al entregar (rutaSegura)
    }

    public function admiteVisor(array $a): bool
    {
        return true;
    }

    public function entregar(array $a, bool $descargar, bool $interno): ?bool
    {
        $ruta = $this->rutaSegura($a);
        if ($ruta === null) {
            error_log("[aida-entrega] archivo {$a['id']} ({$a['ruta']}) en la base pero no en el disco");
            return null;
        }
        return $this->enviar($a, $ruta, $descargar, $interno);
    }

    /** Ruta absoluta verificada dentro de la raíz, o null si no está o se sale de la carpeta. */
    public function rutaSegura(array $a): ?string
    {
        $raiz = realpath($this->raiz);
        $rel = (string)$a['ruta'];
        if ($raiz === false || $rel === '' || str_contains($rel, "\0")) {
            return null;
        }
        $real = realpath($raiz . '/' . ltrim($rel, '/'));
        if ($real === false || !str_starts_with($real, $raiz . DIRECTORY_SEPARATOR) || !is_file($real) || !is_readable($real)) {
            return null;
        }
        return $real;
    }

    /**
     * Interpreta un encabezado Range de un solo tramo.
     * @return array{0:int,1:int}|false|null  [desde, hasta] · null = entregar completo · false = no satisfacible (416)
     */
    public static function rango(?string $encabezado, int $tamanio): array|false|null
    {
        if ($encabezado === null || $encabezado === '') {
            return null;
        }
        if (!preg_match('/^\s*bytes\s*=\s*(\d*)\s*-\s*(\d*)\s*$/i', $encabezado, $m)) {
            return null; // varios tramos o formato raro: se entrega completo (lo permite RFC 9110)
        }
        if ($m[1] === '' && $m[2] === '') {
            return null;
        }
        if ($tamanio === 0) {
            return false;
        }
        if ($m[1] === '') { // los últimos N bytes
            $n = (int)$m[2];
            if ($n === 0) {
                return false;
            }
            return [max(0, $tamanio - $n), $tamanio - 1];
        }
        $desde = (int)$m[1];
        $hasta = $m[2] === '' ? $tamanio - 1 : min((int)$m[2], $tamanio - 1);
        if ($desde >= $tamanio || $hasta < $desde) {
            return false;
        }
        return [$desde, $hasta];
    }

    /**
     * Envía el archivo con soporte de Range, If-None-Match / If-Modified-Since e If-Range.
     * @param bool $descargar true = Content-Disposition attachment; false = inline (visor del navegador)
     * @param bool $interno   true = pedido del backoffice (no se guarda en caché compartida ni privada)
     * @return bool true si se entregó el archivo desde el principio (para contar la vista una sola vez)
     */
    private function enviar(array $a, string $ruta, bool $descargar, bool $interno): bool
    {
        $tamanio = (int)filesize($ruta);
        $mtime = (int)filemtime($ruta);
        $etag = '"' . dechex($mtime) . '-' . dechex($tamanio) . '-' . (int)$a['id'] . '"';
        $nombre = basename((string)$a['nombre_archivo']) ?: 'documento';
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombre) ?: 'documento';

        header_remove('X-Powered-By');
        header('Content-Type: ' . Entrega::mime((string)$a['formato']));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . ($descargar ? 'attachment' : 'inline') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
        header('Accept-Ranges: bytes');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('Cache-Control: ' . ($interno ? 'private, no-store' : 'private, no-cache'));
        header('Referrer-Policy: same-origin');
        header('X-Frame-Options: SAMEORIGIN');
        header("Content-Security-Policy: frame-ancestors 'self'");
        header('X-Robots-Tag: noindex');

        // Validación de caché
        $inm = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
        $ims = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;
        if (($inm !== null && trim($inm) === $etag) || ($inm === null && $ims !== null && strtotime($ims) >= $mtime)) {
            http_response_code(304);
            return false;
        }

        $rango = self::rango($_SERVER['HTTP_RANGE'] ?? null, $tamanio);
        // If-Range: si el archivo cambió, se ignora el rango y va completo
        $ifRange = $_SERVER['HTTP_IF_RANGE'] ?? null;
        if ($rango !== null && $ifRange !== null && trim($ifRange) !== $etag && strtotime($ifRange) !== $mtime) {
            $rango = null;
        }
        if ($rango === false) {
            http_response_code(416);
            header('Content-Range: bytes */' . $tamanio);
            header('Content-Length: 0');
            return false;
        }
        [$desde, $hasta] = $rango ?? [0, max(0, $tamanio - 1)];
        $largo = $tamanio === 0 ? 0 : $hasta - $desde + 1;
        if ($rango !== null) {
            http_response_code(206);
            header("Content-Range: bytes $desde-$hasta/$tamanio");
        }
        header('Content-Length: ' . $largo);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD' || $largo === 0) {
            return false;
        }

        @set_time_limit(0);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $fh = fopen($ruta, 'rb');
        if ($fh === false) {
            return false;
        }
        fseek($fh, $desde);
        $falta = $largo;
        while ($falta > 0 && !feof($fh) && !connection_aborted()) {
            $buf = fread($fh, min(self::BLOQUE, $falta));
            if ($buf === false || $buf === '') {
                break;
            }
            echo $buf;
            flush();
            $falta -= strlen($buf);
        }
        fclose($fh);
        return $rango === null || $desde === 0;
    }
}
