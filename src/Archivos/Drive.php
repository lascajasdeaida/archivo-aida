<?php
declare(strict_types=1);

namespace Aida\Archivos;

use Aida\Reporte;

/**
 * Archivos en el Drive compartido del cliente (origen provisorio, decisión del PM del 16/11/2026).
 *
 * - Qué hay en Drive y con qué id sale del **listado** (ListadoDrive): se carga con `bin/drive-listado.php` o desde
 *   Almacenamiento en el backoffice, y queda una copia normalizada en `archivos.drive.listado`. El escaneo la recorre
 *   como si fuera la carpeta del disco (asocia por ruta C1/C1R31.pdf) y guarda `drive_id` y `drive_compartido`.
 * - Entregar = redirigir, después de los mismos controles de acceso de siempre (Entrega::decidir): ver → visor de Drive
 *   (/file/d/ID/preview, en el iframe de la ficha); descargar → descarga de Drive. Lo que pesa más que `visor_max_mb`
 *   (100 MB por defecto) solo se descarga: el visor de Drive no abre PDF tan grandes.
 * - Los permisos de cada archivo los maneja el cliente en Drive: el escaneo avisa si un restringido figura compartido o
 *   si un público no lo está (controles()).
 *
 * Preparado para la API: con credenciales de cuenta de servicio, listar() podría pedir los archivos a la API de Drive
 * (files.list con id, name, size y permissions) en vez de leer el listado. No está implementado (16/11/2026).
 */
final class Drive implements Origen
{
    /** @var list<string> */
    private array $avisos = [];

    public function __construct(
        private readonly string $listado,
        private readonly int $visorMaxBytes = 100_000_000,
        private readonly ?string $leerDe = null,
    ) {
    }

    /** El mismo origen pero leyendo otro listado (el que se acaba de subir, para simularlo antes de guardarlo). */
    public function conListado(string $archivo): self
    {
        return new self($this->listado, $this->visorMaxBytes, $archivo);
    }

    public function nombre(): string
    {
        return Origenes::DRIVE;
    }

    public function descripcion(): string
    {
        return 'Drive: ' . basename($this->leerDe ?? $this->listado);
    }

    public function rutaListado(): string
    {
        return $this->listado;
    }

    public function visorMaxBytes(): int
    {
        return $this->visorMaxBytes;
    }

    /** Fecha del último listado guardado, o null si todavía no se cargó ninguno. */
    public function listadoDel(): ?int
    {
        return is_file($this->listado) ? (int)filemtime($this->listado) : null;
    }

    public function listar(): array
    {
        $archivo = $this->leerDe ?? $this->listado;
        if (!is_file($archivo)) {
            throw new \RuntimeException('Todavía no se cargó el listado de Drive (Almacenamiento → Listado de Drive, o php bin/drive-listado.php).');
        }
        $l = ListadoDrive::leer($archivo);
        $this->avisos = $l['errores'];
        return $l['filas'];
    }

    /** Guarda como listado vigente el que se acaba de escanear. */
    public function guardarListado(string $archivo, string $nombreOriginal = ''): void
    {
        ListadoDrive::guardar(ListadoDrive::leer($archivo)['filas'], $this->listado, $nombreOriginal);
    }

    public function avisos(): array
    {
        return $this->avisos;
    }

    public function metadatos(array $fila): array
    {
        // Una línea sin id no borra el que ya estaba (nada se borra en un escaneo); «compartido» sí refleja el listado
        // nuevo: sin dato es sin dato, para no dejar alertas viejas.
        $c = $fila['compartido'] ?? null;
        $meta = ['drive_compartido' => $c === null ? null : (int)(bool)$c];
        if (($fila['drive_id'] ?? null) !== null) {
            $meta['drive_id'] = $fila['drive_id'];
        }
        return $meta;
    }

    public function controles(\PDO $db, Reporte $rep): void
    {
        $filas = $db->query("SELECT a.ruta, a.drive_id, a.drive_compartido, c.numero caja, r.numero reg, r.acceso, r.publicado
            FROM archivo_digital a JOIN registro r ON r.id = a.registro_id JOIN caja c ON c.id = r.caja_id
            WHERE a.presente = 1 AND a.eliminado_en IS NULL AND r.eliminado_en IS NULL AND c.eliminado_en IS NULL
            ORDER BY c.numero, r.numero, a.ruta")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($filas as $f) {
            $caja = (int)$f['caja'];
            $reg = (int)$f['reg'];
            if (!ListadoDrive::idValido($f['drive_id'])) {
                $rep->incidencia('aviso', 'DRIVE_SIN_ID', $caja, $reg, "{$f['ruta']}: el listado de Drive no trae su id; no se puede mostrar ni descargar");
                $rep->sumar('archivos sin id de Drive');
                continue;
            }
            if ($f['drive_compartido'] === null) {
                $rep->sumar('archivos sin dato de «compartido»');
                continue;
            }
            $compartido = (int)$f['drive_compartido'] === 1;
            if ($compartido && $f['acceso'] !== 'publico') {
                $rep->incidencia('error', 'DRIVE_RESTRINGIDO_COMPARTIDO', $caja, $reg,
                    "{$f['ruta']}: el registro es " . ($f['acceso'] === 'restringido' ? 'restringido' : 'de acceso a revisar')
                    . ' y en Drive está compartido con «cualquiera con el enlace»');
            } elseif (!$compartido && $f['acceso'] === 'publico' && (int)$f['publicado'] === 1) {
                $rep->incidencia('aviso', 'DRIVE_PUBLICO_SIN_COMPARTIR', $caja, $reg,
                    "{$f['ruta']}: el registro es público y en Drive no está compartido con «cualquiera con el enlace»");
            }
        }
    }

    public function entregable(array $a): bool
    {
        return ListadoDrive::idValido($a['drive_id'] ?? null);
    }

    public function admiteVisor(array $a): bool
    {
        $peso = $a['peso_bytes'] ?? $a['peso'] ?? null;
        return $peso === null || (int)$peso <= $this->visorMaxBytes;
    }

    public static function urlVisor(string $id): string
    {
        return 'https://drive.google.com/file/d/' . rawurlencode($id) . '/preview';
    }

    public static function urlDescarga(string $id): string
    {
        return 'https://drive.google.com/uc?export=download&id=' . rawurlencode($id);
    }

    public function entregar(array $a, bool $descargar, bool $interno): ?bool
    {
        if (!$this->entregable($a)) {
            error_log("[aida-entrega] archivo {$a['id']} ({$a['ruta']}) sin id de Drive");
            return null;
        }
        $id = (string)$a['drive_id'];
        $url = !$descargar && $this->admiteVisor($a) ? self::urlVisor($id) : self::urlDescarga($id);
        header_remove('X-Powered-By');
        // Sin caché: si el registro pasa a restringido, el próximo pedido ya no redirige.
        header('Cache-Control: private, no-store');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex');
        header('Location: ' . $url, true, 302);
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD';
    }
}
