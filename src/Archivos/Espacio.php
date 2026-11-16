<?php
declare(strict_types=1);

namespace Aida\Archivos;

/**
 * Informe de espacio de los archivos digitalizados, para dimensionar el hosting propio (decisión del PM, 16/11/2026).
 *
 * Sale de archivo_digital (archivos presentes en el último escaneo, sin quitar, de registros y cajas sin baja), así que
 * vale igual con origen disco o drive. Pesos en unidades decimales (1 GB = 1.000.000.000 bytes), como Html::peso.
 *
 * Proyección, dos maneras (se muestran las dos; la diferencia da una idea del margen):
 *  - por registro: promedio por registro digitalizado × registros de todas las cajas (los cargados, llevados a
 *    `cajas_total` si todavía no están todas las cajas en la base);
 *  - por caja: promedio por caja con archivos × `cajas_total`.
 * Las dos suponen que se digitaliza todo y que lo que falta pesa como la muestra.
 */
final class Espacio
{
    public function __construct(
        private readonly \PDO $db,
        private readonly int $cajasTotal = 70,
        private readonly int $pesado = 100_000_000,
    ) {
    }

    /**
     * @return array{cajas: list<array>, total: array<string,int|float|null>, proyeccion: array<string,int|float|null>, pesado: int}
     */
    public function informe(): array
    {
        $st = $this->db->prepare("SELECT c.numero, c.nombre,
                (SELECT COUNT(*) FROM registro r2 WHERE r2.caja_id = c.id AND r2.eliminado_en IS NULL) registros,
                COUNT(DISTINCT a.registro_id) digitalizados, COUNT(a.id) archivos,
                COALESCE(SUM(a.peso_bytes), 0) bytes, MAX(a.peso_bytes) mayor,
                SUM(a.peso_bytes > ?) pesados, SUM(a.peso_bytes IS NULL) sin_peso
            FROM caja c
            JOIN registro r ON r.caja_id = c.id AND r.eliminado_en IS NULL
            JOIN archivo_digital a ON a.registro_id = r.id AND a.presente = 1 AND a.eliminado_en IS NULL
            WHERE c.eliminado_en IS NULL
            GROUP BY c.id, c.numero, c.nombre ORDER BY c.numero");
        $st->execute([$this->pesado]);
        $cajas = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $c) {
            $cajas[] = [
                'numero' => (int)$c['numero'], 'nombre' => (string)$c['nombre'], 'registros' => (int)$c['registros'],
                'digitalizados' => (int)$c['digitalizados'], 'archivos' => (int)$c['archivos'], 'bytes' => (int)$c['bytes'],
                'mayor' => $c['mayor'] !== null ? (int)$c['mayor'] : null, 'pesados' => (int)$c['pesados'], 'sin_peso' => (int)$c['sin_peso'],
                'promedio' => (int)$c['digitalizados'] ? intdiv((int)$c['bytes'], (int)$c['digitalizados']) : null,
            ];
        }
        $base = $this->db->query('SELECT
                (SELECT COUNT(*) FROM caja WHERE eliminado_en IS NULL) cajas,
                (SELECT COUNT(*) FROM registro r JOIN caja c ON c.id = r.caja_id WHERE r.eliminado_en IS NULL AND c.eliminado_en IS NULL) registros')
            ->fetch(\PDO::FETCH_ASSOC);

        $bytes = array_sum(array_column($cajas, 'bytes'));
        $dig = array_sum(array_column($cajas, 'digitalizados'));
        $arch = array_sum(array_column($cajas, 'archivos'));
        $conArchivos = count($cajas);
        $mayores = array_filter(array_column($cajas, 'mayor'), fn($x) => $x !== null);
        $total = [
            'bytes' => $bytes, 'archivos' => $arch, 'digitalizados' => $dig, 'cajas_con_archivos' => $conArchivos,
            'cajas_base' => (int)$base['cajas'], 'registros_base' => (int)$base['registros'],
            'promedio_registro' => $dig ? intdiv($bytes, $dig) : null,
            'promedio_archivo' => $arch ? intdiv($bytes, $arch) : null,
            'promedio_caja' => $conArchivos ? intdiv($bytes, $conArchivos) : null,
            'mayor' => $mayores ? max($mayores) : null,
            'pesados' => array_sum(array_column($cajas, 'pesados')),
            'sin_peso' => array_sum(array_column($cajas, 'sin_peso')),
        ];

        // Registros de todas las cajas: los de la base, llevados a cajas_total si faltan cajas por cargar
        $cajasBase = max(1, (int)$base['cajas']);
        $registrosTodas = (int)$base['registros'] && $cajasBase < $this->cajasTotal
            ? (int)round((int)$base['registros'] / $cajasBase * $this->cajasTotal) : (int)$base['registros'];
        $cajasTodas = max($this->cajasTotal, $cajasBase);
        $porRegistro = $total['promedio_registro'] !== null ? $total['promedio_registro'] * $registrosTodas : null;
        $porCaja = $total['promedio_caja'] !== null ? $total['promedio_caja'] * $cajasTodas : null;
        $proyeccion = [
            'cajas' => $cajasTodas, 'registros' => $registrosTodas,
            'por_registro' => $porRegistro, 'por_caja' => $porCaja,
            'desde' => $porRegistro !== null && $porCaja !== null ? min($porRegistro, $porCaja) : ($porRegistro ?? $porCaja),
            'hasta' => $porRegistro !== null && $porCaja !== null ? max($porRegistro, $porCaja) : ($porRegistro ?? $porCaja),
            'avance' => $registrosTodas ? round($dig / $registrosTodas * 100, 1) : null,
        ];
        return ['cajas' => $cajas, 'total' => $total, 'proyeccion' => $proyeccion, 'pesado' => $this->pesado];
    }

    /** “16,0 GB”, “109 MB”. */
    public static function gb(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }
        if ($bytes >= 1_000_000_000) {
            $g = $bytes / 1e9;
            return number_format($g, $g < 100 ? 1 : 0, ',', '.') . ' GB';
        }
        $m = $bytes / 1e6;
        return number_format($m, $m < 10 ? 1 : 0, ',', '.') . ' MB';
    }
}
