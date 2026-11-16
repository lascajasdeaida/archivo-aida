<?php
declare(strict_types=1);

namespace Aida;

use Aida\Archivos\Disco;
use Aida\Archivos\Origen;

/**
 * Escanea los archivos digitalizados y los asocia a los registros.
 *
 * Estructura real entregada por el equipo (nov 2026): un archivo por registro,
 * suelto en la carpeta de la caja:  C14/C14R3.pdf
 * También acepta partes numeradas (C14R3_001.pdf) y nombres con ceros (C10R02.pdf).
 *
 * Es incremental: se puede correr las veces que haga falta. Lo que aparece se da de alta,
 * lo que ya estaba se actualiza y lo que desapareció queda marcado como no presente.
 * El estado `registro.digitalizado` se recalcula a partir de los archivos presentes.
 *
 * De dónde salen los archivos lo resuelve el origen (src/Archivos): Disco recorre la carpeta, Drive lee el listado
 * de Drive. Para los dos se asocia igual, por la ruta (C14/C14R3.pdf). Lo propio del origen (el id de Drive) se guarda
 * con Origen::metadatos(); si cambia en un archivo que ya estaba, queda en el historial del escaneo.
 */
final class Escaner
{
    private const MEDIOS = [
        'pdf' => 'documento', 'doc' => 'documento', 'docx' => 'documento', 'txt' => 'documento',
        'jpg' => 'imagen', 'jpeg' => 'imagen', 'png' => 'imagen', 'tif' => 'imagen', 'tiff' => 'imagen', 'webp' => 'imagen',
        'mp3' => 'audio', 'wav' => 'audio', 'm4a' => 'audio', 'ogg' => 'audio',
        'mp4' => 'video', 'mov' => 'video', 'm4v' => 'video', 'webm' => 'video',
    ];

    private Reporte $rep;

    public function __construct(private readonly \PDO $db)
    {
    }

    /** Escanea el origen de archivos configurado (Origenes::desdeConfig). */
    public function escanear(Origen $origen, bool $simulacion = false): Reporte
    {
        $archivos = $origen->listar();
        return $this->procesar($archivos, $origen->descripcion(), $simulacion, $origen);
    }

    /** Escanea una carpeta del disco. */
    public function escanearCarpeta(string $raiz, bool $simulacion = false): Reporte
    {
        return $this->escanear(new Disco($raiz), $simulacion);
    }

    /**
     * Procesa un listado de archivos que no están en este disco (por ejemplo, la carpeta de Drive).
     * Formato TSV: ruta<TAB>bytes<TAB>fecha(opcional), una línea por archivo, ruta relativa (C14/C14R3.pdf).
     */
    public function procesarListado(string $tsv, bool $simulacion = false): Reporte
    {
        $archivos = [];
        foreach (file($tsv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linea) {
            if (str_starts_with($linea, '#')) {
                continue;
            }
            $p = explode("\t", $linea);
            $archivos[] = ['ruta' => trim($p[0]), 'bytes' => isset($p[1]) ? (int)$p[1] : null,
                           'mtime' => isset($p[2]) && $p[2] !== '' ? $p[2] : null];
        }
        return $this->procesar($archivos, basename($tsv), $simulacion, null);
    }

    /** @param list<array{ruta:string,bytes:?int,mtime:?string}> $archivos */
    private function procesar(array $archivos, string $fuente, bool $simulacion, ?Origen $origen): Reporte
    {
        $this->rep = Reporte::iniciar('escaneo', $fuente, $simulacion);
        $this->rep->alcanceTotal = true;
        $this->db->beginTransaction();
        try {
            // El proceso se abre al empezar para que los cambios del historial queden asociados a él
            $historial = $simulacion ? null : new Historial($this->db, null, 'escaneo', $this->rep->abrir($this->db));
            foreach ($origen?->avisos() ?? [] as $aviso) {
                $this->rep->incidencia('aviso', 'LISTADO_LINEA_INVALIDA', null, null, $aviso);
                $this->rep->sumar('líneas del listado con errores');
            }
            $vistos = [];
            $buscar = $this->db->prepare('SELECT r.id, r.excel_digitalizado, COALESCE(r.eliminado_en, c.eliminado_en) baja
                                          FROM registro r JOIN caja c ON c.id = r.caja_id
                                          WHERE c.numero = ? AND r.numero = ?');
            $existe = $this->db->prepare('SELECT id, registro_id, peso_bytes, presente, eliminado_en, drive_id, drive_compartido FROM archivo_digital WHERE ruta = ?');
            $insert = $this->db->prepare('INSERT INTO archivo_digital
                (registro_id, ruta, nombre_archivo, parte, formato, tipo_medio, peso_bytes, modificado_en, presente, visto_en)
                VALUES (?,?,?,?,?,?,?,?,1,NOW())');
            $update = $this->db->prepare('UPDATE archivo_digital SET registro_id = ?, parte = ?, formato = ?, tipo_medio = ?,
                peso_bytes = ?, modificado_en = ?, presente = 1, visto_en = NOW() WHERE id = ?');

            foreach ($archivos as $a) {
                $this->rep->sumar('archivos leídos');
                $nombre = basename($a['ruta']);
                $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
                if (!isset(self::MEDIOS[$ext])) {
                    $this->rep->incidencia('aviso', 'FORMATO_NO_RECONOCIDO', null, null, "{$a['ruta']}: extensión «{$ext}» no admitida");
                    continue;
                }
                $cod = Texto::parsearCodigo($nombre);
                if ($cod === null) {
                    $this->rep->incidencia('aviso', 'NOMBRE_NO_RECONOCIDO', null, null, "{$a['ruta']}: el nombre no tiene forma C#R#");
                    continue;
                }
                [$caja, $reg, $parte] = $cod;

                $carpeta = basename(dirname($a['ruta']));
                $carpCod = preg_match('/^C0*(\d+)$/i', $carpeta, $m) ? (int)$m[1] : null;
                if ($carpCod !== null && $carpCod !== $caja) {
                    $this->rep->incidencia('aviso', 'ARCHIVO_EN_OTRA_CAJA', $caja, $reg,
                        "{$a['ruta']} está en la carpeta $carpeta");
                }

                $buscar->execute([$caja, $reg]);
                $r = $buscar->fetch();
                if (!$r) {
                    $this->rep->incidencia('error', 'ARCHIVO_SIN_REGISTRO', $caja, $reg,
                        "{$a['ruta']}: no hay registro " . Texto::codigo($caja, $reg) . ' en la base (¿falta importar el Excel?)');
                    continue;
                }

                if ($r['baja'] !== null) {
                    $this->rep->incidencia('info', 'ARCHIVO_DE_UNA_BAJA', $caja, $reg,
                        "{$a['ruta']}: el registro o la caja fueron dados de baja desde el backoffice; no se asoció");
                    continue;
                }

                $existe->execute([$a['ruta']]);
                $prev = $existe->fetch();
                if ($prev && $prev['eliminado_en'] !== null) {
                    $this->rep->sumar('archivos quitados desde el backoffice (se respetan)');
                    continue;
                }
                $medio = self::MEDIOS[$ext];
                $meta = $origen?->metadatos($a) ?? [];
                if (!$prev) {
                    $insert->execute([$r['id'], $a['ruta'], $nombre, $parte, $ext, $medio, $a['bytes'], $a['mtime']]);
                    $nuevoId = (int)$this->db->lastInsertId();
                    $this->guardarMetadatos($nuevoId, [], $meta, null);
                    $vistos[] = $nuevoId;
                    $this->rep->sumar('archivos nuevos');
                } else {
                    $update->execute([$r['id'], $parte, $ext, $medio, $a['bytes'], $a['mtime'], $prev['id']]);
                    $this->guardarMetadatos((int)$prev['id'], $prev, $meta, $historial);
                    $vistos[] = (int)$prev['id'];
                    $this->rep->sumar(((int)$prev['presente'] === 0) ? 'archivos que volvieron' :
                        (((int)$prev['peso_bytes'] !== (int)$a['bytes']) ? 'archivos modificados' : 'archivos sin cambios'));
                }
            }

            // Lo que no se vio en este escaneo deja de estar presente
            $presentes = $this->db->query('SELECT a.id, a.ruta, c.numero caja, r.numero reg FROM archivo_digital a
                JOIN registro r ON r.id = a.registro_id JOIN caja c ON c.id = r.caja_id
                WHERE a.presente = 1 AND a.eliminado_en IS NULL AND r.eliminado_en IS NULL AND c.eliminado_en IS NULL')->fetchAll();
            $vistosSet = array_flip($vistos);
            $baja = $this->db->prepare('UPDATE archivo_digital SET presente = 0 WHERE id = ?');
            foreach ($presentes as $f) {
                if (isset($vistosSet[(int)$f['id']])) {
                    continue;
                }
                $baja->execute([$f['id']]);
                $this->rep->incidencia('aviso', 'ARCHIVO_DESAPARECIDO', (int)$f['caja'], (int)$f['reg'],
                    "{$f['ruta']} ya no está en la carpeta");
                $this->rep->sumar('archivos desaparecidos');
            }

            // Recalcular estado digitalizado
            $this->db->exec('UPDATE registro r SET digitalizado =
                EXISTS (SELECT 1 FROM archivo_digital a WHERE a.registro_id = r.id AND a.presente = 1 AND a.eliminado_en IS NULL)');

            // Control cruzado con lo que dice el Excel
            $cruce = $this->db->query("SELECT c.numero caja, r.numero reg, r.digitalizado, r.excel_digitalizado
                FROM registro r JOIN caja c ON c.id = r.caja_id
                WHERE r.excel_digitalizado IS NOT NULL AND r.excel_digitalizado <> r.digitalizado
                  AND r.eliminado_en IS NULL AND c.eliminado_en IS NULL
                ORDER BY c.numero, r.numero");
            foreach ($cruce->fetchAll() as $x) {
                if ((int)$x['excel_digitalizado'] === 1) {
                    $this->rep->incidencia('info', 'PENDIENTE_DE_SUBIR', (int)$x['caja'], (int)$x['reg'],
                        'El Excel lo marca digitalizado pero el archivo todavía no está');
                } else {
                    $this->rep->incidencia('aviso', 'EXCEL_DICE_NO_DIGITALIZADO', (int)$x['caja'], (int)$x['reg'],
                        'Hay archivo pero el Excel lo marca como no digitalizado');
                }
            }
            $origen?->controles($this->db, $this->rep);

            $tot = $this->db->query('SELECT COUNT(*) FROM registro WHERE digitalizado = 1 AND eliminado_en IS NULL')->fetchColumn();
            $this->rep->sumar('registros digitalizados (total)', (int)$tot);

            if ($simulacion) {
                $this->db->rollBack();
            } else {
                $this->db->commit();
                $this->rep->guardar($this->db);
            }
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
        return $this->rep;
    }

    /** Columnas propias del origen permitidas en archivo_digital (Origen::metadatos). */
    private const METADATOS = ['drive_id', 'drive_compartido'];

    /**
     * Guarda lo propio del origen (id de Drive, compartido). En un archivo que ya estaba, el cambio queda en el historial
     * del escaneo (se puede deshacer con el proceso).
     * @param array<string,mixed> $prev  fila anterior (vacía si es nuevo)
     * @param array<string,scalar|null> $meta
     */
    private function guardarMetadatos(int $id, array $prev, array $meta, ?Historial $historial): void
    {
        $meta = array_intersect_key($meta, array_flip(self::METADATOS));
        $cambia = [];
        foreach ($meta as $col => $v) {
            $antes = $prev[$col] ?? null;
            if ($prev === [] ? $v !== null : (string)($antes ?? "\0") !== (string)($v ?? "\0")) {
                $cambia[$col] = $v;
            }
        }
        if (!$cambia) {
            return;
        }
        $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($cambia)));
        $this->db->prepare("UPDATE archivo_digital SET $set WHERE id = ?")->execute([...array_values($cambia), $id]);
        if ($prev !== []) {
            $historial?->registrar('archivo_digital', $id, 'modificacion', array_intersect_key($prev, $cambia), $cambia);
            if (array_key_exists('drive_id', $cambia)) {
                $this->rep->sumar(($prev['drive_id'] ?? null) === null ? 'ids de Drive cargados' : 'ids de Drive cambiados');
            }
            if (array_key_exists('drive_compartido', $cambia)) {
                $this->rep->sumar(($prev['drive_compartido'] ?? null) === null ? 'datos de «compartido» cargados' : 'cambios de «compartido» en Drive');
            }
        } elseif (isset($cambia['drive_id'])) {
            $this->rep->sumar('ids de Drive cargados');
        }
    }
}
