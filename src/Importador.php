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
 *  - Año (col. K): Texto::anio() da la lista de años (registro_anio, src/Anios.php) y el resumen
 *    menor/mayor/sin confirmar. Un rango responde a todos sus años; una lista salteada, solo a los que nombra.
 *    Lo que no se puede interpretar queda sin año y se informa.
 *  - Tipos y descriptores se resuelven con Aida\Vocabulario: fusiones, términos movidos al otro
 *    vocabulario y grafías anteriores van al término vigente; los descartados se ignoran.
 *    Cada regla aplicada se informa agrupada por caja (src/Alertas.php, VOCAB_*).
 */
final class Importador
{
    /** Nombres de campo legibles para los avisos. */
    private const ETIQUETAS = ['titulo' => 'Título', 'autor' => 'Autor', 'lugar' => 'Lugar', 'fecha_texto' => 'Fecha',
        'anio_texto' => 'Año', 'isbn' => 'ISBN', 'seleccion_digit' => 'Selección para digitalizar',
        'excel_nomenclatura' => 'Nombre de archivo', 'excel_privado' => 'PRIVADO', 'acceso' => 'Acceso', 'paginas' => 'Páginas',
        'resumen' => 'Resumen', 'obs_editorial' => 'Observaciones/Editorial', 'edicion_catalogo' => 'Edición de catálogo',
        'obs_conservacion' => 'Observaciones de conservación', 'obs_digitalizacion' => 'Observaciones de digitalización'];

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
        'titulo', 'autor', 'lugar', 'fecha_texto', 'anio', 'anio_hasta', 'anio_sin_confirmar', 'anio_texto', 'paginas', 'resumen', 'isbn',
        'obs_editorial', 'edicion_catalogo', 'obs_conservacion', 'obs_digitalizacion', 'seleccion_digit',
        'excel_digitalizado', 'excel_nomenclatura', 'excel_privado', 'acceso',
    ];

    private Reporte $rep;
    private Historial $hist;
    /** @var array<string,array|false> cache de vocabulario "tipo|clave" => resolución (false = descartado) */
    private array $vocab = [];
    private Vocabulario $voc;
    /** @var array<string,array{codigo:string,detalle:string,registros:list<int>}> reglas de curaduría aplicadas en la caja actual */
    private array $reglas = [];
    /** @var list<string> campos bloqueados que la planilla ya trae corregidos, en la caja actual («R2 Autor») */
    private array $corregidos = [];
    /** @var list<string> ídem para los campos de la caja (se juntan antes de procesar sus registros) */
    private array $corregidosCaja = [];

    public function __construct(private readonly \PDO $db)
    {
    }

    /**
     * @param int[]|null $soloCajas limitar a estas cajas
     * @param string|null $fuente nombre que queda en la bitácora (por defecto, el del archivo). El backoffice
     *                            guarda la subida con un nombre generado y pasa acá el nombre original.
     */
    public function importar(string $xlsxPath, ?array $soloCajas = null, bool $simulacion = false, ?string $fuente = null): Reporte
    {
        // Candado en la base: no puede haber dos importaciones a la vez (web y consola comparten este punto).
        if ((int)$this->db->query("SELECT GET_LOCK('aida_importacion', 0)")->fetchColumn() !== 1) {
            throw new \RuntimeException('Hay otra importación corriendo en este momento. Esperá a que termine y probá de nuevo.', 409);
        }
        try {
            return $this->importarSinCandado($xlsxPath, $soloCajas, $simulacion, $fuente);
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('aida_importacion')");
        }
    }

    private function importarSinCandado(string $xlsxPath, ?array $soloCajas, bool $simulacion, ?string $fuente): Reporte
    {
        $this->rep = Reporte::iniciar('importacion', $fuente ?? basename($xlsxPath), $simulacion);
        $this->rep->alcanceTotal = $soloCajas === null;
        $x = new Xlsx($xlsxPath);

        $this->db->beginTransaction();
        try {
            $this->rep->abrir($this->db);
            $this->hist = new Historial($this->db, null, 'importacion', $this->rep->procesoId);
            $this->voc = new Vocabulario($this->db);
            $this->vocab = [];
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
        $this->reglas = [];
        $this->corregidos = $this->corregidosCaja;
        $this->corregidosCaja = [];
        foreach ($filas as $num => $f) {
            $this->importarRegistro($cajaId, $numCaja, $num, $f, $nomenclaturas);
        }
        // Reglas de curaduría aplicadas: una incidencia informativa por regla y caja
        foreach ($this->reglas as $g) {
            sort($g['registros']);
            $n = count($g['registros']);
            $lista = implode(', ', array_map(fn($x) => "R$x", array_slice($g['registros'], 0, 30))) . ($n > 30 ? ' …' : '');
            $this->rep->incidencia('info', $g['codigo'], $numCaja, $n === 1 ? $g['registros'][0] : null,
                sprintf($g['detalle'], $n === 1 ? '1 registro' : "$n registros") . " ($lista)");
            $this->rep->sumar('reglas de curaduría aplicadas', $n);
        }
        // Campos corregidos a mano que el equipo ya corrigió en la planilla: una incidencia informativa por caja
        if ($this->corregidos) {
            $n = count($this->corregidos);
            $lista = implode(', ', array_slice($this->corregidos, 0, 30)) . ($n > 30 ? ' …' : '');
            $this->rep->incidencia('info', 'CORREGIDO_EN_PLANILLA', $numCaja, null, ($n === 1
                ? 'El equipo corrigió en la planilla 1 campo que estaba corregido a mano; se desbloqueó'
                : "El equipo corrigió en la planilla $n campos que estaban corregidos a mano; se desbloquearon") . " ($lista)");
            $this->corregidos = [];
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
            $id = (int)$this->db->lastInsertId();
            $this->hist->registrar('caja', $id, 'alta', [], ['numero' => $numero, 'nombre' => $nombre, 'resumen' => $resumen]);
            return $id;
        }
        if ($c['eliminado_en'] !== null) {
            return null;
        }
        $delExcel = ['nombre' => $nombre, 'resumen' => $resumen];
        $bloq = $this->desbloquearCorregidos('caja', (int)$c['id'], $numero, null,
            json_decode((string)$c['campos_bloqueados'], true) ?: [], $delExcel);
        ['nombre' => $nombre, 'resumen' => $resumen] = $delExcel;   // con la versión de la base, si se conservó
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
        $antes = $this->hist->leer('caja', (int)$c['id'], ['nombre', 'resumen']);
        $this->db->prepare('UPDATE caja SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
        $this->hist->registrar('caja', (int)$c['id'], 'modificacion', $antes, $this->hist->leer('caja', (int)$c['id'], ['nombre', 'resumen']));
        return (int)$c['id'];
    }

    /** @param array<string,int[]> $nomenclaturas acumulador por caja */
    private function importarRegistro(int $cajaId, int $numCaja, int $num, array $f, array &$nomenclaturas): void
    {
        $codigo = Texto::codigo($numCaja, $num);

        // Año: año solo, rango o con duda (Texto::anio, tabla en docs/decisiones.md)
        $anioRaw = $f['anio_raw'];
        $anioTxt = Texto::limpio($anioRaw);
        $an = Texto::anio($anioRaw);
        if ($an['formato'] === 'ilegible') {
            $this->rep->incidencia('info', 'ANIO_NO_INTERPRETADO', $numCaja, $num,
                'La columna Año dice «' . mb_substr((string)$anioTxt, 0, 80) . '»: no se pudo leer un año, así que no aparece en el filtro por año');
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
            'anio'               => $an['desde'],
            'anio_hasta'         => $an['hasta'],
            'anio_sin_confirmar' => (int)$an['sin_confirmar'],
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
                $this->rep->incidencia('aviso', 'TEXTO_RECORTADO', $numCaja, $num, "El campo «" . (self::ETIQUETAS[$k] ?? $k) . "» superaba $max caracteres");
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
            $antes = null;
        } else {
            $regId = (int)$r['id'];
            $anioExcel = $datos['anio_texto'];
            $bloq = $this->desbloquearCorregidos('registro', $regId, $numCaja, $num,
                json_decode((string)$r['campos_bloqueados'], true) ?: [], $datos);
            if (in_array('anio_texto', $bloq, true) || $datos['anio_texto'] !== $anioExcel) {
                // Año editado a mano (o conservado al desbloquearlo): los campos derivados salen del texto que quedó en la base, no del Excel
                $st = $this->db->prepare('SELECT anio_texto FROM registro WHERE id = ?');
                $st->execute([$regId]);
                $an = Texto::anio($st->fetchColumn());
                $datos['anio'] = $an['desde'];
                $datos['anio_hasta'] = $an['hasta'];
                $datos['anio_sin_confirmar'] = (int)$an['sin_confirmar'];
                $bloq = array_values(array_diff($bloq, ['anio', 'anio_hasta', 'anio_sin_confirmar']));
            }
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
            $seguidos = array_merge(array_keys(array_diff_key($datos, array_flip($bloq))),
                                    array_diff(['tipos', 'descriptores'], $bloq));
            $antes = $this->hist->leer('registro', $regId, $seguidos);
            $up = $this->db->prepare('UPDATE registro SET ' . implode(', ', $set) . ' WHERE id = ?');
            $up->execute($vals);
            if ($up->rowCount() > 0) {
                $this->db->prepare('UPDATE registro SET importado_en = NOW() WHERE id = ?')->execute([$regId]);
                $this->rep->sumar('registros actualizados');
            } else {
                $this->rep->sumar('registros sin cambios');
            }
        }

        // Años del filtro (registro_anio): siguen al año que quedó en el registro
        (new Anios($this->db))->sincronizar($regId);

        // Vocabularios
        $this->vincular($regId, [
            'tipo_documento' => [$f['tipo1'], $f['tipo2'], $f['tipo3']],
            'descriptor' => [$f['desc1'], $f['desc2'], $f['desc3'], $f['desc4']],
        ], $bloq, $numCaja, $num);

        // Historial (permite deshacer esta importación)
        if ($antes === null) {
            $this->hist->registrar('registro', $regId, 'alta', [],
                $datos + ['codigo' => $codigo, 'tipos' => $this->hist->leerLista($regId, 'tipos'),
                          'descriptores' => $this->hist->leerLista($regId, 'descriptores')]);
        } else {
            $this->hist->registrar('registro', $regId, 'modificacion', $antes, $this->hist->leer('registro', $regId, array_keys($antes)));
        }
    }

    /**
     * Cierra el circuito con la planilla: si un campo bloqueado (corregido a mano) ahora trae en el Excel
     * el mismo valor que la base — sin contar espacios ni mayúsculas, sí las tildes —, el equipo ya lo corrigió
     * en su planilla: se desbloquea (vía Historial, queda en esta importación y se deshace con ella). Si el valor
     * es idéntico, vuelve a tomarse del Excel; si solo coincide ignorando mayúsculas y espacios («aída  BORTNIK»
     * vs «Aída Bortnik»), se conserva el de la base: se reemplaza en $datos (decisión del PM, 15/11/2026).
     * Si el Excel trae otro valor, el bloqueo sigue.
     * @param list<string> $bloq
     * @param array<string,mixed> $datos lo que trae el Excel; vuelve con el valor de la base en los conservados
     * @return list<string> campos que siguen bloqueados
     */
    private function desbloquearCorregidos(string $entidad, int $id, int $numCaja, ?int $num, array $bloq, array &$datos): array
    {
        $candidatos = array_values(array_intersect($bloq, array_keys($datos)));
        if (!$candidatos) {
            return $bloq;
        }
        // Los derivados del año siguen al texto: se comparan por anio_texto
        $candidatos = array_values(array_diff($candidatos, ['anio', 'anio_hasta', 'anio_sin_confirmar']));
        $base = $candidatos ? $this->hist->leer($entidad, $id, $candidatos) : [];
        $libres = [];
        foreach ($candidatos as $campo) {
            if (Texto::comparable($datos[$campo]) === Texto::comparable($base[$campo] ?? null)) {
                $libres[] = $campo;
            }
        }
        if (!$libres) {
            return $bloq;
        }
        if (in_array('anio_texto', $libres, true)) {
            $libres = array_merge($libres, ['anio', 'anio_hasta', 'anio_sin_confirmar']);
        }
        $quedan = array_values(array_diff($bloq, $libres));
        $this->hist->actualizar($entidad, $id, ['campos_bloqueados' => $quedan ? json_encode($quedan) : null], false);
        $this->rep->sumar('campos corregidos en la planilla (se desbloquean)', count(array_intersect($candidatos, $libres)));
        $etq = $entidad === 'caja' ? ['nombre' => 'Nombre de caja', 'resumen' => 'Resumen de la caja'] : self::ETIQUETAS;
        foreach (array_intersect($candidatos, $libres) as $campo) {
            $conservado = (string)$datos[$campo] !== (string)($base[$campo] ?? '');
            if ($conservado) {
                // Coincide solo sin mayúsculas ni espacios: queda nuestra versión
                $datos[$campo] = $base[$campo];
                $this->rep->sumar('campos desbloqueados conservando la versión de la base');
            }
            $txt = ($num !== null ? "R$num " : '') . ($etq[$campo] ?? $campo) . ($conservado ? ' (se conservó la versión de la base)' : '');
            if ($entidad === 'caja') {
                $this->corregidosCaja[] = $txt;
            } else {
                $this->corregidos[] = $txt;
            }
        }
        return $quedan;
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

    /**
     * Asigna tipos y descriptores. Cada valor se resuelve al término vigente, que puede ser del otro
     * vocabulario (término movido): «Televisión» en la columna Tipo se carga como descriptor.
     * Los que llegan por una regla de curaduría van después de los propios de la columna, en el orden del Excel.
     * @param array<string,array<int,mixed>> $columnas tipo de vocabulario => valores de sus columnas
     * @param list<string> $bloq campos bloqueados del registro ('tipos', 'descriptores')
     */
    private function vincular(int $regId, array $columnas, array $bloq, int $caja, int $num): void
    {
        $propios = ['tipo_documento' => [], 'descriptor' => []];
        $movidos = ['tipo_documento' => [], 'descriptor' => []];
        foreach ($columnas as $tipo => $valores) {
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
                $res = $this->vocabId($tipo, $nombre);
                if ($res['regla'] !== null) {
                    $this->anotarRegla($tipo, $nombre, $res, $num);
                }
                if ($res['descartado']) {
                    continue;
                }
                if ($res['tipo'] === $tipo) {
                    $propios[$tipo][$res['id']] ??= true;
                } else {
                    $movidos[$res['tipo']][$res['id']] ??= true;
                }
            }
        }
        $ins = $this->db->prepare('INSERT INTO registro_vocabulario (registro_id, vocabulario_id, orden) VALUES (?,?,?)');
        foreach ([ 'tipo_documento' => 'tipos', 'descriptor' => 'descriptores'] as $tipo => $campo) {
            if (in_array($campo, $bloq, true)) {
                continue;
            }
            $ids = array_keys($propios[$tipo] + $movidos[$tipo]);
            $this->db->prepare('DELETE rv FROM registro_vocabulario rv JOIN vocabulario v ON v.id = rv.vocabulario_id
                                WHERE rv.registro_id = ? AND v.tipo = ?')->execute([$regId, $tipo]);
            foreach ($ids as $i => $id) {
                $ins->execute([$regId, $id, $i + 1]);
            }
        }
    }

    /** Junta las reglas aplicadas en la caja para informarlas agrupadas. */
    private function anotarRegla(string $tipo, string $nombre, array $res, int $num): void
    {
        $col = Vocabulario::NOMBRES[$tipo];
        [$codigo, $detalle] = match ($res['regla']) {
            'descartado' => ['VOCAB_DESCARTADO', "«{$nombre}» llegó como {$col} en %s y se ignoró: es un término descartado"],
            'movido' => ['VOCAB_MOVIDO', "«{$nombre}» llegó como {$col} en %s y se cargó como " . Vocabulario::NOMBRES[$res['tipo']] . " «{$res['nombre']}»"],
            'renombre' => ['VOCAB_RENOMBRADO', "«{$nombre}» ({$col}) llegó con la grafía anterior en %s y se cargó como «{$res['nombre']}»"],
            default => ['VOCAB_FUSIONADO', "«{$nombre}» ({$col}) llegó en %s y se cargó como «{$res['nombre']}», el término en que se fusionó"],
        };
        $k = "$codigo|$tipo|" . Texto::clave($nombre);
        $this->reglas[$k] ??= ['codigo' => $codigo, 'detalle' => $detalle, 'registros' => []];
        if (!in_array($num, $this->reglas[$k]['registros'], true)) {
            $this->reglas[$k]['registros'][] = $num;
        }
    }

    /**
     * Término vigente para un valor de la columna $tipo. Si no existe, se crea como propuesto.
     * @return array{id:int, tipo:string, nombre:string, descartado:bool, regla:?string}
     */
    private function vocabId(string $tipo, string $nombre): array
    {
        $k = "$tipo|" . Texto::clave($nombre);
        if (isset($this->vocab[$k])) {
            return $this->vocab[$k];
        }
        $r = $this->voc->resolver($tipo, $nombre);
        if ($r === null) {
            $this->db->prepare("INSERT INTO vocabulario (tipo, nombre, clave, estado) VALUES (?,?,?,'propuesto')")
                     ->execute([$tipo, $nombre, Texto::clave($nombre)]);
            $this->rep->sumar($tipo === 'descriptor' ? 'descriptores nuevos (a curar)' : 'tipos nuevos (a curar)');
            return $this->vocab[$k] = ['id' => (int)$this->db->lastInsertId(), 'tipo' => $tipo, 'nombre' => $nombre,
                                       'descartado' => false, 'regla' => null];
        }
        if ($r['via'] === 'eliminado') {
            // Término cuya alta se deshizo: vuelve como propuesto, porque la planilla lo sigue usando
            $id = (int)$r['inicio']['id'];
            $antes = $this->hist->leer('vocabulario', $id, ['estado', 'fusionado_en', 'regla', 'eliminado_en', 'eliminado_por']);
            $this->db->prepare("UPDATE vocabulario SET estado = 'propuesto', fusionado_en = NULL, regla = NULL,
                                eliminado_en = NULL, eliminado_por = NULL WHERE id = ?")->execute([$id]);
            $this->hist->registrar('vocabulario', $id, 'restauracion', $antes, $this->hist->leer('vocabulario', $id, array_keys($antes)));
            $this->rep->sumar($tipo === 'descriptor' ? 'descriptores nuevos (a curar)' : 'tipos nuevos (a curar)');
            return $this->vocab[$k] = ['id' => $id, 'tipo' => $tipo, 'nombre' => (string)$r['inicio']['nombre'],
                                       'descartado' => false, 'regla' => null];
        }
        $fin = $r['final'];
        $descartado = $fin['estado'] === 'descartado';
        return $this->vocab[$k] = ['id' => (int)$fin['id'], 'tipo' => (string)$fin['tipo'], 'nombre' => (string)$fin['nombre'],
                                   'descartado' => $descartado, 'regla' => $descartado ? 'descartado' : $r['regla']];
    }
}
