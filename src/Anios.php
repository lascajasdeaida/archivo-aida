<?php
declare(strict_types=1);

namespace Aida;

/**
 * Tabla registro_anio: los años a los que responde cada registro (docs/decisiones.md, «Años»).
 * Es lo que usan el filtro por año, rango y década y la faceta de décadas (src/Buscador.php).
 *
 * Se deriva de la columna Año (registro.anio_texto) con Texto::anio(), y nunca contradice el resumen
 * que queda en registro (anio = menor, anio_hasta = mayor, anio_sin_confirmar = alguno dudoso), que es lo
 * auditado en el historial:
 *   - si Texto::anio(anio_texto) da el mismo resumen que la fila, se guardan sus años (la lista real);
 *   - si no (registros viejos sin texto, o un resumen calculado con otras reglas que volvió al deshacer),
 *     se guardan todos los años entre anio y anio_hasta, como antes. bin/recalcular-anios.php los pone al día.
 *
 * La mantienen Historial (al escribir cualquier campo del año, también al deshacer) y el Importador.
 * No se audita aparte: es un derivado del año del registro, que sí queda en el historial.
 */
final class Anios
{
    public const CAMPOS = ['anio', 'anio_hasta', 'anio_sin_confirmar', 'anio_texto'];

    public function __construct(private readonly \PDO $db)
    {
    }

    /**
     * Los años que corresponden a un registro según su fila actual.
     * @param array{anio:mixed, anio_hasta:mixed, anio_sin_confirmar:mixed, anio_texto:mixed} $r
     * @return array<int,bool> año => sin confirmar
     */
    public static function calcular(array $r): array
    {
        if ($r['anio'] === null) {
            return [];
        }
        $desde = (int)$r['anio'];
        $hasta = (int)($r['anio_hasta'] ?? $r['anio']);
        $duda = (bool)(int)$r['anio_sin_confirmar'];
        $an = Texto::anio($r['anio_texto']);
        if ($an['anios'] && $an['desde'] === $desde && $an['hasta'] === $hasta && $an['sin_confirmar'] === $duda) {
            return $an['anios'];
        }
        return $hasta >= $desde ? array_fill_keys(range($desde, $hasta), $duda) : [];
    }

    /** Pone al día los años de un registro. @return bool si cambió algo */
    public function sincronizar(int $registroId): bool
    {
        $st = $this->db->prepare('SELECT anio, anio_hasta, anio_sin_confirmar, anio_texto FROM registro WHERE id = ?');
        $st->execute([$registroId]);
        $r = $st->fetch(\PDO::FETCH_ASSOC);
        $nuevos = $r ? self::calcular($r) : [];
        if ($this->leer($registroId) === $nuevos) {
            return false;
        }
        $this->db->prepare('DELETE FROM registro_anio WHERE registro_id = ?')->execute([$registroId]);
        if ($nuevos) {
            $filas = [];
            $par = [];
            foreach ($nuevos as $a => $d) {
                $filas[] = '(?,?,?)';
                array_push($par, $registroId, $a, (int)$d);
            }
            $this->db->prepare('INSERT INTO registro_anio (registro_id, anio, sin_confirmar) VALUES ' . implode(',', $filas))->execute($par);
        }
        return true;
    }

    /** @return array<int,bool> */
    public function leer(int $registroId): array
    {
        $st = $this->db->prepare('SELECT anio, sin_confirmar FROM registro_anio WHERE registro_id = ? ORDER BY anio');
        $st->execute([$registroId]);
        $out = [];
        foreach ($st->fetchAll(\PDO::FETCH_NUM) as [$a, $d]) {
            $out[(int)$a] = (bool)(int)$d;
        }
        return $out;
    }

    /** Reconstruye la tabla de todos los registros (después de la migración 004). @return int registros que cambiaron */
    public function reconstruir(): int
    {
        $n = 0;
        foreach ($this->db->query('SELECT id FROM registro')->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            $n += (int)$this->sincronizar((int)$id);
        }
        return $n;
    }
}
