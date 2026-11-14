<?php
declare(strict_types=1);

/**
 * Arranque común: autoload simple del namespace Aida y conexión PDO.
 * La configuración vive en config/config.php (no versionado); ver config/config.example.php.
 */

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Aida\\')) {
        $file = __DIR__ . '/' . substr($class, 5) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

function aida_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $path = dirname(__DIR__) . '/config/config.php';
        if (!is_file($path)) {
            fwrite(STDERR, "Falta config/config.php (copiar config/config.example.php)\n");
            exit(1);
        }
        $cfg = require $path;
    }
    return $cfg;
}

function aida_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = aida_config()['db'];
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'] ?? 3306, $c['name']),
            $c['user'],
            $c['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
    return $pdo;
}
