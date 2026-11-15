<?php
declare(strict_types=1);

namespace Aida;

/**
 * Importa el Excel del catálogo («Catálogo Aída Parte 1 BACKUP CONSERVACIÓN»).
 *
 * Reglas (ver docs/decisiones.md):
 *  - Una pestaña «Caja N …» por caja. Fila 1 = encabezados, fila 2 = subencabezados, datos desde la fila 3.
 *  - Columnas A–S fijas en todas las pestañas. Desde la T se ubican por el nombre del encabezado,
 *    y «Digitalización» / «Nombre de archivo digital» además se verifican por contenido
 *    (en algunas pestañas el encabezado está invertido respecto de los datos).
 *  - La clave del registro es caja + n.º de registro → C{caja}R{registro}. La columna de nomenclatura
 *    del Excel solo se usa como control cruzado.
 *  - Acceso: «Nivel de Acceso» si dice Público/Restringido; si no, PRIVADO = VERDADERO → restringido,
 *    «VERLO» → a_revisar; en cualquier otro caso, público.
 *  - Es incremental: crea lo nuevo, actualiza lo que cambió y respeta los campos bloqueados
 *    (editados a mano en el backoffice). Nunca borra registros.
 *  - El estado «digitalizado» no lo toca: lo define el escaneo de carpetas.
 *  - Lo dado de baja desde el backoffice (eliminado_en) no se vuelve a crear, y los registros
 *    creados desde el backoffice (origen = 'backoffice') no se modifican.
 */
final class Importador
{
    private const FIJAS = [
        0 => 'caja_excel', 1 => 'nombre_caja', 2 => 'numero',
        3 => 'tipo1', 4 => 'tipo2', 5 => 'tipo3',
        6 => 'autor', 7 => 'titulo', 8 => 'lugar', 9 => 'fecha_texto', 10 => 'anio_raw', 11 => 'paginas',
        12 => 'resumen', 13 => 'desc1', 14 => 'desc2', 15 => 'desc3', 16 => 'desc4',
        17 => 'isbn', 18 => 'obs_editorial',
    ];

    /** prefijo de encabezado normalizado => campo. El orden importa ('observaciones de digit' antes que 'digitaliz'). */
    private const VARIABLES = [
        'edicion de catalogo'          => 'edicion_catalogo',
        'observaciones de conservacion' => 'obs_conservacion',
        'nivel de acceso'              => 'nivel_acceso',
        'observaciones de digitaliz'   => 'obs_digitalizacion',
        'nombre de archivo'            => 'nomenclatura',
        'digitaliz'                    => 'digitalizado',
        'seleccion para digitaliz'     => 'seleccion_digit',
        'privado'                      => 'privado',
    ];

    /** Campos de `registro` que escribe el importador (y que se pueden bloquear). */
    private const CAMPOS_REGISTRO = [
        'titulo', 'autor', 'lugar', 'fecha_texto', 'anio', 'anio_texto', 'paginas', 'resumen', 'isbn',
        'obs_editorial', 'edicion_catalogo', 'obs_conservacion', 'obs_digitalizacion', 'seleccion_digit',
        'excel_digitalizado', 'excel_nomenclatura', 'excel_privado', 'acceso',
    ];

    private Reporte $rep;
    /** @var array<string,int> cache de vocabulario "tipo|clave" => id */
    private array $vocab = [];

    public function __construct(private readonly \PDO $db)
    {
    }

    /**
     * @param int[]|null $soloCajas limitar a estas cajas
     */
    public function importar(string $xlsxPath, ?array $soloCajas = null, bool $simulacion = false): Reporte
    {
        $this->rep = Reporte::iniciar('importacion', basename($xlsxPath), $simulacion);
        $this->rep->alcanceTotal = $soloCajas === null;
        $x = new Xlsx($xlsxPath);

        $this->db->beginTransaction();
        try {
            foreach ($x->sheetNames() as $hoja) {
                if (!preg_match('/^\s*Caja\s+(\d+)\b/iu', $hoja, $m)) {
                    $this->rep->sumar('pestañas ignoradas');
                    continue;
                }
                $numCaja = (int)$m[1];
                if ($soloCajas !== null && !in_array($numCaja, $soloCajas, true)) {
                    continue;
                }
                $this->rep->cajasAlcanzadas[] = $numCaja;
                $this->importarCaja($numCaja, trim($hoja), $x->rows($hoja));
            }
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

    /** @param array<int,array<int,mixed>> $rows */
    private function importarCaja(int $numCaja, string $pestania, array $rows): void
    {
        $this->rep->sumar('cajas procesadas');
        $map = $this->mapearColumnas($numCaja, $rows);

        // Filas de datos
        $filas = [];
        foreach ($rows as $idx => $row) {
            if ($idx < 2) {
                continue;
            }
            $num = Texto::entero($row[2] ?? null);
            if ($num === null) {
                if (Texto::limpio($row[7] ?? null) !== null || Texto::limpio($row[12] ?? null) !== null) {
                    $this->rep->incidencia('aviso', 'FILA_SIN_NUMERO', $numCaja, null,
                        'Fila ' . ($idx + 1) . ' tiene datos pero no número de registro: no se importó');
                }
                continue;
            }
            $f = [];
            foreach ($map as $col => $campo) {
                $f[$campo] = $row[$col] ?? null;
            }
            $f['_fila'] = $idx + 1;
            if (isset($filas[$num])) {
                $this->rep->incidencia('error', 'REGISTRO_DUPLICADO', $numCaja, $num,
                    "El registro $num aparece dos veces (filas {$filas[$num]['_fila']} y {$f['_fila']}); se tomó la primera");
                continue;
            }
            $filas[$num] = $f;
        }
        if (!$filas) {
            $this->rep->incidencia('aviso', 'CAJA_VACIA', $numCaja, null, "La pestaña «{$pestania}» no tiene registros");
            return;
        }
        ksort($filas);

        // Caja
        $primera = reset($filas);
        $nombre = Texto::limpio($primera['nombre_caja']) ?? $pestania;
        $resumen = null;
        foreach ($filas as $f) {
            if (($resumen = Texto::limpio($f['edicion_catalogo'] ?? null)) !== null) {
                break;
            }
        }
        $cajaExcel = Texto::entero($primera['caja_excel']);
        if ($cajaExcel !== null && $cajaExcel !== $numCaja) {
            $this->rep->incidencia('aviso', 'CAJA_DISTINTA', $numCaja, null,
                "La pestaña es de la caja $numCaja pero la columna A dice $cajaExcel");
        }
        $cajaId = $this->upsertCaja($numCaja, $nombre, $pestania, $resumen);
        if ($cajaId === null) {
            $this->rep->incidencia('info', 'CAJA_DADA_DE_BAJA', $numCaja, null,
                'La caja fue dada de baja desde el backoffice; no se importó');
            $this->rep->sumar('cajas dadas de baja (se respetan)');
            return;
        }

        // Registros
        $nomenclaturas = [];
        foreach ($filas as $num => $f) {
            $this->importarRegistro($cajaId, $numCaja, $num, $f, $nomenclaturas);
        }
        foreach ($nomenclaturas as $nom => $nums) {
            if (count($nums) > 1) {
                $this->rep->incidencia('aviso', 'NOMENCLATURA_REPETIDA', $numCaja, null,
                    "«{$nom}» figura en los registros " . implode(', ', $nums));
            }
        }

        // Registros que estaban en la base y ya no están en el Excel (no se borran)
        $st = $this->db->prepare("SELECT numero FROM registro WHERE caja_id = ? AND origen = 'excel' AND eliminado_en IS NULL");
        $st->execute([$cajaId]);
        foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $n) {
            if (!isset($filas[(int)$n])) {
                $this->rep->incidencia('aviso', 'REGISTRO_FUERA_DEL_EXCEL', $numCaja, (int)$n,
                    'Está en la base pero ya no figura en el Excel; no se borró');
            }
        }
    }

    /**
     * Devuelve [índice de columna => campo].
     * @param array<int,array<int,mixed>> $rows
     * @return array<int,string>
     */
    private function mapearColumnas(int $numCaja, array $rows): array
    {
        $map = self::FIJAS;
        $enc = $rows[0] ?? [];
        foreach ($enc as $col => $titulo) {
            if ($col < 19 || $titulo === null) {
                continue;
            }
            $k = Texto::clave((string)$titulo);
            foreach (self::VARIABLES as $prefijo => $campo) {
                if (str_starts_with($k, $prefijo)) {
                    if (in_array($campo, $map, true)) {
                        $this->rep->incidencia('aviso', 'COLUMNA_REPETIDA', $numCaja, null,
                            "Hay dos columnas «{$titulo}»; se usa la primera");
                    } else {
                        $map[$col] = $campo;
                    }
                    break;
                }
            }
        }

        // Verificación por contenido de Digitalización / Nombre de archivo
        $colDig = array_search('digitalizado', $map, true);
        $colNom = array_search('nomenclatura', $map, true);
        if ($colDig !== false && $colNom !== false) {
            [$digBool, $digCod] = $this->perfilColumna($rows, (int)$colDig);
            [$nomBool, $nomCod] = $this->perfilColumna($rows, (int)$colNom);
            if ($digCod > $digBool && $nomBool > $nomCod) {
                $map[$colDig] = 'nomenclatura';
                $map[$colNom] = 'digitalizado';
                $this->rep->incidencia('info', 'COLUMNAS_INVERTIDAS', $numCaja, null,
                    'Los encabezados de «Digitalización» y «Nombre de archivo digital» están invertidos; se leyeron por contenido');
            }
        }
        return $map;
    }

    /** @return array{0:int,1:int} [celdas booleanas, celdas con forma de código] */
    private function perfilColumna(array $rows, int $col): array
    {
        $b = $c = 0;
        foreach ($rows as $i => $r) {
            if ($i < 2) {
                continue;
            }
            $v = $r[$col] ?? null;
            if (is_bool($v)) {
                $b++;
            } elseif (is_string($v) && Texto::parsearCodigo($v) !== null) {
                $c++;
            }
        }
        return [$b, $c];
    }

    private function upsertCaja(int $numero, string $nombre, string $pestania, ?string $resumen): ?int
    {
        $st = $this->db->prepare('SELECT id, campos_bloqueados, eliminado_en FROM caja WHERE numero = ?');
        $st->execute([$numero]);
        $c = $st->fetch();
        if (!$c) {
            $this->db->prepare('INSERT INTO caja (numero, nombre, nombre_pestania, resumen) VALUES (?,?,?,?)')
                     ->execute([$numero, $nombre, $pestania, $resumen]);
            $this->rep->sumar('cajas nuevas');
            return (int)$this->db->lastInsertId();
        }
        if ($c['eliminado_en'] !== null) {
            return null;
        }
        $bloq = json_decode((string)$c['campos_bloqueados'], true) ?: [];
        $sets = ['nombre_pestania = ?'];
        $vals = [$pestania];
        if (!in_array('nombre', $bloq, true)) {
            $sets[] = 'nombre = ?';
            $vals[] = $nombre;
        }
        if (!in_array('resumen', $bloq, true)) {
            $sets[] = 'resumen = ?';
            $vals[] = $resumen;
        }
        $vals[] = $c['id'];
        $this->db->prepare('UPDATE caja SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
        return (int)$c['id'];
    }

    /** @param array<string,int[]> $nomenclaturas acumulador por caja */
    private function importarRegistro(int $cajaId, int $numCaja, int $num, array $f, array &$nomenclaturas): void
    {
        $codigo = Texto::codigo($numCaja, $num);

        // Año
        $anioRaw = $f['anio_raw'];
        $anioTxt = Texto::limpio($anioRaw);
        $anio = Texto::entero($anioRaw);
        if ($anio !== null && ($anio < 1900 || $anio > 2100)) {
            $anio = null;
        }

        // Nomenclatura (control cruzado)
        $nom = Texto::limpio($f['nomenclatura'] ?? null);
        if ($nom !== null) {
            $p = Texto::parsearCodigo($nom);
            $nomenclaturas[strtoupper($nom)][] = $num;
            if ($p === null) {
                $this->rep->incidencia('aviso', 'NOMENCLATURA_ILEGIBLE', $numCaja, $num, "«{$nom}» no tiene forma C#R#");
            } elseif ($p[0] !== $numCaja || $p[1] !== $num) {
                $this->rep->incidencia('aviso', 'NOMENCLATURA_DISTINTA', $numCaja, $num,
                    "El Excel dice «{$nom}»; se usa $codigo (caja + n.º de registro)");
            }
        }

        // Digitalización según el Excel
        $dig = $f['digitalizado'] ?? null;
        $excelDig = is_bool($dig) ? (int)$dig : null;

        // Acceso
        [$acceso, $privadoTxt] = $this->resolverAcceso($numCaja, $num, $f);

        $datos = [
            'titulo'             => Texto::limpio($f['titulo']),
            'autor'              => Texto::limpio($f['autor']),
            'lugar'              => Texto::limpio($f['lugar']),
            'fecha_texto'        => Texto::limpio($f['fecha_texto']),
            'anio'               => $anio,
            'anio_texto'         => $anioTxt,
            'paginas'            => Texto::entero($f['paginas']),
            'resumen'            => Texto::limpio($f['resumen']),
            'isbn'               => Texto::limpio($f['isbn']),
            'obs_editorial'      => Texto::limpio($f['obs_editorial']),
            'edicion_catalogo'   => Texto::limpio($f['edicion_catalogo'] ?? null),
            'obs_conservacion'   => Texto::limpio($f['obs_conservacion'] ?? null),
            'obs_digitalizacion' => Texto::limpio($f['obs_digitalizacion'] ?? null),
            'seleccion_digit'    => Texto::limpio($f['seleccion_digit'] ?? null),
            'excel_digitalizado' => $excelDig,
            'excel_nomenclatura' => $nom,
            'excel_privado'      => $privadoTxt,
            'acceso'             => $acceso,
        ];
        foreach (['titulo' => 500, 'autor' => 500, 'lugar' => 500, 'fecha_texto' => 500, 'anio_texto' => 60,
                  'isbn' => 60, 'seleccion_digit' => 500, 'excel_nomenclatura' => 40, 'excel_privado' => 255] as $k => $max) {
            if ($datos[$k] !== null && mb_strlen((string)$datos[$k]) > $max) {
                $datos[$k] = mb_substr((string)$datos[$k], 0, $max);
                $this->rep->incidencia('aviso', 'TEXTO_RECORTADO', $numCaja, $num, "El campo $k superaba $max caracteres");
            }
        }

        $st = $this->db->prepare('SELECT id, campos_bloqueados, origen, eliminado_en FROM registro WHERE caja_id = ? AND numero = ?');
        $st->execute([$cajaId, $num]);
        $r = $st->fetch();
        if ($r && $r['eliminado_en'] !== null) {
            $this->rep->incidencia('info', 'REGISTRO_DADO_DE_BAJA', $numCaja, $num,
                'Fue dado de baja desde el backoffice y sigue en el Excel; no se volvió a crear');
            $this->rep->sumar('registros dados de baja (se respetan)');
            return;
        }
        if ($r && $r['origen'] === 'backoffice') {
            $this->rep->incidencia('aviso', 'REGISTRO_DEL_BACKOFFICE', $numCaja, $num,
                'Fue creado desde el backoffice y ahora también figura en el Excel; no se modificó. Revisar cuál vale');
            $this->rep->sumar('registros del backoffice (no se tocan)');
            return;
        }

        if (!$r) {
            $cols = array_keys($datos);
            $sql = 'INSERT INTO registro (caja_id, numero, codigo, origen, importado_en, ' . implode(', ', $cols)
                 . ') VALUES (?,?,?,\'excel\',NOW(),' . implode(',', array_fill(0, count($cols), '?')) . ')';
            $this->db->prepare($sql)->execute(array_merge([$cajaId, $num, $codigo], array_values($datos)));
            $regId = (int)$this->db->lastInsertId();
            $this->rep->sumar('registros nuevos');
            $bloq = [];
        } else {
            $regId = (int)$r['id'];
            $bloq = json_decode((string)$r['campos_bloqueados'], true) ?: [];
            $set = [];
            $vals = [];
            foreach ($datos as $k => $v) {
                if (in_array($k, $bloq, true)) {
                    continue;
                }
                $set[] = "$k = ?";
                $vals[] = $v;
            }
            $vals[] = $regId;
            $up = $this->db->prepare('UPDATE registro SET ' . implode(', ', $set) . ' WHERE id = ?');
            $up->execute($vals);
            if ($up->rowCount() > 0) {
                $this->db->prepare('UPDATE registro SET importado_en = NOW() WHERE id = ?')->execute([$regId]);
                $this->rep->sumar('registros actualizados');
            } else {
                $this->rep->sumar('registros sin cambios');
            }
        }

        // Vocabularios
        if (!in_array('tipos', $bloq, true)) {
            $this->vincular($regId, 'tipo_documento', [$f['tipo1'], $f['tipo2'], $f['tipo3']], $numCaja, $num);
        }
        if (!in_array('descriptores', $bloq, true)) {
            $this->vincular($regId, 'descriptor', [$f['desc1'], $f['desc2'], $f['desc3'], $f['desc4']], $numCaja, $num);
        }
    }

    /** @return array{0:string,1:?string} [acceso, valor crudo de PRIVADO] */
    private function resolverAcceso(int $caja, int $num, array $f): array
    {
        $nivel = Texto::clave(Texto::limpio($f['nivel_acceso'] ?? null));
        $priv = $f['privado'] ?? null;
        $privTxt = is_bool($priv) ? ($priv ? 'VERDADERO' : 'FALSO') : Texto::limpio($priv);

        if (str_starts_with($nivel, 'restring')) {
            return ['restringido', $privTxt];
        }
        if (str_starts_with($nivel, 'public')) {
            return ['publico', $privTxt];
        }
        if ($priv === true) {
            return ['restringido', $privTxt];
        }
        if (is_string($priv) && $privTxt !== null) {
            $k = Texto::clave($privTxt);
            if (in_array($k, ['verdadero', 'true', 'si', 'privado', 'x'], true)) {
                return ['restringido', $privTxt];
            }
            if ($k === 'verlo') {
                $this->rep->incidencia('aviso', 'ACCESO_A_REVISAR', $caja, $num, 'PRIVADO dice «VERLO»');
                return ['a_revisar', $privTxt];
            }
            $this->rep->incidencia('aviso', 'PRIVADO_CON_TEXTO', $caja, $num,
                'La columna PRIVADO tiene texto que no es un valor de acceso: «' . mb_substr($privTxt, 0, 60) . '…»; se tomó como público');
        }
        return ['publico', $privTxt];
    }

    /** @param array<int,mixed> $valores */
    private function vincular(int $regId, string $tipo, array $valores, int $caja, int $num): void
    {
        $ids = [];
        $orden = 0;
        foreach ($valores as $v) {
            $nombre = Texto::limpio($v);
            if ($nombre === null) {
                continue;
            }
            if (mb_strlen($nombre) > 80) {
                $this->rep->incidencia('aviso', 'VOCABULARIO_TEXTO_LARGO', $caja, $num,
                    ($tipo === 'descriptor' ? 'Descriptor' : 'Tipo') . ' con texto largo, no parece un término: «'
                    . mb_substr($nombre, 0, 60) . '…»; no se cargó');
                continue;
            }
            $orden++;
            $id = $this->vocabId($tipo, $nombre, $caja, $num);
            if (!isset($ids[$id])) {
                $ids[$id] = $orden;
            }
        }
        $this->db->prepare('DELETE rv FROM registro_vocabulario rv JOIN vocabulario v ON v.id = rv.vocabulario_id
                            WHERE rv.registro_id = ? AND v.tipo = ?')->execute([$regId, $tipo]);
        $ins = $this->db->prepare('INSERT INTO registro_vocabulario (registro_id, vocabulario_id, orden) VALUES (?,?,?)');
        foreach ($ids as $id => $o) {
            $ins->execute([$regId, $id, $o]);
        }
    }

    private function vocabId(string $tipo, string $nombre, int $caja, int $num): int
    {
        $clave = Texto::clave($nombre);
        $k = "$tipo|$clave";
        if (isset($this->vocab[$k])) {
            return $this->vocab[$k];
        }
        $st = $this->db->prepare('SELECT id, fusionado_en FROM vocabulario WHERE tipo = ? AND clave = ?');
        $st->execute([$tipo, $clave]);
        $row = $st->fetch();
        if ($row) {
            $id = (int)($row['fusionado_en'] ?? $row['id']);
        } else {
            $this->db->prepare("INSERT INTO vocabulario (tipo, nombre, clave, estado) VALUES (?,?,?,'propuesto')")
                     ->execute([$tipo, $nombre, $clave]);
            $id = (int)$this->db->lastInsertId();
            $this->rep->sumar($tipo === 'descriptor' ? 'descriptores nuevos (a curar)' : 'tipos nuevos (a curar)');
        }
        return $this->vocab[$k] = $id;
    }
}
