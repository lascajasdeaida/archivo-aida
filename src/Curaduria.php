<?php
declare(strict_types=1);

namespace Aida;

/**
 * Curaduría de vocabularios: mover un término al otro vocabulario, fusionar, renombrar y descartar.
 *
 * Cada operación es un `proceso` de tipo 'curaduria' (quién, cuándo, motivo, registros afectados) y todos sus
 * cambios quedan en `auditoria` con ese proceso: los de cada registro (sus listas de tipos y descriptores,
 * sin bloquearlas para el Excel) y los de los términos. Así se deshace completa con Historial::revertirProceso
 * y aparece en el informe «Cambios de curaduría» (Vocabularios).
 *
 * Orden en el historial: primero los registros, después los términos. Deshacer va del último al primero, así
 * que primero vuelve el término (deja de estar movido o descartado) y después cada registro recupera su lista.
 *
 * La importación respeta lo curado sin bloquear nada (Aida\Vocabulario resuelve cada valor del Excel al término
 * vigente), por eso la próxima planilla no vuelve a crear lo que se movió, fusionó, renombró o descartó.
 */
final class Curaduria
{
    public const ACCIONES = ['mover' => 'Movido', 'fusionar' => 'Fusionado', 'renombrar' => 'Renombrado', 'descartar' => 'Descartado'];

    private Vocabulario $voc;
    /** @var array<int,string> proceso => acción en curso */
    private array $acciones = [];

    public function __construct(private readonly \PDO $db, private readonly ?int $usuarioId)
    {
        $this->voc = new Vocabulario($db);
    }

    // ------------------------------------------------------------------
    // Lo que va a cambiar (para la pantalla de confirmación)
    // ------------------------------------------------------------------

    /** @return array{termino:array, registros:list<array{id:int,codigo:string}>, solo_este:list<string>} */
    public function impacto(int $id): array
    {
        $t = $this->activo($id);
        $st = $this->db->prepare("SELECT r.id, r.codigo,
              (SELECT COUNT(*) FROM registro_vocabulario rv2 JOIN vocabulario v2 ON v2.id = rv2.vocabulario_id
                WHERE rv2.registro_id = r.id AND v2.tipo = ?) del_tipo
            FROM registro_vocabulario rv JOIN registro r ON r.id = rv.registro_id
            WHERE rv.vocabulario_id = ? ORDER BY r.caja_id, r.numero");
        $st->execute([$t['tipo'], $id]);
        $regs = [];
        $solo = [];
        foreach ($st->fetchAll() as $r) {
            $regs[] = ['id' => (int)$r['id'], 'codigo' => $r['codigo']];
            if ((int)$r['del_tipo'] === 1) {
                $solo[] = $r['codigo'];
            }
        }
        usort($regs, fn($a, $b) => self::ordenCodigo($a['codigo'], $b['codigo']));
        usort($solo, [self::class, 'ordenCodigo']);
        return ['termino' => $t, 'registros' => $regs, 'solo_este' => $solo];
    }

    /**
     * Términos vigentes de un vocabulario donde se puede mover o fusionar $id, con cuántos de sus registros
     * ya los tienen y si son equivalentes (misma grafía o casi: sin tildes, signos ni plural).
     * @return list<array{id:int,nombre:string,estado:string,usos:int,comparten:int,equivalente:bool}>
     */
    public function destinos(int $id, string $tipo): array
    {
        $t = $this->activo($id);
        $st = $this->db->prepare("SELECT v.id, v.nombre, v.clave, v.estado,
              (SELECT COUNT(*) FROM registro_vocabulario rv WHERE rv.vocabulario_id = v.id) usos,
              (SELECT COUNT(*) FROM registro_vocabulario rv JOIN registro_vocabulario ro ON ro.registro_id = rv.registro_id
                 WHERE rv.vocabulario_id = v.id AND ro.vocabulario_id = ?) comparten
            FROM vocabulario v WHERE v.tipo = ? AND v.id <> ? AND v.fusionado_en IS NULL AND v.eliminado_en IS NULL
              AND v.estado <> 'descartado' ORDER BY v.nombre");
        $st->execute([$id, $tipo, $id]);
        $flex = Texto::claveFlexible($t['nombre']);
        $out = [];
        foreach ($st->fetchAll() as $d) {
            $out[] = ['id' => (int)$d['id'], 'nombre' => $d['nombre'], 'estado' => $d['estado'], 'usos' => (int)$d['usos'],
                      'comparten' => (int)$d['comparten'],
                      'equivalente' => $d['clave'] === $t['clave'] || Texto::claveFlexible($d['nombre']) === $flex];
        }
        usort($out, fn($a, $b) => [$b['equivalente'], $a['nombre']] <=> [$a['equivalente'], $b['nombre']]);
        return $out;
    }

    // ------------------------------------------------------------------
    // Operaciones
    // ------------------------------------------------------------------

    /**
     * Mueve un término al otro vocabulario. Con $destinoId lo fusiona en ese término; sin destino crea uno
     * con el mismo nombre (si ya existe uno equivalente hay que elegirlo).
     * @return array{proceso:int, registros:int, destino:array}
     */
    public function mover(int $id, ?int $destinoId, ?string $motivo): array
    {
        $o = $this->activo($id);
        $tipoDest = Vocabulario::otro($o['tipo']);
        return $this->enTransaccion(function () use ($o, $tipoDest, $destinoId, $motivo) {
            $creado = false;
            $revivido = null;
            if ($destinoId) {
                $d = $this->activo($destinoId);
                if ($d['tipo'] !== $tipoDest) {
                    throw new \RuntimeException('El destino tiene que ser del otro vocabulario.');
                }
            } else {
                $st = $this->db->prepare('SELECT * FROM vocabulario WHERE tipo = ? AND clave = ?');
                $st->execute([$tipoDest, $o['clave']]);
                $d = $st->fetch() ?: null;
                if ($d && $d['eliminado_en'] === null) {
                    throw new \RuntimeException("Ya existe «{$d['nombre']}» en " . Vocabulario::PLURALES[$tipoDest] . ': elegilo como destino.');
                }
                if ($d) {
                    $revivido = $this->leer((int)$d['id']);
                    $this->db->prepare("UPDATE vocabulario SET eliminado_en = NULL, eliminado_por = NULL, estado = 'aprobado',
                        fusionado_en = NULL, regla = NULL WHERE id = ?")->execute([$d['id']]);
                } else {
                    $this->db->prepare("INSERT INTO vocabulario (tipo, nombre, clave, estado) VALUES (?,?,?,'aprobado')")
                             ->execute([$tipoDest, $o['nombre'], $o['clave']]);
                    $creado = true;
                }
                $d = $this->voc->fila($destinoId = $creado ? (int)$this->db->lastInsertId() : (int)$d['id']);
            }
            $pid = $this->abrir('mover', $o, $d, $motivo);
            $h = $this->historial($pid);
            $imp = $this->impacto((int)$o['id']);
            $antesO = $this->leer((int)$o['id']);
            $regs = $this->reasignar((int)$o['id'], (int)$d['id'], false);
            $this->db->prepare("UPDATE vocabulario SET fusionado_en = ?, regla = 'movido', motivo = ? WHERE id = ?")
                     ->execute([$d['id'], $this->motivo($motivo), $o['id']]);
            $this->registrarRegistros($h, $regs);
            if ($creado) {
                $h->registrar('vocabulario', (int)$d['id'], 'alta', [], $this->leer((int)$d['id']));
            } elseif ($revivido !== null) {
                $h->registrar('vocabulario', (int)$d['id'], 'restauracion', $revivido, $this->leer((int)$d['id']));
            }
            $h->registrar('vocabulario', (int)$o['id'], 'modificacion', $antesO, $this->leer((int)$o['id']));
            $aprobado = !$creado && $revivido === null && $this->aprobarDestino($h, $d);
            $this->cerrar($pid, $o, $d, $motivo, $regs, ['solo_este' => $imp['solo_este'], 'creado' => $creado, 'destino_aprobado' => $aprobado]);
            return ['proceso' => $pid, 'registros' => count($regs), 'destino' => $d, 'aprobado' => $aprobado];
        });
    }

    /** Fusiona un término en otro del mismo vocabulario. */
    public function fusionar(int $id, int $destinoId, ?string $motivo): array
    {
        $o = $this->activo($id);
        $d = $this->activo($destinoId);
        if ($d['tipo'] !== $o['tipo']) {
            throw new \RuntimeException('Para pasar un término al otro vocabulario usá «Mover».');
        }
        if ((int)$d['id'] === (int)$o['id']) {
            throw new \RuntimeException('Elegí otro término como destino de la fusión.');
        }
        return $this->enTransaccion(function () use ($o, $d, $motivo) {
            $pid = $this->abrir('fusionar', $o, $d, $motivo);
            $h = $this->historial($pid);
            $antesO = $this->leer((int)$o['id']);
            $regs = $this->reasignar((int)$o['id'], (int)$d['id'], true);
            $this->db->prepare("UPDATE vocabulario SET fusionado_en = ?, regla = 'fusion', motivo = ? WHERE id = ?")
                     ->execute([$d['id'], $this->motivo($motivo), $o['id']]);
            $this->registrarRegistros($h, $regs);
            $h->registrar('vocabulario', (int)$o['id'], 'modificacion', $antesO, $this->leer((int)$o['id']));
            $aprobado = $this->aprobarDestino($h, $d);
            $this->cerrar($pid, $o, $d, $motivo, $regs, ['destino_aprobado' => $aprobado]);
            return ['proceso' => $pid, 'registros' => count($regs), 'destino' => $d, 'aprobado' => $aprobado];
        });
    }

    /**
     * Renombra un término. Si cambia la grafía (no solo tildes o mayúsculas), la anterior queda como alias:
     * si el Excel la vuelve a traer, se carga el término con el nombre nuevo.
     */
    public function renombrar(int $id, string $nombre, ?string $motivo): array
    {
        $t = $this->activo($id);
        $nombre = trim((string)preg_replace('/\s+/u', ' ', $nombre));
        if ($nombre === '' || mb_strlen($nombre) > 120) {
            throw new \RuntimeException('El nombre tiene que tener entre 1 y 120 caracteres.');
        }
        if ($nombre === $t['nombre']) {
            throw new \RuntimeException('El nombre es el mismo.');
        }
        $kNueva = Texto::clave($nombre);
        $aliasNueva = null;
        if ($kNueva !== $t['clave']) {
            $st = $this->db->prepare('SELECT nombre FROM vocabulario WHERE tipo = ? AND clave = ? AND id <> ?');
            $st->execute([$t['tipo'], $kNueva, $t['id']]);
            if (($otro = $st->fetchColumn()) !== false) {
                throw new \RuntimeException("Ya existe «{$otro}» en este vocabulario: para juntarlos usá «Fusionar».");
            }
            $st = $this->db->prepare('SELECT a.*, v.nombre termino FROM vocabulario_alias a JOIN vocabulario v ON v.id = a.vocabulario_id
                                      WHERE a.tipo = ? AND a.clave = ?');
            $st->execute([$t['tipo'], $kNueva]);
            $aliasNueva = $st->fetch() ?: null;
            if ($aliasNueva && $aliasNueva['eliminado_en'] === null && (int)$aliasNueva['vocabulario_id'] !== (int)$t['id']) {
                throw new \RuntimeException("«{$nombre}» es una grafía anterior de «{$aliasNueva['termino']}»: para juntarlos usá «Fusionar».");
            }
        }
        return $this->enTransaccion(function () use ($t, $nombre, $kNueva, $aliasNueva, $motivo) {
            $pid = $this->abrir('renombrar', $t, ['nombre' => $nombre, 'tipo' => $t['tipo'], 'id' => $t['id']], $motivo);
            $h = $this->historial($pid);
            $antes = $this->leer((int)$t['id']);
            $this->db->prepare('UPDATE vocabulario SET nombre = ?, clave = ?, motivo = ? WHERE id = ?')
                     ->execute([$nombre, $kNueva, $this->motivo($motivo), $t['id']]);
            $h->registrar('vocabulario', (int)$t['id'], 'modificacion', $antes, $this->leer((int)$t['id']));
            if ($kNueva !== $t['clave']) {
                // La grafía anterior se sigue reconociendo
                $st = $this->db->prepare('SELECT id FROM vocabulario_alias WHERE tipo = ? AND clave = ?');
                $st->execute([$t['tipo'], $t['clave']]);
                if (($aid = $st->fetchColumn()) !== false) {
                    $a0 = $this->leerAlias((int)$aid);
                    $this->db->prepare('UPDATE vocabulario_alias SET nombre = ?, vocabulario_id = ?, eliminado_en = NULL, eliminado_por = NULL WHERE id = ?')
                             ->execute([$t['nombre'], $t['id'], $aid]);
                    $h->registrar('vocabulario_alias', (int)$aid, 'restauracion', $a0, $this->leerAlias((int)$aid));
                } else {
                    $this->db->prepare('INSERT INTO vocabulario_alias (tipo, clave, nombre, vocabulario_id) VALUES (?,?,?,?)')
                             ->execute([$t['tipo'], $t['clave'], $t['nombre'], $t['id']]);
                    $aid = (int)$this->db->lastInsertId();
                    $h->registrar('vocabulario_alias', $aid, 'alta', [], $this->leerAlias($aid));
                }
                // Si el nombre nuevo era una grafía anterior de este mismo término, deja de ser alias
                if ($aliasNueva && $aliasNueva['eliminado_en'] === null) {
                    $a0 = $this->leerAlias((int)$aliasNueva['id']);
                    $this->db->prepare('UPDATE vocabulario_alias SET eliminado_en = NOW(), eliminado_por = ? WHERE id = ?')
                             ->execute([$this->usuarioId, $aliasNueva['id']]);
                    $h->registrar('vocabulario_alias', (int)$aliasNueva['id'], 'baja', $a0, $this->leerAlias((int)$aliasNueva['id']));
                }
            }
            $usos = $this->db->prepare('SELECT r.codigo FROM registro_vocabulario rv JOIN registro r ON r.id = rv.registro_id WHERE rv.vocabulario_id = ?');
            $usos->execute([$t['id']]);
            $codigos = $usos->fetchAll(\PDO::FETCH_COLUMN);
            $this->cerrar($pid, $t, ['nombre' => $nombre, 'tipo' => $t['tipo'], 'id' => $t['id']], $motivo,
                          array_map(fn($c) => ['codigo' => $c], $codigos));
            return ['proceso' => $pid, 'registros' => count($codigos)];
        });
    }

    /**
     * Descarta un término basura: se quita de sus registros (sin bloquear sus otros tipos y descriptores),
     * deja de ofrecerse y la importación lo ignora y lo informa.
     */
    public function descartar(int $id, string $motivo): array
    {
        $t = $this->activo($id);
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new \RuntimeException('Para descartar un término hay que escribir el motivo.');
        }
        return $this->enTransaccion(function () use ($t, $motivo) {
            $imp = $this->impacto((int)$t['id']);
            $pid = $this->abrir('descartar', $t, null, $motivo);
            $h = $this->historial($pid);
            $antes = $this->leer((int)$t['id']);
            $regs = $this->reasignar((int)$t['id'], null, false);
            $this->db->prepare("UPDATE vocabulario SET estado = 'descartado', motivo = ? WHERE id = ?")
                     ->execute([$this->motivo($motivo), $t['id']]);
            $this->registrarRegistros($h, $regs);
            $h->registrar('vocabulario', (int)$t['id'], 'modificacion', $antes, $this->leer((int)$t['id']));
            $this->cerrar($pid, $t, null, $motivo, $regs, ['solo_este' => $imp['solo_este']]);
            return ['proceso' => $pid, 'registros' => count($regs)];
        });
    }

    /** Restaura un término descartado deshaciendo su descarte (vuelve a sus registros). */
    public function restaurar(int $id, bool $forzar = false): array
    {
        $st = $this->db->prepare("SELECT id, resumen FROM proceso WHERE tipo = 'curaduria' ORDER BY id DESC");
        $st->execute();
        foreach ($st->fetchAll() as $p) {
            $r = json_decode((string)$p['resumen'], true) ?: [];
            if (($r['accion'] ?? '') === 'descartar' && (int)($r['termino_id'] ?? 0) === $id && empty($r['deshecho'])) {
                return $this->deshacer((int)$p['id'], $forzar) + ['proceso' => (int)$p['id']];
            }
        }
        // Descartado sin proceso (no debería pasar): se reactiva como propuesto
        return $this->enTransaccion(function () use ($id) {
            $h = $this->historial(null);
            $antes = $this->leer($id);
            $this->db->prepare("UPDATE vocabulario SET estado = 'propuesto' WHERE id = ? AND estado = 'descartado'")->execute([$id]);
            $h->registrar('vocabulario', $id, 'restauracion', $antes, $this->leer($id));
            return ['ok' => true, 'mensaje' => 'Término restaurado como propuesto. La próxima importación lo vuelve a asignar donde lo traiga la planilla.', 'conflictos' => []];
        });
    }

    /**
     * Deshace una operación de curaduría completa. Si algún registro o término cambió después, no se aplica
     * nada y se devuelven los conflictos (con $forzar se aplica igual).
     * @return array{ok:bool, mensaje:string, conflictos:list<string>}
     */
    public function deshacer(int $procesoId, bool $forzar = false): array
    {
        $st = $this->db->prepare("SELECT * FROM proceso WHERE id = ? AND tipo = 'curaduria'");
        $st->execute([$procesoId]);
        $p = $st->fetch();
        if (!$p) {
            return ['ok' => false, 'mensaje' => 'No existe ese cambio de curaduría.', 'conflictos' => []];
        }
        $r = json_decode((string)$p['resumen'], true) ?: [];
        if (!empty($r['deshecho'])) {
            return ['ok' => false, 'mensaje' => 'Ese cambio ya se deshizo.', 'conflictos' => []];
        }
        $propia = !$this->db->inTransaction();
        if ($propia) {
            $this->db->beginTransaction();
        }
        try {
            $res = (new Historial($this->db, $this->usuarioId, 'backoffice'))->revertirProceso($procesoId, $forzar);
            if ($res['ok']) {
                $r['deshecho'] = ['en' => date('Y-m-d H:i:s'), 'usuario_id' => $this->usuarioId];
                $this->db->prepare('UPDATE proceso SET resumen = ? WHERE id = ?')
                         ->execute([json_encode($r, JSON_UNESCAPED_UNICODE), $procesoId]);
                $res['mensaje'] = 'Se deshizo: ' . self::describir($r) . '.';
            }
            if ($propia) {
                $res['ok'] ? $this->db->commit() : $this->db->rollBack();
            }
            return $res;
        } catch (\Throwable $e) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // Informe
    // ------------------------------------------------------------------

    /** @return list<array<string,mixed>> operaciones, de la más nueva a la más vieja */
    public function informe(): array
    {
        $rows = $this->db->query("SELECT p.id, p.iniciado_en, p.resumen, u.nombre usuario, u.email
            FROM proceso p LEFT JOIN usuario u ON u.id = p.usuario_id WHERE p.tipo = 'curaduria' ORDER BY p.id DESC")->fetchAll();
        $nombres = [];
        foreach ($this->db->query('SELECT id, COALESCE(nombre, email) n FROM usuario') as $u) {
            $nombres[(int)$u['id']] = $u['n'];
        }
        $out = [];
        foreach ($rows as $p) {
            $r = json_decode((string)$p['resumen'], true) ?: [];
            $out[] = [
                'id' => (int)$p['id'], 'fecha' => $p['iniciado_en'], 'usuario' => $p['usuario'] ?: $p['email'] ?: '—',
                'accion' => $r['accion'] ?? '', 'accion_txt' => self::ACCIONES[$r['accion'] ?? ''] ?? ($r['accion'] ?? ''),
                'tipo' => $r['tipo'] ?? '', 'termino' => $r['termino'] ?? '', 'destino' => $r['destino'] ?? null,
                'destino_tipo' => $r['destino_tipo'] ?? null, 'motivo' => $r['motivo'] ?? null,
                'registros' => (int)($r['registros'] ?? 0), 'codigos' => $r['codigos'] ?? [], 'solo_este' => $r['solo_este'] ?? [],
                'descripcion' => self::describir($r),
                'deshecho' => !empty($r['deshecho']) ? ['en' => $r['deshecho']['en'], 'usuario' => $nombres[(int)($r['deshecho']['usuario_id'] ?? 0)] ?? '—'] : null,
            ];
        }
        return $out;
    }

    /** Frase corta: «tipo de documento «Televisión» → descriptor «Televisión»». */
    public static function describir(array $r): string
    {
        $tipo = Vocabulario::NOMBRES[$r['tipo'] ?? ''] ?? '';
        $t = "{$tipo} «" . ($r['termino'] ?? '') . '»';
        return match ($r['accion'] ?? '') {
            'mover' => "$t movido a " . (Vocabulario::NOMBRES[$r['destino_tipo'] ?? ''] ?? '') . ' «' . ($r['destino'] ?? '') . '»',
            'fusionar' => "$t fusionado en «" . ($r['destino'] ?? '') . '»',
            'renombrar' => "$t renombrado «" . ($r['destino'] ?? '') . '»',
            'descartar' => "$t descartado",
            default => $t,
        };
    }

    /** Evita que una planilla interprete como fórmula una celda que empieza con = + - @ (inyección CSV). */
    private static function celda(mixed $v): mixed
    {
        return is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false ? "'" . $v : $v;
    }

    /** CSV (separado por «;», UTF-8 con BOM, como lo abre Excel en castellano). */
    public function csv(): string
    {
        $f = fopen('php://temp', 'r+');
        fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, ['N.º', 'Fecha', 'Usuario', 'Acción', 'Vocabulario', 'Término', 'Destino', 'Vocabulario destino', 'Motivo',
                     'Registros afectados', 'Códigos', 'Quedan sin tipo/descriptor', 'Estado'], ';');
        foreach ($this->informe() as $x) {
            fputcsv($f, array_map([self::class, 'celda'], [$x['id'], date('d/m/Y H:i', strtotime((string)$x['fecha'])), $x['usuario'], $x['accion_txt'],
                Vocabulario::NOMBRES[$x['tipo']] ?? $x['tipo'], $x['termino'], $x['destino'] ?? '',
                $x['destino_tipo'] ? Vocabulario::NOMBRES[$x['destino_tipo']] : '', $x['motivo'] ?? '', $x['registros'],
                implode(', ', $x['codigos']), implode(', ', $x['solo_este']),
                $x['deshecho'] ? 'Deshecho el ' . date('d/m/Y H:i', strtotime($x['deshecho']['en'])) . ' por ' . $x['deshecho']['usuario'] : 'Vigente']), ';');
        }
        rewind($f);
        return (string)stream_get_contents($f);
    }

    /** Texto plano para pegar en un mail al equipo de digitalización o al cliente. */
    public function texto(bool $soloVigentes = true): string
    {
        $l = ['Cambios de curaduría de vocabularios — Las cajas de Aída (' . date('d/m/Y') . ')', ''];
        foreach (array_reverse($this->informe()) as $x) {
            if ($soloVigentes && $x['deshecho']) {
                continue;
            }
            $l[] = '- ' . date('d/m/Y', strtotime((string)$x['fecha'])) . ' · ' . ucfirst($x['descripcion'])
                 . ' · ' . ($x['registros'] === 1 ? '1 registro' : "{$x['registros']} registros")
                 . ($x['motivo'] ? ' · Motivo: ' . $x['motivo'] : '') . ' · ' . $x['usuario']
                 . ($x['deshecho'] ? ' · DESHECHO el ' . date('d/m/Y', strtotime($x['deshecho']['en'])) : '');
        }
        $l[] = '';
        $l[] = 'En la planilla conviene usar el término vigente. Si llega la grafía anterior, la importación la corrige sola y lo informa.';
        return implode("\n", $l) . "\n";
    }

    // ------------------------------------------------------------------
    // Auxiliares
    // ------------------------------------------------------------------

    /** Término vigente (ni fusionado, ni movido, ni descartado, ni dado de baja). */
    private function activo(int $id): array
    {
        $t = $this->voc->fila($id);
        if (!$t || $t['eliminado_en'] !== null) {
            throw new \RuntimeException('No existe ese término.');
        }
        if ($t['fusionado_en'] !== null) {
            throw new \RuntimeException("«{$t['nombre']}» ya está " . ($t['regla'] === 'movido' ? 'movido' : 'fusionado') . ': deshacé ese cambio primero.');
        }
        if ($t['estado'] === 'descartado') {
            throw new \RuntimeException("«{$t['nombre']}» está descartado: restauralo primero.");
        }
        return $t;
    }

    /**
     * Pasa los registros de $origen a $destino (o los quita, si no hay destino).
     * $mismoOrden: el destino toma la posición del origen (fusión); si no, va al final de la lista (movido,
     * igual que lo carga la importación).
     * @return list<array{id:int,codigo:string,antes:array}>
     */
    private function reasignar(int $origen, ?int $destino, bool $mismoOrden): array
    {
        $st = $this->db->prepare('SELECT rv.registro_id, rv.orden, r.codigo FROM registro_vocabulario rv JOIN registro r ON r.id = rv.registro_id
                                  WHERE rv.vocabulario_id = ? ORDER BY r.caja_id, r.numero');
        $st->execute([$origen]);
        $filas = $st->fetchAll();
        $h = new Historial($this->db);
        $out = [];
        $tipoDest = $destino ? $this->voc->fila($destino)['tipo'] : null;
        $tiene = $this->db->prepare('SELECT 1 FROM registro_vocabulario WHERE registro_id = ? AND vocabulario_id = ?');
        $max = $this->db->prepare('SELECT COALESCE(MAX(rv.orden), 0) FROM registro_vocabulario rv JOIN vocabulario v ON v.id = rv.vocabulario_id
                                   WHERE rv.registro_id = ? AND v.tipo = ?');
        $ins = $this->db->prepare('INSERT INTO registro_vocabulario (registro_id, vocabulario_id, orden) VALUES (?,?,?)');
        foreach ($filas as $f) {
            $rid = (int)$f['registro_id'];
            $out[] = ['id' => $rid, 'codigo' => $f['codigo'], 'antes' => $h->leer('registro', $rid, ['tipos', 'descriptores'])];
            if ($destino) {
                $tiene->execute([$rid, $destino]);
                if (!$tiene->fetchColumn()) {
                    if ($mismoOrden) {
                        $orden = (int)$f['orden'];
                    } else {
                        $max->execute([$rid, $tipoDest]);
                        $orden = (int)$max->fetchColumn() + 1;
                    }
                    $ins->execute([$rid, $destino, min(255, $orden)]);
                }
            }
        }
        $this->db->prepare('DELETE FROM registro_vocabulario WHERE vocabulario_id = ?')->execute([$origen]);
        return $out;
    }

    /**
     * Decisión del PM (15/11/2026): fusionar o mover en un término «propuesto» lo aprueba, porque elegirlo como
     * destino ya es curarlo. Queda en el historial dentro del mismo proceso (se deshace con la operación).
     */
    private function aprobarDestino(Historial $h, array $d): bool
    {
        $st = $this->db->prepare('SELECT estado FROM vocabulario WHERE id = ?');
        $st->execute([$d['id']]);
        if ($st->fetchColumn() !== 'propuesto') {
            return false;
        }
        $h->actualizar('vocabulario', (int)$d['id'], ['estado' => 'aprobado'], false);
        return true;
    }

    /** @param list<array{id:int,codigo:string,antes:array}> $regs */
    private function registrarRegistros(Historial $h, array $regs): void
    {
        foreach ($regs as $r) {
            $h->registrar('registro', $r['id'], 'modificacion', $r['antes'], $h->leer('registro', $r['id'], ['tipos', 'descriptores']));
        }
    }

    private function abrir(string $accion, array $t, ?array $d, ?string $motivo): int
    {
        $this->db->prepare("INSERT INTO proceso (tipo, fuente, simulacion, usuario_id, iniciado_en) VALUES ('curaduria', ?, 0, ?, NOW())")
                 ->execute([mb_substr(self::describir(['accion' => $accion, 'tipo' => $t['tipo'], 'termino' => $t['nombre'],
                     'destino' => $d['nombre'] ?? null, 'destino_tipo' => $d['tipo'] ?? null]), 0, 500), $this->usuarioId]);
        $pid = (int)$this->db->lastInsertId();
        $this->acciones[$pid] = $accion;
        return $pid;
    }

    private function cerrar(int $pid, array $t, ?array $d, ?string $motivo, array $regs, array $extra = []): void
    {
        $codigos = array_column($regs, 'codigo');
        usort($codigos, [self::class, 'ordenCodigo']);
        $resumen = [
            'accion' => $this->acciones[$pid],
            'tipo' => $t['tipo'], 'termino' => $t['nombre'], 'termino_id' => (int)$t['id'],
            'destino' => $d['nombre'] ?? null, 'destino_tipo' => $d['tipo'] ?? null, 'destino_id' => isset($d['id']) ? (int)$d['id'] : null,
            'motivo' => $this->motivo($motivo), 'registros' => count($codigos), 'codigos' => $codigos,
        ] + $extra;
        $this->db->prepare('UPDATE proceso SET resumen = ?, terminado_en = NOW() WHERE id = ?')
                 ->execute([json_encode($resumen, JSON_UNESCAPED_UNICODE), $pid]);
    }

    private function historial(?int $pid): Historial
    {
        return new Historial($this->db, $this->usuarioId, 'backoffice', $pid);
    }

    /** @return array<string,mixed> */
    private function leer(int $id): array
    {
        return (new Historial($this->db))->leer('vocabulario', $id, Historial::CAMPOS['vocabulario']);
    }

    /** @return array<string,mixed> */
    private function leerAlias(int $id): array
    {
        return (new Historial($this->db))->leer('vocabulario_alias', $id, Historial::CAMPOS['vocabulario_alias']);
    }

    private function motivo(?string $m): ?string
    {
        $m = trim((string)$m);
        return $m === '' ? null : mb_substr($m, 0, 500);
    }

    /** @template T @param callable():T $fn @return T */
    private function enTransaccion(callable $fn): mixed
    {
        $propia = !$this->db->inTransaction();
        if ($propia) {
            $this->db->beginTransaction();
        }
        try {
            $r = $fn();
            if ($propia) {
                $this->db->commit();
            }
            return $r;
        } catch (\Throwable $e) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public static function ordenCodigo(string $a, string $b): int
    {
        $pa = Texto::parsearCodigo($a) ?? [0, 0];
        $pb = Texto::parsearCodigo($b) ?? [0, 0];
        return [$pa[0], $pa[1]] <=> [$pb[0], $pb[1]];
    }
}
