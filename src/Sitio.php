<?php
declare(strict_types=1);

namespace Aida;

/**
 * Datos de las páginas del sitio público (ficha de un registro y página de una caja), listos para las
 * plantillas de vistas/publico/. Solo lee: no escribe nada ni cuenta vistas.
 *
 * Dos modos:
 *  - público (vistaPrevia = false): lo que no se muestra en el sitio no existe (devuelve null).
 *    Reglas iguales a Buscador (modo público) y Entrega: sin bajas, registro publicado, acceso distinto de
 *    «a revisar», caja publicada.
 *  - vista previa (vistaPrevia = true, backoffice): devuelve la página aunque hoy no se vea en el sitio, con
 *    la lista de motivos (`motivos`) para avisarle al operador. Todo lo demás se arma igual que en el sitio:
 *    la lista de la caja, los vecinos y las cifras cuentan solo lo que el sitio mostraría si la caja estuviera
 *    publicada.
 *
 * Vocabularios: se muestran los términos aprobados y los propuestos; nunca los descartados, los dados de baja
 * ni los que no tienen letras ni números («.», «-»). Ver docs/decisiones.md, «Vocabularios».
 */
final class Sitio
{
    /** Condición SQL de un registro visible en el sitio (alias r = registro, c = caja). */
    private const VISIBLE = "r.eliminado_en IS NULL AND r.publicado = 1 AND r.acceso <> 'a_revisar'
        AND c.eliminado_en IS NULL AND c.publicada = 1";

    /** Igual, sin exigir caja publicada: lo que el sitio mostraría de la caja si estuviera publicada. */
    private const VISIBLE_EN_CAJA = "r.eliminado_en IS NULL AND r.publicado = 1 AND r.acceso <> 'a_revisar'";

    private const EXTRACTO = 220;

    public function __construct(private readonly \PDO $db, private readonly bool $vistaPrevia = false)
    {
    }

    // ------------------------------------------------------------------
    // Ficha del registro
    // ------------------------------------------------------------------

    /** Ficha por id de registro (vista previa) o null si no existe o no se muestra. */
    public function fichaPorId(int $id): ?array
    {
        return $this->ficha('r.id = ?', [$id]);
    }

    /** Ficha por código («C14R3», «c14r03»). */
    public function fichaPorCodigo(string $codigo): ?array
    {
        $p = Texto::parsearCodigo($codigo);
        if ($p === null || $p[2] !== 0) {
            return null;
        }
        return $this->ficha('c.numero = ? AND r.numero = ?', [$p[0], $p[1]]);
    }

    private function ficha(string $donde, array $params): ?array
    {
        $st = $this->db->prepare("SELECT r.*, c.numero caja_numero, c.nombre caja_nombre, c.publicada caja_publicada,
                c.eliminado_en caja_baja
            FROM registro r JOIN caja c ON c.id = r.caja_id WHERE $donde");
        $st->execute($params);
        $r = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$r) {
            return null;
        }
        $motivos = self::motivosRegistro($r);
        if ($motivos && !$this->vistaPrevia) {
            return null;
        }
        $id = (int)$r['id'];
        $voc = $this->vocabularios([$id])[$id] ?? ['tipo_documento' => [], 'descriptor' => []];
        $anios = $this->anios([$id])[$id] ?? [];
        $archivos = $this->archivos($id);

        // Vecinos: los registros de la caja que el sitio muestra, más este (si es de los que no se ven, para ubicarlo)
        $lista = $this->listaCaja((int)$r['caja_id']);
        $nums = array_column($lista, 'numero');
        if (!in_array((int)$r['numero'], array_map('intval', $nums), true)) {
            $lista[] = ['id' => $id, 'numero' => (int)$r['numero'], 'codigo' => $r['codigo'], 'titulo' => $r['titulo']];
            usort($lista, fn($a, $b) => (int)$a['numero'] <=> (int)$b['numero']);
        }
        $pos = array_search($id, array_map(fn($x) => (int)$x['id'], $lista), true);
        $vecino = function (?array $x): ?array {
            if ($x === null) {
                return null;
            }
            $v = $this->vocabularios([(int)$x['id']])[(int)$x['id']] ?? ['tipo_documento' => []];
            return ['id' => (int)$x['id'], 'codigo' => $x['codigo']] + self::nombre($x['titulo'], $v['tipo_documento']);
        };

        $digital = $archivos !== [];
        $documento = $r['acceso'] !== 'publico' ? 'pedido' : ($digital ? 'ver' : 'ficha');

        return [
            'registro' => $r,
            'caja' => ['id' => (int)$r['caja_id'], 'numero' => (int)$r['caja_numero'], 'nombre' => $r['caja_nombre']],
            'tipos' => $voc['tipo_documento'],
            'descriptores' => $voc['descriptor'],
            'anio' => self::anio($anios, $r['anio_texto']),
            'archivos' => $archivos,
            'digital' => $digital,
            'documento' => $documento,
            'anterior' => $vecino($pos !== false && $pos > 0 ? $lista[$pos - 1] : null),
            'siguiente' => $vecino($pos !== false && isset($lista[$pos + 1]) ? $lista[$pos + 1] : null),
            'motivos' => $motivos,
        ] + self::nombre($r['titulo'], $voc['tipo_documento']);
    }

    /**
     * Por qué un registro no aparece hoy en el sitio (vacío = aparece).
     * @param array $r fila de registro con caja_baja y caja_publicada
     * @return list<string>
     */
    public static function motivosRegistro(array $r): array
    {
        $m = [];
        if ($r['eliminado_en'] !== null) {
            $m[] = 'El registro está dado de baja (' . self::fecha($r['eliminado_en']) . ').';
        }
        if ($r['caja_baja'] !== null) {
            $m[] = 'Su caja está dada de baja (' . self::fecha($r['caja_baja']) . ').';
        }
        if (!(int)$r['publicado']) {
            $m[] = 'El registro está marcado como no publicado.';
        }
        if ($r['acceso'] === 'a_revisar') {
            $m[] = 'El acceso está «a revisar»' . ($r['excel_privado'] ? ' (la planilla dice «' . $r['excel_privado'] . '»)' : '')
                . ': no se publica hasta elegir Público o Restringido.';
        }
        if ($r['caja_baja'] === null && !(int)$r['caja_publicada']) {
            $m[] = 'La caja ' . (int)$r['caja_numero'] . ' todavía no está publicada.';
        }
        return $m;
    }

    // ------------------------------------------------------------------
    // Página de la caja
    // ------------------------------------------------------------------

    public function cajaPorId(int $id): ?array
    {
        return $this->caja('id = ?', [$id]);
    }

    public function cajaPorNumero(int $numero): ?array
    {
        return $this->caja('numero = ?', [$numero]);
    }

    private function caja(string $donde, array $params): ?array
    {
        $st = $this->db->prepare("SELECT * FROM caja WHERE $donde");
        $st->execute($params);
        $c = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$c) {
            return null;
        }
        $motivos = self::motivosCaja($c);
        if ($motivos && !$this->vistaPrevia) {
            return null;
        }
        $registros = $this->tarjetas((int)$c['id']);

        // Lo que no aparecería aunque la caja estuviera publicada (solo para la vista previa)
        $st = $this->db->prepare("SELECT SUM(eliminado_en IS NOT NULL) bajas,
                SUM(eliminado_en IS NULL AND publicado = 0) no_publicados,
                SUM(eliminado_en IS NULL AND publicado = 1 AND acceso = 'a_revisar') a_revisar
            FROM registro WHERE caja_id = ?");
        $st->execute([$c['id']]);
        $ocultos = array_map('intval', $st->fetch(\PDO::FETCH_ASSOC) ?: []);

        $anios = [];
        $tipos = [];
        foreach ($registros as $t) {
            $anios = array_merge($anios, array_keys($t['anios']));
            foreach ($t['tipos'] as $tp) {
                $tipos[$tp['slug']] ??= $tp + ['cantidad' => 0];
                $tipos[$tp['slug']]['cantidad']++;
            }
        }
        uasort($tipos, fn($a, $b) => ($b['cantidad'] <=> $a['cantidad']) ?: strcmp($a['slug'], $b['slug']));

        // Cajas vecinas y total: las publicadas (en la vista previa, todas las que no están dadas de baja)
        $vis = $this->vistaPrevia ? 'eliminado_en IS NULL' : 'eliminado_en IS NULL AND publicada = 1';
        $todas = $this->db->query("SELECT id, numero, nombre FROM caja WHERE $vis ORDER BY numero")->fetchAll(\PDO::FETCH_ASSOC);
        $pos = array_search((int)$c['id'], array_map(fn($x) => (int)$x['id'], $todas), true);
        $dig = count(array_filter($registros, fn($t) => $t['digital']));

        return [
            'caja' => $c,
            'registros' => $registros,
            'cifras' => [
                'documentos' => count($registros),
                'digitales' => $dig,
                'a_pedido' => count(array_filter($registros, fn($t) => $t['pedido'])),
                'sin_titulo' => count(array_filter($registros, fn($t) => $t['sinTitulo'])),
                'desde' => $anios ? min($anios) : null,
                'hasta' => $anios ? max($anios) : null,
                'avance' => $registros ? (int)round($dig / count($registros) * 100) : 0,
            ],
            'tipos' => array_slice(array_values($tipos), 0, 6),
            'total_cajas' => count($todas),
            'anterior' => $pos !== false && $pos > 0 ? $todas[$pos - 1] : null,
            'siguiente' => $pos !== false && isset($todas[$pos + 1]) ? $todas[$pos + 1] : null,
            'motivos' => $motivos,
            'ocultos' => $ocultos,
        ];
    }

    /** @return list<string> */
    public static function motivosCaja(array $c): array
    {
        if ($c['eliminado_en'] !== null) {
            return ['La caja está dada de baja (' . self::fecha($c['eliminado_en']) . ').'];
        }
        return (int)$c['publicada'] ? [] : ['La caja todavía no está publicada: ni ella ni sus registros aparecen en el sitio.'];
    }

    /** Tarjetas de los registros que el sitio muestra en una caja, en el orden físico. */
    private function tarjetas(int $cajaId): array
    {
        $lista = $this->listaCaja($cajaId, true);
        $ids = array_map(fn($x) => (int)$x['id'], $lista);
        $voc = $this->vocabularios($ids);
        $anios = $this->anios($ids);
        $out = [];
        foreach ($lista as $x) {
            $id = (int)$x['id'];
            $tipos = $voc[$id]['tipo_documento'] ?? [];
            $resumen = trim((string)$x['resumen']);
            $out[] = [
                'id' => $id,
                'codigo' => $x['codigo'],
                'numero' => (int)$x['numero'],
                'tipos' => $tipos,
                'anios' => $anios[$id] ?? [],
                'anio' => self::anio($anios[$id] ?? [], $x['anio_texto']),
                'extracto' => mb_strlen($resumen) > self::EXTRACTO ? rtrim(mb_substr($resumen, 0, self::EXTRACTO)) . '…' : $resumen,
                'digital' => (bool)(int)$x['digital'],
                'pedido' => $x['acceso'] === 'restringido',
            ] + self::nombre($x['titulo'], $tipos);
        }
        return $out;
    }

    /** Registros visibles de una caja (sin exigir que la caja esté publicada). */
    private function listaCaja(int $cajaId, bool $completo = false): array
    {
        $campos = $completo
            ? ", r.resumen, r.anio_texto, r.acceso, EXISTS (SELECT 1 FROM archivo_digital a WHERE a.registro_id = r.id
                 AND a.eliminado_en IS NULL AND a.presente = 1) digital"
            : '';
        $st = $this->db->prepare('SELECT r.id, r.numero, r.codigo, r.titulo' . $campos . '
            FROM registro r WHERE r.caja_id = ? AND ' . self::VISIBLE_EN_CAJA . ' ORDER BY r.numero');
        $st->execute([$cajaId]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------------
    // Partes comunes
    // ------------------------------------------------------------------

    /**
     * Archivos que se pueden ver de un registro (no quitados, presentes en el último escaneo, formato servible).
     * Si el visor los entrega o no al público lo decide Entrega según el acceso; acá solo se listan.
     */
    private function archivos(int $registroId): array
    {
        $st = $this->db->prepare('SELECT a.id, a.registro_id, a.ruta, a.nombre_archivo, a.formato, a.parte, a.peso_bytes,
                a.presente, a.eliminado_en archivo_baja, r.acceso, r.publicado, r.eliminado_en registro_baja, c.eliminado_en caja_baja
            FROM archivo_digital a JOIN registro r ON r.id = a.registro_id JOIN caja c ON c.id = r.caja_id
            WHERE a.registro_id = ? ORDER BY a.parte, a.nombre_archivo');
        $st->execute([$registroId]);
        $out = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $a) {
            // Como lo vería el equipo: descarta solo lo que no existe o no se puede entregar (bajas, ausentes, formato)
            $a['registro_baja'] = $a['caja_baja'] = null;
            if (Entrega::decidir($a, true) === Entrega::SERVIR) {
                $out[] = ['id' => (int)$a['id'], 'nombre' => $a['nombre_archivo'], 'formato' => strtolower((string)$a['formato']),
                          'parte' => (int)$a['parte'], 'peso' => $a['peso_bytes'] !== null ? (int)$a['peso_bytes'] : null];
            }
        }
        return $out;
    }

    /**
     * Tipos y descriptores por registro, en el orden de la planilla.
     * @param list<int> $ids
     * @return array<int, array{tipo_documento: list<array{nombre:string,slug:string}>, descriptor: list<array{nombre:string,slug:string}>}>
     */
    private function vocabularios(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $st = $this->db->prepare('SELECT rv.registro_id, v.tipo, v.nombre FROM registro_vocabulario rv
            JOIN vocabulario v ON v.id = rv.vocabulario_id
            WHERE rv.registro_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ")
              AND v.estado IN ('aprobado', 'propuesto') AND v.eliminado_en IS NULL AND v.fusionado_en IS NULL
            ORDER BY rv.registro_id, v.tipo, rv.orden, v.nombre");
        $st->execute($ids);
        $out = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $f) {
            if (!preg_match('/[\p{L}\p{N}]/u', (string)$f['nombre'])) {
                continue; // «.», «-»: basura sin curar
            }
            $out[(int)$f['registro_id']] ??= ['tipo_documento' => [], 'descriptor' => []];
            $out[(int)$f['registro_id']][$f['tipo']][] = ['nombre' => $f['nombre'], 'slug' => self::slug($f['nombre'])];
        }
        return $out;
    }

    /**
     * @param list<int> $ids
     * @return array<int, array<int,bool>> registro => [año => sin confirmar]
     */
    private function anios(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $st = $this->db->prepare('SELECT registro_id, anio, sin_confirmar FROM registro_anio WHERE registro_id IN ('
            . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY registro_id, anio');
        $st->execute($ids);
        $out = [];
        foreach ($st->fetchAll(\PDO::FETCH_NUM) as [$r, $a, $d]) {
            $out[(int)$r][(int)$a] = (bool)(int)$d;
        }
        return $out;
    }

    /**
     * Año para mostrar. Con años interpretados: la etiqueta («1998, 2006–2008», «1972 (sin confirmar)»).
     * Sin años: el texto de la planilla tal cual, salvo que diga que no tiene fecha.
     * @return array{etiqueta:?string, dudoso:bool, original:?string, decada:?int}
     */
    public static function anio(array $anios, ?string $texto): array
    {
        $texto = trim((string)$texto) ?: null;
        if ($anios) {
            $et = Texto::aniosEtiqueta($anios);
            $unico = count($anios) === 1 ? array_key_first($anios) : null;
            return [
                'etiqueta' => $et,
                'dudoso' => in_array(true, $anios, true),
                // El texto de la planilla, si dice algo distinto de lo que se muestra («1972?», «1981/82»)
                'original' => $texto !== null && $texto !== (string)$unico ? $texto : null,
                'decada' => $unico !== null ? intdiv($unico, 10) * 10 : null,
            ];
        }
        $sinFecha = $texto === null || Texto::anio($texto)['desde'] === null && preg_match('/^(sin|s\/|no |\?+$)/iu', $texto);
        return ['etiqueta' => $sinFecha ? null : $texto, 'dudoso' => false, 'original' => null, 'decada' => null];
    }

    /**
     * Nombre con el que se muestra un registro: su título o, si no tiene, sus tipos (en cursiva en la plantilla).
     * @return array{titulo:string, sinTitulo:bool}
     */
    public static function nombre(?string $titulo, array $tipos): array
    {
        $titulo = trim((string)$titulo);
        if ($titulo !== '') {
            return ['titulo' => $titulo, 'sinTitulo' => false];
        }
        $nombres = array_column($tipos, 'nombre');
        return ['titulo' => $nombres ? implode(' · ', $nombres) : 'Documento sin título', 'sinTitulo' => true];
    }

    /** «La Historia Oficial» → «la-historia-oficial» (para las URL del sitio). */
    public static function slug(?string $s): string
    {
        return trim((string)preg_replace('/[^a-z0-9]+/', '-', Texto::clave($s)), '-');
    }

    private static function fecha(string $f): string
    {
        $t = strtotime($f);
        return $t ? 'desde el ' . date('d/m/Y', $t) : $f;
    }
}
