<?php
declare(strict_types=1);

namespace Aida;

/** Junta contadores e incidencias de un proceso y, si no es simulación, los guarda en la base. */
final class Reporte
{
    /** @var array<string,int> */
    public array $cuentas = [];
    /** @var list<array{nivel:string,codigo:string,caja:?int,registro:?int,detalle:string}> */
    public array $incidencias = [];

    public function __construct(
        public readonly string $tipo,
        public readonly ?string $fuente,
        public readonly bool $simulacion,
        private readonly string $iniciado = '',
    ) {
    }

    public static function iniciar(string $tipo, ?string $fuente, bool $simulacion): self
    {
        return new self($tipo, $fuente, $simulacion, date('Y-m-d H:i:s'));
    }

    public function sumar(string $clave, int $n = 1): void
    {
        $this->cuentas[$clave] = ($this->cuentas[$clave] ?? 0) + $n;
    }

    public function incidencia(string $nivel, string $codigo, ?int $caja, ?int $registro, string $detalle): void
    {
        $this->incidencias[] = compact('nivel', 'codigo', 'caja', 'registro', 'detalle');
    }

    /** @var int[] cajas alcanzadas por este proceso (para cerrar solas las alertas que ya no ocurren) */
    public array $cajasAlcanzadas = [];
    public bool $alcanceTotal = false;

    /**
     * Guarda el proceso y sus incidencias.
     * - Si una incidencia igual ya está abierta, no se duplica: se vincula al proceso nuevo.
     * - Las incidencias abiertas del mismo tipo de proceso que ya no se repiten (dentro del alcance
     *   de esta corrida) se cierran solas, con la nota «Ya no ocurre».
     */
    public ?int $procesoId = null;

    /** Crea el proceso al empezar, para que los cambios del historial queden asociados a él. */
    public function abrir(\PDO $db, ?int $usuarioId = null): int
    {
        $db->prepare('INSERT INTO proceso (tipo, fuente, simulacion, usuario_id, iniciado_en) VALUES (?,?,?,?,?)')
           ->execute([$this->tipo, $this->fuente, (int)$this->simulacion, $usuarioId, $this->iniciado]);
        return $this->procesoId = (int)$db->lastInsertId();
    }

    public function guardar(\PDO $db): ?int
    {
        if ($this->simulacion) {
            return null;
        }
        if ($this->procesoId === null) {
            $this->abrir($db);
        }
        $id = $this->procesoId;
        $db->prepare('UPDATE proceso SET resumen = ?, terminado_en = NOW() WHERE id = ?')
           ->execute([json_encode($this->cuentas, JSON_UNESCAPED_UNICODE), $id]);

        $abiertas = $db->prepare('SELECT i.id, i.codigo, i.caja, i.registro, i.detalle FROM incidencia i
            JOIN proceso p ON p.id = i.proceso_id WHERE i.resuelta = 0 AND p.tipo = ?');
        $abiertas->execute([$this->tipo]);
        $porClave = [];
        foreach ($abiertas->fetchAll() as $a) {
            $porClave[$a['codigo'] . '|' . $a['caja'] . '|' . $a['registro'] . '|' . $a['detalle']] = $a;
        }

        $ins = $db->prepare('INSERT INTO incidencia (proceso_id, nivel, codigo, caja, registro, detalle, requiere_validacion)
                             VALUES (?,?,?,?,?,?,?)');
        $mover = $db->prepare('UPDATE incidencia SET proceso_id = ? WHERE id = ?');
        $vigentes = [];
        foreach ($this->incidencias as $i) {
            $det = mb_substr($i['detalle'], 0, 1000);
            $k = $i['codigo'] . '|' . $i['caja'] . '|' . $i['registro'] . '|' . $det;
            if (isset($porClave[$k])) {
                $mover->execute([$id, $porClave[$k]['id']]);
                $vigentes[$k] = true;
                continue;
            }
            $ins->execute([$id, $i['nivel'], $i['codigo'], $i['caja'], $i['registro'], $det,
                           (int)Alertas::requiereValidacion($i['codigo'], $i['nivel'])]);
            $vigentes[$k] = true;
        }

        $cerrar = $db->prepare("UPDATE incidencia SET resuelta = 1, resuelta_en = NOW(), resolucion = 'Ya no ocurre' WHERE id = ?");
        foreach ($porClave as $k => $a) {
            if (isset($vigentes[$k])) {
                continue;
            }
            if ($this->alcanceTotal || ($a['caja'] !== null && in_array((int)$a['caja'], $this->cajasAlcanzadas, true))) {
                $cerrar->execute([$a['id']]);
                $this->sumar('alertas que se cerraron solas');
            }
        }
        return $id;
    }

    /** Resumen legible para consola. */
    public function texto(): string
    {
        $out = [];
        $out[] = strtoupper($this->tipo) . ($this->simulacion ? ' (SIMULACIÓN: no se guardó nada)' : '');
        foreach ($this->cuentas as $k => $v) {
            $out[] = sprintf('  %-34s %6d', $k, $v);
        }
        if ($this->incidencias) {
            $porCodigo = [];
            foreach ($this->incidencias as $i) {
                $porCodigo[$i['nivel'] . ' ' . $i['codigo']][] = $i;
            }
            ksort($porCodigo);
            $out[] = '';
            $out[] = 'Incidencias:';
            foreach ($porCodigo as $k => $lista) {
                $al = Alertas::de($lista[0]['codigo']);
                $out[] = sprintf('  %s (%d)%s — %s', $k, count($lista),
                    Alertas::requiereValidacion($lista[0]['codigo'], $lista[0]['nivel']) ? ' [VALIDAR]' : '', $al['titulo']);
                foreach (array_slice($lista, 0, 8) as $i) {
                    $donde = $i['caja'] !== null ? 'C' . $i['caja'] . ($i['registro'] !== null ? 'R' . $i['registro'] : '') : '';
                    $out[] = sprintf('    %-8s %s', $donde, $i['detalle']);
                }
                if (count($lista) > 8) {
                    $out[] = '    … y ' . (count($lista) - 8) . ' más';
                }
            }
        }
        return implode("\n", $out) . "\n";
    }
}
