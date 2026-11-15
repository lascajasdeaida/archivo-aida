<?php
declare(strict_types=1);

namespace Aida;

/**
 * Entrega de archivos digitales (PDF y otros) con control de acceso.
 *
 * Se pide siempre por id de `archivo_digital`, nunca por ruta. La ruta sale de la base y se
 * resuelve con realpath dentro de `archivos_raiz`, así que no hay forma de salirse de esa carpeta.
 *
 * Reglas (ver docs/decisiones.md, «Acceso»):
 *  - El archivo no está quitado (eliminado_en) y apareció en el último escaneo (presente = 1).
 *  - Ni el registro ni la caja están dados de baja.
 *  - Público: el registro está publicado y su acceso es «publico».
 *    Si es «restringido» o «a_revisar», el documento se consulta a pedido.
 *  - Usuarios del backoffice (interno = true): ven también lo restringido, a revisar o no publicado.
 *
 * La usan public/archivo.php (sitio y backoffice). El sitio público puede reusar `evaluar()`
 * para decidir si muestra el botón «Ver documento» o la leyenda de consulta a pedido.
 */
final class Entrega
{
    public const SERVIR = 'servir';
    public const NO_ENCONTRADO = 'no_encontrado';
    public const A_PEDIDO = 'a_pedido';

    /** Formatos que se entregan y cómo. Lo que no está acá no se sirve. */
    private const MIME = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'tif' => 'image/tiff', 'tiff' => 'image/tiff',
        'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav',
        'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
    ];

    private const BLOQUE = 1048576; // 1 MB por lectura

    public function __construct(private readonly \PDO $db, private readonly string $raiz)
    {
    }

    /** Archivo con los datos de su registro y su caja, o null si no existe. */
    public function buscar(int $archivoId): ?array
    {
        if ($archivoId < 1) {
            return null;
        }
        $st = $this->db->prepare('SELECT a.id, a.registro_id, a.ruta, a.nombre_archivo, a.formato, a.presente,
                a.eliminado_en archivo_baja, a.modificado_en,
                r.codigo, r.titulo, r.acceso, r.publicado, r.eliminado_en registro_baja,
                c.numero caja_numero, c.nombre caja_nombre, c.eliminado_en caja_baja
            FROM archivo_digital a JOIN registro r ON r.id = a.registro_id JOIN caja c ON c.id = r.caja_id
            WHERE a.id = ?');
        $st->execute([$archivoId]);
        return $st->fetch() ?: null;
    }

    /**
     * Decide qué hacer con un pedido.
     * @return self::SERVIR|self::NO_ENCONTRADO|self::A_PEDIDO
     */
    public static function decidir(?array $a, bool $interno): string
    {
        if ($a === null || $a['archivo_baja'] !== null || !(int)$a['presente']
            || $a['registro_baja'] !== null || $a['caja_baja'] !== null) {
            return self::NO_ENCONTRADO;
        }
        if (!isset(self::MIME[strtolower((string)$a['formato'])])) {
            return self::NO_ENCONTRADO;
        }
        if ($interno) {
            return self::SERVIR;
        }
        if (!(int)$a['publicado']) {
            return self::NO_ENCONTRADO;
        }
        return $a['acceso'] === 'publico' ? self::SERVIR : self::A_PEDIDO;
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

    public static function mime(string $formato): string
    {
        return self::MIME[strtolower($formato)] ?? 'application/octet-stream';
    }

    /** Registra una vista o descarga. Sin datos personales: solo registro, archivo, tipo y fecha. */
    public function registrarUso(array $a, string $tipo): void
    {
        try {
            $this->db->prepare('INSERT INTO evento_uso (registro_id, archivo_id, tipo) VALUES (?,?,?)')
                     ->execute([(int)$a['registro_id'], (int)$a['id'], $tipo === 'descarga' ? 'descarga' : 'vista']);
        } catch (\PDOException $e) {
            error_log('[aida-entrega] no se pudo registrar el uso: ' . $e->getMessage());
        }
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
    public function enviar(array $a, string $ruta, bool $descargar, bool $interno): bool
    {
        $tamanio = (int)filesize($ruta);
        $mtime = (int)filemtime($ruta);
        $etag = '"' . dechex($mtime) . '-' . dechex($tamanio) . '-' . (int)$a['id'] . '"';
        $nombre = basename((string)$a['nombre_archivo']) ?: 'documento';
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombre) ?: 'documento';

        header_remove('X-Powered-By');
        header('Content-Type: ' . self::mime((string)$a['formato']));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . ($descargar ? 'attachment' : 'inline') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
        header('Accept-Ranges: bytes');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('Cache-Control: ' . ($interno ? 'private, no-cache' : 'private, max-age=3600'));
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
