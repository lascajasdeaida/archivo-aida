<?php
declare(strict_types=1);

namespace Aida;

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

    /**
     * Escanea una carpeta del disco.
     */
    public function escanearCarpeta(string $raiz, bool $simulacion = false): Reporte
    {
        $raiz = rtrim($raiz, '/');
        if (!is_dir($raiz)) {
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
        return $this->procesar($archivos, $raiz, $simulacion);
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
        return $this->procesar($archivos, basename($tsv), $simulacion);
    }

    /** @param list<array{ruta:string,bytes:?int,mtime:?string}> $archivos */
    private function procesar(array $archivos, string $fuente, bool $simulacion): Reporte
    {
        $this->rep = Reporte::iniciar('escaneo', $fuente, $simulacion);
        $this->rep->alcanceTotal = true;
        $this->db->beginTransaction();
        try {
            $vistos = [];
            $buscar = $this->db->prepare('SELECT r.id, r.excel_digitalizado, COALESCE(r.eliminado_en, c.eliminado_en) baja
                                          FROM registro r JOIN caja c ON c.id = r.caja_id
                                          WHERE c.numero = ? AND r.numero = ?');
            $existe = $this->db->prepare('SELECT id, registro_id, peso_bytes, presente, eliminado_en FROM archivo_digital WHERE ruta = ?');
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
                if (!$prev) {
                    $insert->execute([$r['id'], $a['ruta'], $nombre, $parte, $ext, $medio, $a['bytes'], $a['mtime']]);
                    $vistos[] = (int)$this->db->lastInsertId();
                    $this->rep->sumar('archivos nuevos');
                } else {
                    $update->execute([$r['id'], $parte, $ext, $medio, $a['bytes'], $a['mtime'], $prev['id']]);
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
}
