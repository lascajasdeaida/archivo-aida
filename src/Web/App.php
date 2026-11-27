<?php
declare(strict_types=1);

namespace Aida\Web;

/**
 * Utilidades del backoffice: sesión, usuario actual, permisos, CSRF, mensajes y render de vistas.
 */
final class App
{
    /** Permisos por rol (ver docs/backoffice.md §6). */
    private const PERMISOS = [
        'editar'            => ['admin', 'editor', 'data_entry'],
        'baja'              => ['admin', 'editor'],
        'acceso'            => ['admin', 'editor'],
        'nomenclatura'      => ['admin', 'editor'],
        'vocabularios'      => ['admin', 'editor'],
        'validar'           => ['admin', 'editor', 'qa'],
        'deshacer_propio'   => ['admin', 'editor', 'data_entry'],
        'deshacer_todo'     => ['admin', 'editor'],
        'forzar'            => ['admin', 'editor'],
        'procesos'          => ['admin', 'editor'],
        'correccion'        => ['admin', 'editor'],
        'publicar'          => ['admin', 'editor'],   // publicar / despublicar una caja en el sitio
        'consultas'         => ['admin', 'editor'],   // consultas a pedido del sitio (con datos personales)
        'usuarios'          => ['admin'],
        'migraciones'       => ['admin'],
        'ver'               => ['admin', 'editor', 'data_entry', 'qa'],
    ];

    public const ROLES = [
        'admin' => 'Administrador', 'editor' => 'Editor', 'data_entry' => 'Carga de datos', 'qa' => 'Control de calidad',
    ];

    public static function iniciarSesion(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', '1'); // no acepta ids de sesión inventados
            session_name('aida_bo');
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
            session_start();
        }
    }

    /** Abre la sesión de un usuario recién autenticado (ingreso o cambio de la propia contraseña). */
    public static function abrirSesionUsuario(array $u): void
    {
        session_regenerate_id(true);
        $_SESSION['usuario'] = ['id' => (int)$u['id'], 'email' => $u['email'], 'nombre' => $u['nombre'], 'rol' => $u['rol']];
        $_SESSION['version'] = (int)($u['sesion_version'] ?? 0);
    }

    /**
     * En cada pedido: si el usuario fue desactivado, cambió de perfil o de correo, o cambió la contraseña después de
     * abrir esta sesión, la sesión se cierra. Se compara usuario.sesion_version (sube con cada uno de esos cambios)
     * con el valor guardado al ingresar, por igualdad exacta: no depende de relojes ni zonas horarias.
     *
     * Si la base no responde, falla cerrado: la sesión se cierra y el pedido sigue como anónimo.
     */
    public static function revalidarSesion(): void
    {
        if (empty($_SESSION['usuario'])) {
            return;
        }
        try {
            $st = aida_db()->prepare('SELECT email, nombre, rol, activo, sesion_version FROM usuario WHERE id = ?');
            $st->execute([(int)$_SESSION['usuario']['id']]);
            $u = $st->fetch();
        } catch (\Throwable $t) {
            error_log('[aida-bo] revalidarSesion: ' . $t->getMessage());
            $u = false;
        }
        if (!$u || !(int)$u['activo'] || $u['rol'] === 'visitante' || !isset($_SESSION['version'])
            || (int)$u['sesion_version'] !== (int)$_SESSION['version']) {
            $_SESSION = [];
            session_regenerate_id(true);
            return;
        }
        $_SESSION['usuario']['nombre'] = $u['nombre'];
    }

    /**
     * Para el sitio público y la entrega de archivos: si llega la cookie del backoffice, abre la sesión, la revalida y
     * devuelve el usuario; si no hay sesión válida, no deja una sesión nueva ni manda Set-Cookie (al público no se le
     * crean sesiones). La sesión se cierra enseguida para no bloquear pedidos largos (un PDF pesado).
     *
     * @return array{id:int,email:string,nombre:?string,rol:string}|null
     */
    public static function sesionInterna(): ?array
    {
        if (empty($_COOKIE['aida_bo']) || !is_string($_COOKIE['aida_bo'])) {
            return null;
        }
        self::iniciarSesion();
        self::revalidarSesion();
        $u = self::usuario();
        if ($u === null) {
            session_destroy();
            header_remove('Set-Cookie');
            return null;
        }
        session_write_close();
        return $u;
    }

    /** @return array{id:int,email:string,nombre:?string,rol:string}|null */
    public static function usuario(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    public static function usuarioId(): ?int
    {
        return isset($_SESSION['usuario']) ? (int)$_SESSION['usuario']['id'] : null;
    }

    public static function puede(string $accion): bool
    {
        $u = self::usuario();
        return $u !== null && in_array($u['rol'], self::PERMISOS[$accion] ?? [], true);
    }

    public static function exigir(string $accion): void
    {
        if (!self::usuario()) {
            self::redirigir('login');
        }
        if (!self::puede($accion)) {
            http_response_code(403);
            self::render('error', ['titulo' => 'Sin permiso', 'mensaje' => 'Tu perfil no tiene permiso para esta acción.']);
            exit;
        }
    }

    // --- CSRF ---------------------------------------------------------

    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function campoToken(): string
    {
        return '<input type="hidden" name="_t" value="' . self::token() . '">';
    }

    public static function verificarPost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !hash_equals(self::token(), (string)($_POST['_t'] ?? ''))) {
            http_response_code(400);
            self::render('error', ['titulo' => 'Pedido inválido', 'mensaje' => 'El formulario venció o no es válido. Volvé atrás y probá de nuevo.']);
            exit;
        }
    }

    // --- Navegación y mensajes ----------------------------------------

    /** @param array<string,mixed> $params */
    public static function url(string $ruta, array $params = []): string
    {
        return '?' . http_build_query(['r' => $ruta] + $params);
    }

    /** @param array<string,mixed> $params */
    public static function redirigir(string $ruta, array $params = [], string $ancla = ''): never
    {
        header('Location: ' . self::url($ruta, $params) . ($ancla ? "#$ancla" : ''));
        exit;
    }

    public static function aviso(string $tipo, string $texto): void
    {
        $_SESSION['avisos'][] = [$tipo, $texto];
    }

    /** @return list<array{0:string,1:string}> */
    public static function avisos(): array
    {
        $a = $_SESSION['avisos'] ?? [];
        unset($_SESSION['avisos']);
        return $a;
    }

    // --- Vistas -------------------------------------------------------

    /** @param array<string,mixed> $datos */
    public static function render(string $vista, array $datos = []): void
    {
        $datos['_vista'] = $vista;
        extract($datos, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__, 2) . "/vistas/$vista.php";
        $contenido = ob_get_clean();
        if (in_array($vista, ['login', 'acceso'], true)) {
            echo $contenido;
            return;
        }
        require dirname(__DIR__, 2) . '/vistas/_layout.php';
    }

    /** Consultas del sitio sin atender (chip del menú). 0 si la tabla todavía no existe (migración 005 pendiente). */
    public static function consultasNuevas(): int
    {
        static $n = null;
        if ($n === null) {
            try {
                $n = (new \Aida\Consultas(aida_db()))->pendientes();
            } catch (\PDOException) {
                $n = 0;
            }
        }
        return $n;
    }

    public static function pendientes(): int
    {
        static $n = null;
        if ($n === null) {
            $n = (int)aida_db()->query('SELECT COUNT(*) FROM incidencia WHERE resuelta = 0 AND requiere_validacion = 1')->fetchColumn();
        }
        return $n;
    }
}
