<?php
declare(strict_types=1);

namespace Aida;

/**
 * Usuarios del backoffice: alta, edición, baja (desactivar; no se borra nada), contraseñas y enlaces de acceso.
 *
 * Enlaces de acceso: un valor al azar de 32 bytes que viaja solo en el enlace. En la base queda su hash SHA-256,
 * el vencimiento y si ya se usó. Generar uno nuevo anula los anteriores sin usar del mismo usuario.
 * Todos los cambios de datos (no de contraseña) quedan en el historial.
 */
final class Usuarios
{
    public const ROLES = ['admin', 'editor', 'data_entry', 'qa'];
    public const CLAVE_MIN = 10;
    /** Horas de validez de cada enlace. */
    public const VIGENCIA = ['restablecer' => 2, 'alta' => 72];
    /** «Olvidé mi contraseña»: como mucho un correo cada tantos minutos por usuario. */
    public const ESPERA_MIN = 5;

    public function __construct(private \PDO $db, private ?int $actorId = null)
    {
    }

    /** @return list<array<string,mixed>> */
    public function listar(): array
    {
        return $this->db->query("SELECT id, email, nombre, rol, activo, ultimo_ingreso, creado_en
                                 FROM usuario WHERE rol <> 'visitante'
                                 ORDER BY activo DESC, FIELD(rol,'admin','editor','data_entry','qa'), COALESCE(nombre, email)")->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function buscar(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM usuario WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** @return array<string,mixed>|null */
    public function porEmail(string $email): ?array
    {
        $st = $this->db->prepare('SELECT * FROM usuario WHERE email = ?');
        $st->execute([self::normalizarEmail($email)]);
        return $st->fetch() ?: null;
    }

    public static function normalizarEmail(string $e): string
    {
        return mb_strtolower(trim($e));
    }

    /**
     * Valida los datos del formulario. Devuelve los errores por campo (vacío si está todo bien).
     * @param array{email:string,nombre:string,rol:string,activo:int} $d
     * @return array<string,string>
     */
    public function validar(array $d, ?int $id = null): array
    {
        $err = [];
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $err['email'] = 'Escribí un correo válido.';
        } else {
            $otro = $this->porEmail($d['email']);
            if ($otro && (int)$otro['id'] !== $id) {
                $err['email'] = 'Ya hay un usuario con ese correo.';
            }
        }
        if (mb_strlen($d['nombre']) > 120) {
            $err['nombre'] = 'El nombre es demasiado largo.';
        }
        if (!in_array($d['rol'], self::ROLES, true)) {
            $err['rol'] = 'Elegí un perfil.';
        }
        if ($id !== null && $id === $this->actorId) {
            $yo = $this->buscar($id);
            if ($yo && $d['rol'] !== $yo['rol']) {
                $err['rol'] = 'No podés cambiar tu propio perfil. Pedíselo a otro administrador.';
            }
            if (!$d['activo']) {
                $err['activo'] = 'No podés desactivar tu propio usuario.';
            }
        }
        if ($id !== null && !isset($err['rol']) && !isset($err['activo'])) {
            $actual = $this->buscar($id);
            $dejaDeSerAdmin = $actual && $actual['rol'] === 'admin' && (int)$actual['activo'] === 1 && ($d['rol'] !== 'admin' || !$d['activo']);
            if ($dejaDeSerAdmin && $this->adminsActivos() <= 1) {
                $err['rol'] = 'Tiene que quedar al menos un administrador activo.';
            }
        }
        return $err;
    }

    public function adminsActivos(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM usuario WHERE rol = 'admin' AND activo = 1")->fetchColumn();
    }

    /**
     * Alta sin contraseña utilizable: la persona la crea desde el enlace de alta.
     * @param array{email:string,nombre:string,rol:string,activo:int} $d
     */
    public function crear(array $d): int
    {
        $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT); // nadie la conoce
        $this->db->prepare('INSERT INTO usuario (email, password_hash, nombre, rol, activo) VALUES (?,?,?,?,?)')
                 ->execute([$d['email'], $hash, $d['nombre'] ?: null, $d['rol'], $d['activo']]);
        $id = (int)$this->db->lastInsertId();
        $this->historial()->registrar('usuario', $id, 'alta', [], self::publicos($d));
        return $id;
    }

    /** @param array{email:string,nombre:string,rol:string,activo:int} $d */
    public function actualizar(int $id, array $d): void
    {
        $antes = $this->buscar($id);
        if (!$antes) {
            throw new \RuntimeException('Usuario inexistente.');
        }
        $this->db->prepare('UPDATE usuario SET email = ?, nombre = ?, rol = ?, activo = ? WHERE id = ?')
                 ->execute([$d['email'], $d['nombre'] ?: null, $d['rol'], $d['activo'], $id]);
        // Si cambia el correo, el perfil o el estado, los enlaces pendientes (pueden estar en manos del destinatario
        // anterior) se anulan y las sesiones abiertas se cierran.
        if ($antes['email'] !== $d['email'] || $antes['rol'] !== $d['rol'] || (int)$antes['activo'] !== (int)$d['activo']) {
            $this->anularEnlaces($id);
            $this->cerrarSesiones($id);
        }
        $accion = (int)$antes['activo'] === 1 && !$d['activo'] ? 'baja' : ((int)$antes['activo'] === 0 && $d['activo'] ? 'restauracion' : 'modificacion');
        $this->historial()->registrar('usuario', $id, $accion, self::publicos($antes), self::publicos($d));
    }

    // ------------------------------------------------------------------
    // Contraseñas
    // ------------------------------------------------------------------

    /** Devuelve el error si la contraseña no sirve, o null. */
    public static function errorClave(string $clave, string $repetida, string $email): ?string
    {
        if (mb_strlen($clave) < self::CLAVE_MIN) {
            return 'La contraseña tiene que tener al menos ' . self::CLAVE_MIN . ' caracteres.';
        }
        if (strlen($clave) > 200) {
            return 'La contraseña es demasiado larga.';
        }
        if ($clave !== $repetida) {
            return 'Las dos contraseñas no coinciden.';
        }
        if (mb_strtolower($clave) === mb_strtolower($email) || mb_strtolower($clave) === mb_strtolower(strstr($email, '@', true) ?: '')) {
            return 'La contraseña no puede ser tu correo.';
        }
        return null;
    }

    public function cambiarClave(int $id, string $clave): void
    {
        $this->db->prepare('UPDATE usuario SET password_hash = ?, clave_cambiada_en = NOW(), sesion_version = sesion_version + 1 WHERE id = ?')
                 ->execute([password_hash($clave, PASSWORD_DEFAULT), $id]);
        $this->anularEnlaces($id);
        $this->historial()->registrar('usuario', $id, 'modificacion', ['clave' => 'anterior'], ['clave' => 'cambiada']);
    }

    public function registrarIngreso(int $id): void
    {
        $this->db->prepare('UPDATE usuario SET ultimo_ingreso = NOW() WHERE id = ?')->execute([$id]);
    }

    // ------------------------------------------------------------------
    // Enlaces de acceso
    // ------------------------------------------------------------------

    /** Crea un enlace nuevo (anula los anteriores) y devuelve el valor que va en la URL. */
    public function crearEnlace(int $usuarioId, string $tipo): string
    {
        if (!isset(self::VIGENCIA[$tipo])) {
            throw new \InvalidArgumentException("Tipo de enlace desconocido: $tipo");
        }
        $this->anularEnlaces($usuarioId);
        $valor = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->db->prepare('INSERT INTO usuario_token (usuario_id, hash, tipo, vence_en, creado_por)
                            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), ?)')
                 ->execute([$usuarioId, hash('sha256', $valor), $tipo, self::VIGENCIA[$tipo], $this->actorId]);
        return $valor;
    }

    /** ¿Ya se mandó un enlace a este usuario hace muy poco? (freno de «Olvidé mi contraseña») */
    public function enlaceReciente(int $usuarioId): bool
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM usuario_token WHERE usuario_id = ? AND creado_en > DATE_SUB(NOW(), INTERVAL ? MINUTE)');
        $st->execute([$usuarioId, self::ESPERA_MIN]);
        return (int)$st->fetchColumn() > 0;
    }

    /**
     * Enlace vigente (sin usar, sin vencer, usuario activo) o null.
     * @return array{token_id:int,tipo:string,usuario:array<string,mixed>}|null
     */
    public function enlaceVigente(string $valor): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{40,60}$/', $valor)) {
            return null;
        }
        $st = $this->db->prepare('SELECT t.id token_id, t.tipo, u.*
                                  FROM usuario_token t JOIN usuario u ON u.id = t.usuario_id
                                  WHERE t.hash = ? AND t.usado_en IS NULL AND t.vence_en > NOW() AND u.activo = 1');
        $st->execute([hash('sha256', $valor)]);
        $f = $st->fetch();
        if (!$f) {
            return null;
        }
        $tokenId = (int)$f['token_id'];
        $tipo = (string)$f['tipo'];
        unset($f['token_id'], $f['tipo']);
        return ['token_id' => $tokenId, 'tipo' => $tipo, 'usuario' => $f];
    }

    /** Usa el enlace: fija la contraseña y lo marca usado. Devuelve false si ya no estaba vigente. */
    public function usarEnlace(string $valor, string $clave): bool
    {
        $this->db->beginTransaction();
        try {
            $e = $this->enlaceVigente($valor);
            if (!$e) {
                $this->db->rollBack();
                return false;
            }
            $st = $this->db->prepare('UPDATE usuario_token SET usado_en = NOW() WHERE id = ? AND usado_en IS NULL');
            $st->execute([$e['token_id']]);
            if ($st->rowCount() !== 1) {
                $this->db->rollBack();
                return false;
            }
            $this->cambiarClave((int)$e['usuario']['id'], $clave);
            $this->db->commit();
            return true;
        } catch (\Throwable $t) {
            $this->db->rollBack();
            throw $t;
        }
    }

    /** Cierra todas las sesiones abiertas del usuario (App::revalidarSesion compara esta versión). */
    public function cerrarSesiones(int $usuarioId): void
    {
        $this->db->prepare('UPDATE usuario SET sesion_version = sesion_version + 1 WHERE id = ?')->execute([$usuarioId]);
    }

    public function anularEnlaces(int $usuarioId): void
    {
        $this->db->prepare('UPDATE usuario_token SET usado_en = NOW() WHERE usuario_id = ? AND usado_en IS NULL')->execute([$usuarioId]);
    }

    /** Texto del correo con el enlace. */
    public static function textoCorreo(string $tipo, string $enlace, ?string $nombre): string
    {
        $hola = 'Hola' . ($nombre ? " $nombre" : '') . ',';
        $horas = self::VIGENCIA[$tipo];
        if ($tipo === 'alta') {
            return "$hola\n\nTe dieron acceso al backoffice del archivo Las cajas de Aída.\n"
                 . "Para crear tu contraseña, entrá a este enlace (vale $horas horas y se usa una sola vez):\n\n$enlace\n\n"
                 . "Si no esperabas este correo, ignoralo.\n";
        }
        return "$hola\n\nPediste restablecer tu contraseña del backoffice de Las cajas de Aída.\n"
             . "Entrá a este enlace para elegir una nueva (vale $horas horas y se usa una sola vez):\n\n$enlace\n\n"
             . "Si no lo pediste vos, ignorá este correo: tu contraseña actual sigue funcionando.\n";
    }

    // ------------------------------------------------------------------

    private function historial(): Historial
    {
        return new Historial($this->db, $this->actorId, 'backoffice');
    }

    /** @return array<string,mixed> */
    private static function publicos(array $u): array
    {
        return ['email' => $u['email'], 'nombre' => ($u['nombre'] ?? '') ?: null, 'rol' => $u['rol'], 'activo' => (int)$u['activo']];
    }
}
