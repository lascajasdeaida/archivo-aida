<?php
declare(strict_types=1);

namespace Aida;

use Aida\Archivos\Origen;

/**
 * Entrega de archivos digitales (PDF y otros) con control de acceso.
 *
 * Se pide siempre por id de `archivo_digital`, nunca por ruta. Acá están las reglas de acceso; cómo se entrega lo resuelve
 * el origen de archivos (src/Archivos: Disco lee la carpeta propia, Drive redirige al Drive del cliente).
 *
 * Reglas (ver docs/decisiones.md, “Acceso”):
 *  - El archivo no está quitado (eliminado_en) y apareció en el último escaneo (presente = 1).
 *  - Ni el registro ni la caja están dados de baja.
 *  - Público: el registro está publicado y su acceso es “publico”.
 *    Si es “restringido” o “a_revisar”, el documento se consulta a pedido.
 *  - Usuarios del backoffice (interno = true): ven también lo restringido, a revisar o no publicado.
 *
 * La usan public/archivo.php (sitio y backoffice). El sitio público puede reusar `evaluar()`
 * para decidir si muestra el botón “Ver documento” o la leyenda de consulta a pedido.
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

    public function __construct(private readonly \PDO $db, private readonly Origen $origen)
    {
    }

    public function origen(): Origen
    {
        return $this->origen;
    }

    /** Archivo con los datos de su registro y su caja, o null si no existe. */
    public function buscar(int $archivoId): ?array
    {
        if ($archivoId < 1) {
            return null;
        }
        $st = $this->db->prepare('SELECT a.id, a.registro_id, a.ruta, a.nombre_archivo, a.formato, a.presente,
                a.eliminado_en archivo_baja, a.modificado_en, a.peso_bytes, a.drive_id, a.drive_compartido,
                r.codigo, r.titulo, r.acceso, r.publicado, r.eliminado_en registro_baja,
                c.numero caja_numero, c.nombre caja_nombre, c.eliminado_en caja_baja, c.publicada caja_publicada
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
        if (!(int)$a['publicado'] || !(int)($a['caja_publicada'] ?? 0)) {
            return self::NO_ENCONTRADO;
        }
        return $a['acceso'] === 'publico' ? self::SERVIR : self::A_PEDIDO;
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
     * Responde un pedido que decidir() ya autorizó (SERVIR): el origen manda el archivo o redirige.
     * @return bool|null null = no está en el origen (404); true = contar el uso
     */
    public function entregar(array $a, bool $descargar, bool $interno): ?bool
    {
        return $this->origen->entregar($a, $descargar, $interno);
    }
}
