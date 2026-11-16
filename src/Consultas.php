<?php
declare(strict_types=1);

namespace Aida;

/**
 * Consultas a pedido que llegan desde el sitio público (/consulta/C2R5) y su seguimiento en el backoffice.
 *
 *  - Los datos personales (nombre, correo, institución, mensaje) quedan solo en la tabla `consulta`: no van al
 *    historial (el alta se audita con código, tipo, uso y estado), ni a los eventos de uso, ni a GA4.
 *  - Estado (nueva / respondida / descartada) y notas internas se cambian por Historial: quedan auditados y se deshacen.
 *  - Todavía no se manda ningún correo (falta definir con el cliente quién responde, docs/consultas-cliente.md §2).
 *    Punto de extensión: NotificadorConsultas (config 'consultas' => ['notificador' => Clase::class]).
 *
 * Anti-spam sin servicios externos (Web\SitioPublico): campo trampa oculto, tiempo mínimo entre que se abre el
 * formulario y se envía (firmado), CSRF con cookie y límite de envíos por IP (guardada como HMAC, no en claro).
 */
final class Consultas
{
    public const USOS = [
        'investigacion' => 'Investigación académica', 'prensa' => 'Nota periodística',
        'produccion' => 'Producción audiovisual o teatral', 'personal' => 'Interés personal', 'otro' => 'Otro',
    ];
    public const MODALIDADES = ['digital' => 'Copia digital por correo', 'presencial' => 'En persona, con cita previa'];
    public const ESTADOS = ['nueva' => 'Nueva', 'respondida' => 'Respondida', 'descartada' => 'Descartada'];
    public const TIPOS = ['a_pedido' => 'Documento de consulta a pedido', 'original' => 'Consulta por el documento'];

    /** Envíos por IP en una hora antes de pedir que espere. */
    public const LIMITE_POR_HORA = 5;

    public function __construct(private readonly \PDO $db)
    {
    }

    /**
     * Valida los datos del formulario. Devuelve los errores por campo (vacío = válido) y los datos limpios.
     * @param array<string,mixed> $post
     * @return array{0: array<string,string>, 1: array<string,mixed>}
     */
    public static function validar(array $post): array
    {
        $txt = fn(string $k, int $max) => mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($post[$k] ?? '')) ?? ''), 0, $max);
        $d = [
            'nombre' => $txt('nombre', 120),
            'correo' => mb_strtolower($txt('correo', 190)),
            'institucion' => $txt('institucion', 160) ?: null,
            'uso' => (string)($post['uso'] ?? ''),
            'modalidad' => (string)($post['modalidad'] ?? 'digital'),
            'motivo' => mb_substr(trim(str_replace("\r\n", "\n", (string)($post['motivo'] ?? ''))), 0, 4000),
            'condiciones' => !empty($post['condiciones']),
        ];
        $e = [];
        if (mb_strlen($d['nombre']) < 2) {
            $e['nombre'] = 'Escribí tu nombre y apellido.';
        }
        if (!filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) {
            $e['correo'] = 'Escribí un correo válido, por ejemplo nombre@dominio.com.';
        }
        if (!isset(self::USOS[$d['uso']])) {
            $e['uso'] = 'Elegí para qué querés consultar el documento.';
        }
        if (!isset(self::MODALIDADES[$d['modalidad']])) {
            $d['modalidad'] = 'digital';
        }
        if (mb_strlen($d['motivo']) < 10) {
            $e['motivo'] = 'Contanos en pocas palabras qué necesitás (al menos 10 caracteres).';
        }
        if (!$d['condiciones']) {
            $e['condiciones'] = 'Tenés que aceptar las condiciones de uso para enviar el pedido.';
        }
        return [$e, $d];
    }

    /** ¿Esta IP (como HMAC) ya mandó demasiadas consultas en la última hora? */
    public function excedeLimite(?string $ipHash): bool
    {
        if ($ipHash === null) {
            return false;
        }
        $st = $this->db->prepare('SELECT COUNT(*) FROM consulta WHERE ip_hash = ? AND creado_en > (NOW() - INTERVAL 1 HOUR)');
        $st->execute([$ipHash]);
        return (int)$st->fetchColumn() >= self::LIMITE_POR_HORA;
    }

    /**
     * Guarda una consulta válida. El alta va al historial sin datos personales (origen 'sitio', sin usuario).
     * @param array{id:int|string, codigo:string} $registro
     * @param array<string,mixed> $d datos de validar()
     */
    public function guardar(array $registro, string $tipo, array $d, ?string $ipHash): int
    {
        $tipo = isset(self::TIPOS[$tipo]) ? $tipo : 'a_pedido';
        $this->db->beginTransaction();
        try {
            $this->db->prepare('INSERT INTO consulta (registro_id, codigo, tipo, nombre, correo, institucion, uso, modalidad, motivo, ip_hash)
                                VALUES (?,?,?,?,?,?,?,?,?,?)')
                     ->execute([(int)$registro['id'], $registro['codigo'], $tipo, $d['nombre'], $d['correo'], $d['institucion'],
                                $d['uso'], $d['modalidad'], $d['motivo'], $ipHash]);
            $id = (int)$this->db->lastInsertId();
            (new Historial($this->db, null, 'sitio'))->registrar('consulta', $id, 'alta', [],
                ['codigo' => $registro['codigo'], 'tipo' => $tipo, 'uso' => $d['uso'], 'modalidad' => $d['modalidad'], 'estado' => 'nueva']);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        try {
            self::notificador()->nueva($this->buscar($id) ?? []);
        } catch (\Throwable $e) {
            // Avisar nunca hace perder la consulta: ya está guardada y aparece en el backoffice.
            error_log('[aida-consultas] no se pudo avisar la consulta ' . $id . ': ' . $e->getMessage());
        }
        return $id;
    }

    /** Número que ve quien pidió y el equipo: «2026-0007». */
    public static function numero(array $c): string
    {
        $anio = !empty($c['creado_en']) ? date('Y', strtotime((string)$c['creado_en'])) : date('Y');
        return $anio . '-' . str_pad((string)(int)$c['id'], 4, '0', STR_PAD_LEFT);
    }

    public function buscar(int $id): ?array
    {
        $st = $this->db->prepare('SELECT q.*, u.nombre atendida_por_nombre, u.email atendida_por_email, r.caja_id,
                c.numero caja_numero, c.nombre caja_nombre, r.titulo, r.acceso
            FROM consulta q LEFT JOIN usuario u ON u.id = q.atendida_por LEFT JOIN registro r ON r.id = q.registro_id
            LEFT JOIN caja c ON c.id = r.caja_id WHERE q.id = ?');
        $st->execute([$id]);
        return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Lista para el backoffice, la más nueva primero.
     * @return list<array<string,mixed>>
     */
    public function lista(?string $estado = null, string $q = ''): array
    {
        $w = ['q.eliminado_en IS NULL'];
        $p = [];
        if ($estado !== null && isset(self::ESTADOS[$estado])) {
            $w[] = 'q.estado = ?';
            $p[] = $estado;
        }
        if ($q !== '') {
            $w[] = '(q.codigo = ? OR q.nombre LIKE ? OR q.correo LIKE ? OR q.institucion LIKE ?)';
            array_push($p, strtoupper($q), "%$q%", "%$q%", "%$q%");
        }
        $st = $this->db->prepare('SELECT q.id, q.codigo, q.registro_id, q.tipo, q.nombre, q.correo, q.institucion, q.uso, q.modalidad,
                q.estado, q.creado_en, q.atendida_en, r.titulo, c.numero caja_numero
            FROM consulta q LEFT JOIN registro r ON r.id = q.registro_id LEFT JOIN caja c ON c.id = r.caja_id
            WHERE ' . implode(' AND ', $w) . ' ORDER BY q.estado = \'nueva\' DESC, q.creado_en DESC, q.id DESC LIMIT 500');
        $st->execute($p);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @return array<string,int> cantidad por estado */
    public function conteos(): array
    {
        $out = array_fill_keys(array_keys(self::ESTADOS), 0);
        foreach ($this->db->query('SELECT estado, COUNT(*) n FROM consulta WHERE eliminado_en IS NULL GROUP BY estado') as $f) {
            $out[$f['estado']] = (int)$f['n'];
        }
        return $out;
    }

    public function pendientes(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM consulta WHERE estado = 'nueva' AND eliminado_en IS NULL")->fetchColumn();
    }

    /**
     * Cambia estado y notas desde el backoffice, por Historial (se deshace desde la ficha de la consulta).
     * Al salir de «nueva» se anota quién y cuándo la atendió; al volver a «nueva», se limpia.
     */
    public function actualizar(int $id, string $estado, ?string $notas, int $usuarioId): ?int
    {
        $c = $this->buscar($id);
        if (!$c || !isset(self::ESTADOS[$estado])) {
            throw new \InvalidArgumentException('Consulta o estado inexistente');
        }
        $datos = [];
        $notas = $notas !== null ? (trim(str_replace("\r\n", "\n", $notas)) ?: null) : null;
        if ($notas !== $c['notas']) {
            $datos['notas'] = $notas;
        }
        if ($estado !== $c['estado']) {
            $datos['estado'] = $estado;
            $datos['atendida_por'] = $estado === 'nueva' ? null : $usuarioId;
            $datos['atendida_en'] = $estado === 'nueva' ? null : date('Y-m-d H:i:s');
        }
        if (!$datos) {
            return null;
        }
        return (new Historial($this->db, $usuarioId, 'backoffice'))->actualizar('consulta', $id, $datos, false);
    }

    /** Notificador configurado (por defecto, ninguno). */
    public static function notificador(): NotificadorConsultas
    {
        $clase = function_exists('aida_config') ? (aida_config()['consultas']['notificador'] ?? null) : null;
        if (is_string($clase) && class_exists($clase) && is_subclass_of($clase, NotificadorConsultas::class)) {
            return new $clase();
        }
        return new SinNotificacion();
    }
}
