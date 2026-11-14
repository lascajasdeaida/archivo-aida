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

    public function guardar(\PDO $db): ?int
    {
        if ($this->simulacion) {
            return null;
        }
        $db->prepare('INSERT INTO proceso (tipo, fuente, simulacion, resumen, iniciado_en, terminado_en) VALUES (?,?,0,?,?,NOW())')
           ->execute([$this->tipo, $this->fuente, json_encode($this->cuentas, JSON_UNESCAPED_UNICODE), $this->iniciado]);
        $id = (int)$db->lastInsertId();
        $st = $db->prepare('INSERT INTO incidencia (proceso_id, nivel, codigo, caja, registro, detalle) VALUES (?,?,?,?,?,?)');
        foreach ($this->incidencias as $i) {
            $st->execute([$id, $i['nivel'], $i['codigo'], $i['caja'], $i['registro'], mb_substr($i['detalle'], 0, 1000)]);
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
                $out[] = sprintf('  %s (%d)', $k, count($lista));
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
