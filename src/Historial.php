<?php
declare(strict_types=1);

namespace Aida;

/**
 * Historial de cambios reversible.
 *
 * Todo cambio sobre cajas y registros (backoffice o importación) se guarda en `auditoria`
 * con el valor anterior y el nuevo de cada campo. Con eso se puede:
 *   - deshacer un cambio puntual            → revertir($idCambio)
 *   - deshacer todo lo que hizo una corrida → revertirProceso($idProceso)
 *
 * Deshacer también queda registrado (accion = 'reversion'), así que una reversión se puede volver a revertir.
 * Si el campo cambió de nuevo después del cambio que se quiere deshacer, se informa el conflicto
 * y no se aplica nada salvo que se pida forzar.
 *
 * El backoffice debe hacer sus altas, modificaciones y bajas a través de esta clase
 * (crear / actualizar / darDeBaja / restaurar) para que todo quede registrado.
 */
final class Historial
{
    /** Campos que se pueden editar y revertir por entidad. 'tipos' y 'descriptores' son listas de términos. */
    public const CAMPOS = [
        'caja' => ['numero', 'nombre', 'resumen', 'anio_desde', 'anio_hasta', 'publicada', 'eliminado_en', 'eliminado_por', 'campos_bloqueados'],
        'registro' => [
            'caja_id', 'numero', 'codigo', 'titulo', 'autor', 'lugar', 'fecha_texto', 'anio', 'anio_texto', 'paginas',
            'resumen', 'isbn', 'obs_editorial', 'edicion_catalogo', 'obs_conservacion', 'obs_digitalizacion',
            'seleccion_digit', 'excel_digitalizado', 'excel_nomenclatura', 'excel_privado', 'acceso', 'publicado',
            'origen', 'eliminado_en', 'eliminado_por', 'campos_bloqueados', 'tipos', 'descriptores',
        ],
        'archivo_digital' => ['registro_id', 'parte', 'eliminado_en', 'eliminado_por'],
    ];

    private const LISTAS = ['tipos' => 'tipo_documento', 'descriptores' => 'descriptor'];

    public function __construct(
        private readonly \PDO $db,
        private readonly ?int $usuarioId = null,
        private readonly string $origen = 'backoffice',
        private readonly ?int $procesoId = null,
    ) {
    }

    // ------------------------------------------------------------------
    // Registro de cambios
    // ------------------------------------------------------------------

    /**
     * Guarda un cambio. Solo anota los campos que efectivamente cambiaron.
     * @param array<string,mixed> $antes
     * @param array<string,mixed> $despues
     */
    public function registrar(string $entidad, int $id, string $accion, array $antes, array $despues, ?int $revierte = null): ?int
    {
        $cambios = [];
        foreach (array_unique(array_merge(array_keys($antes), array_keys($despues))) as $campo) {
            $a = $antes[$campo] ?? null;
            $d = $despues[$campo] ?? null;
            if (self::igual($a, $d)) {
                continue;
            }
            $cambios[$campo] = [$a, $d];
        }
        if (!$cambios && $accion === 'modificacion') {
            return null;
        }
        $this->db->prepare('INSERT INTO auditoria (usuario_id, origen, proceso_id, entidad, entidad_id, accion, cambios, revierte_id)
                            VALUES (?,?,?,?,?,?,?,?)')
                 ->execute([$this->usuarioId, $revierte ? 'reversion' : $this->origen, $this->procesoId, $entidad, $id,
                            $accion, json_encode($cambios, JSON_UNESCAPED_UNICODE), $revierte]);
        return (int)$this->db->lastInsertId();
    }

    // ------------------------------------------------------------------
    // Operaciones del backoffice (todas quedan en el historial)
    // ------------------------------------------------------------------

    /** @param array<string,mixed> $datos */
    public function crear(string $entidad, array $datos): int
    {
        $listas = array_intersect_key($datos, self::LISTAS);
        $campos = array_diff_key($datos, self::LISTAS);
        if ($entidad === 'registro') {
            $campos['origen'] = 'backoffice';
            $campos['creado_por'] = $this->usuarioId;
        }
        $cols = array_keys($campos);
        $this->db->prepare("INSERT INTO $entidad (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')
                 ->execute(array_values($campos));
        $id = (int)$this->db->lastInsertId();
        foreach ($listas as $campo => $nombres) {
            $this->escribirLista($id, $campo, (array)$nombres);
        }
        $this->registrar($entidad, $id, 'alta', [], $datos);
        return $id;
    }

    /**
     * Modifica campos. Por defecto bloquea los campos tocados para que el Excel no los pise.
     * @param array<string,mixed> $datos
     */
    public function actualizar(string $entidad, int $id, array $datos, bool $bloquear = true, string $accion = 'modificacion'): ?int
    {
        $this->validarCampos($entidad, array_keys($datos));
        $antes = $this->leer($entidad, $id, array_keys($datos));
        if ($bloquear && in_array('campos_bloqueados', self::CAMPOS[$entidad], true)) {
            $bloq = json_decode((string)($this->leer($entidad, $id, ['campos_bloqueados'])['campos_bloqueados'] ?? ''), true) ?: [];
            $nuevos = array_values(array_unique(array_merge($bloq, array_diff(array_keys($datos), ['eliminado_en', 'eliminado_por', 'campos_bloqueados']))));
            if ($nuevos !== $bloq) {
                $antes['campos_bloqueados'] = $bloq ? json_encode($bloq) : null;
                $datos['campos_bloqueados'] = json_encode($nuevos);
            }
        }
        $this->escribir($entidad, $id, $datos);
        return $this->registrar($entidad, $id, $accion, $antes, $this->leer($entidad, $id, array_keys($datos)));
    }

    public function darDeBaja(string $entidad, int $id): ?int
    {
        return $this->actualizar($entidad, $id, ['eliminado_en' => date('Y-m-d H:i:s'), 'eliminado_por' => $this->usuarioId], false, 'baja');
    }

    public function restaurar(string $entidad, int $id): ?int
    {
        return $this->actualizar($entidad, $id, ['eliminado_en' => null, 'eliminado_por' => null], false, 'restauracion');
    }

    // ------------------------------------------------------------------
    // Volver atrás
    // ------------------------------------------------------------------

    /**
     * Deshace un cambio.
     * @return array{ok:bool, mensaje:string, conflictos:list<string>}
     */
    public function revertir(int $idCambio, bool $forzar = false): array
    {
        $st = $this->db->prepare('SELECT * FROM auditoria WHERE id = ?');
        $st->execute([$idCambio]);
        $c = $st->fetch();
        if (!$c) {
            return ['ok' => false, 'mensaje' => "No existe el cambio $idCambio", 'conflictos' => []];
        }
        if ($c['revertido_en'] !== null) {
            return ['ok' => false, 'mensaje' => "El cambio $idCambio ya fue revertido", 'conflictos' => []];
        }
        $entidad = $c['entidad'];
        $id = (int)$c['entidad_id'];
        $cambios = json_decode((string)$c['cambios'], true) ?: [];

        if ($c['accion'] === 'alta') {
            // Deshacer un alta = baja lógica (no se borra nada)
            $antes = $this->leer($entidad, $id, ['eliminado_en']);
            $this->escribir($entidad, $id, ['eliminado_en' => date('Y-m-d H:i:s'), 'eliminado_por' => $this->usuarioId]);
            $this->registrar($entidad, $id, 'reversion', $antes, $this->leer($entidad, $id, ['eliminado_en']), $idCambio);
            $this->marcarRevertido($idCambio);
            return ['ok' => true, 'mensaje' => "Se deshizo el alta: quedó dado de baja", 'conflictos' => []];
        }

        $actual = $this->leer($entidad, $id, array_keys($cambios));
        $conflictos = [];
        foreach ($cambios as $campo => [$antes, $despues]) {
            if (!self::igual($actual[$campo] ?? null, $despues)) {
                $conflictos[] = "$campo: después de ese cambio se modificó de nuevo (hoy vale «" . self::mostrar($actual[$campo] ?? null)
                              . "», el cambio lo había dejado en «" . self::mostrar($despues) . "»)";
            }
        }
        if ($conflictos && !$forzar) {
            return ['ok' => false, 'mensaje' => 'Hay campos que cambiaron después; no se aplicó nada', 'conflictos' => $conflictos];
        }
        $volver = [];
        foreach ($cambios as $campo => [$antes, $despues]) {
            $volver[$campo] = $antes;
        }
        $this->escribir($entidad, $id, $volver);
        $this->registrar($entidad, $id, 'reversion', $actual, $this->leer($entidad, $id, array_keys($cambios)), $idCambio);
        $this->marcarRevertido($idCambio);
        return ['ok' => true, 'mensaje' => 'Cambio revertido (' . count($cambios) . ' campos)', 'conflictos' => $conflictos];
    }

    /**
     * Deshace todo lo que hizo una importación o un escaneo, del último cambio al primero.
     * Si algún cambio tiene conflicto y no se fuerza, no se aplica nada.
     * @return array{ok:bool, mensaje:string, conflictos:list<string>, revertidos:int}
     */
    public function revertirProceso(int $procesoId, bool $forzar = false): array
    {
        $st = $this->db->prepare('SELECT id FROM auditoria WHERE proceso_id = ? AND revertido_en IS NULL AND accion <> \'reversion\' ORDER BY id DESC');
        $st->execute([$procesoId]);
        $ids = $st->fetchAll(\PDO::FETCH_COLUMN);
        if (!$ids) {
            return ['ok' => false, 'mensaje' => "El proceso $procesoId no tiene cambios para deshacer", 'conflictos' => [], 'revertidos' => 0];
        }
        $propia = !$this->db->inTransaction();
        if ($propia) {
            $this->db->beginTransaction();
        }
        $conflictos = [];
        $n = 0;
        foreach ($ids as $idCambio) {
            $r = $this->revertir((int)$idCambio, $forzar);
            if (!$r['ok']) {
                foreach ($r['conflictos'] ?: [$r['mensaje']] as $x) {
                    $conflictos[] = "cambio $idCambio: $x";
                }
                continue;
            }
            $n++;
        }
        if ($conflictos && !$forzar) {
            if ($propia) {
                $this->db->rollBack();
            }
            return ['ok' => false, 'mensaje' => 'Hay cambios posteriores sobre los mismos datos; no se aplicó nada', 'conflictos' => $conflictos, 'revertidos' => 0];
        }
        if ($propia) {
            $this->db->commit();
        }
        return ['ok' => true, 'mensaje' => "Se deshicieron $n cambios del proceso $procesoId", 'conflictos' => $conflictos, 'revertidos' => $n];
    }

    // ------------------------------------------------------------------
    // Lectura y escritura de campos (incluye listas de términos)
    // ------------------------------------------------------------------

    /** @return array<string,mixed> */
    public function leer(string $entidad, int $id, array $campos): array
    {
        $this->validarCampos($entidad, $campos);
        $simples = array_values(array_diff($campos, array_keys(self::LISTAS)));
        $out = [];
        if ($simples) {
            $st = $this->db->prepare('SELECT ' . implode(',', $simples) . " FROM $entidad WHERE id = ?");
            $st->execute([$id]);
            $out = $st->fetch() ?: [];
        }
        foreach (array_intersect($campos, array_keys(self::LISTAS)) as $campo) {
            $out[$campo] = $this->leerLista($id, $campo);
        }
        return $out;
    }

    /** @param array<string,mixed> $datos */
    private function escribir(string $entidad, int $id, array $datos): void
    {
        $this->validarCampos($entidad, array_keys($datos));
        $simples = array_diff_key($datos, self::LISTAS);
        if ($simples) {
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($simples)));
            $this->db->prepare("UPDATE $entidad SET $set WHERE id = ?")->execute(array_merge(array_values($simples), [$id]));
        }
        foreach (array_intersect_key($datos, self::LISTAS) as $campo => $nombres) {
            $this->escribirLista($id, $campo, (array)$nombres);
        }
    }

    /** @return list<string> */
    public function leerLista(int $registroId, string $campo): array
    {
        $st = $this->db->prepare('SELECT v.nombre FROM registro_vocabulario rv JOIN vocabulario v ON v.id = rv.vocabulario_id
                                  WHERE rv.registro_id = ? AND v.tipo = ? ORDER BY rv.orden, v.nombre');
        $st->execute([$registroId, self::LISTAS[$campo]]);
        return $st->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** @param list<string> $nombres */
    private function escribirLista(int $registroId, string $campo, array $nombres): void
    {
        $tipo = self::LISTAS[$campo];
        $this->db->prepare('DELETE rv FROM registro_vocabulario rv JOIN vocabulario v ON v.id = rv.vocabulario_id
                            WHERE rv.registro_id = ? AND v.tipo = ?')->execute([$registroId, $tipo]);
        $buscar = $this->db->prepare('SELECT COALESCE(fusionado_en, id) FROM vocabulario WHERE tipo = ? AND clave = ?');
        $ins = $this->db->prepare('INSERT IGNORE INTO registro_vocabulario (registro_id, vocabulario_id, orden) VALUES (?,?,?)');
        $orden = 0;
        foreach ($nombres as $n) {
            $buscar->execute([$tipo, Texto::clave($n)]);
            $vid = $buscar->fetchColumn();
            if ($vid === false) {
                throw new \RuntimeException("El término «{$n}» no existe en el vocabulario ($tipo)");
            }
            $ins->execute([$registroId, $vid, ++$orden]);
        }
    }

    private function marcarRevertido(int $idCambio): void
    {
        $this->db->prepare('UPDATE auditoria SET revertido_en = NOW() WHERE id = ?')->execute([$idCambio]);
    }

    private function validarCampos(string $entidad, array $campos): void
    {
        if (!isset(self::CAMPOS[$entidad])) {
            throw new \InvalidArgumentException("Entidad no editable: $entidad");
        }
        $extra = array_diff($campos, self::CAMPOS[$entidad], ['creado_por', 'actualizado_por']);
        if ($extra) {
            throw new \InvalidArgumentException('Campos no editables en ' . $entidad . ': ' . implode(', ', $extra));
        }
    }

    private static function igual(mixed $a, mixed $b): bool
    {
        if (is_array($a) || is_array($b)) {
            return array_values((array)$a) === array_values((array)$b);
        }
        $a = $a === null ? null : (string)$a;
        $b = $b === null ? null : (string)$b;
        return $a === $b;
    }

    private static function mostrar(mixed $v): string
    {
        if ($v === null) {
            return 'vacío';
        }
        if (is_array($v)) {
            return implode(', ', $v);
        }
        $s = (string)$v;
        return mb_strlen($s) > 60 ? mb_substr($s, 0, 60) . '…' : $s;
    }
}
