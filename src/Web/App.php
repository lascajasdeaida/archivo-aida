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
        'usuarios'          => ['admin'],
        'ver'               => ['admin', 'editor', 'data_entry', 'qa'],
    ];

    public const ROLES = [
        'admin' => 'Administrador', 'editor' => 'Editor', 'data_entry' => 'Carga de datos', 'qa' => 'Control de calidad',
    ];

    public static function iniciarSesion(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('aida_bo');
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
            session_start();
        }
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
        if ($vista === 'login') {
            echo $contenido;
            return;
        }
        require dirname(__DIR__, 2) . '/vistas/_layout.php';
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
