<?php
declare(strict_types=1);

/**
 * Router para el servidor embebido de PHP, en desarrollo y pruebas:
 *
 *   php -S 127.0.0.1:8100 -t public bin/router.php
 *
 * Hace lo mismo que public/.htaccess: lo que existe en public/ se sirve tal cual (PHP incluido: admin/, archivo.php);
 * el resto va a public/sitio.php con URLs legibles. sitio.php?ruta=… también funciona (modo sin reescritura).
 */

$publico = dirname(__DIR__) . '/public';
$camino = rawurldecode((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
$archivo = realpath($publico . $camino);

if ($archivo !== false && str_starts_with($archivo, $publico) && $camino !== '/') {
    if (is_dir($archivo) && is_file($archivo . '/index.php')) {
        $archivo .= '/index.php';
    }
    if (is_file($archivo)) {
        if (str_ends_with($archivo, '.php')) {
            $_SERVER['SCRIPT_NAME'] = substr($archivo, strlen($publico));
            $_SERVER['SCRIPT_FILENAME'] = $archivo;
            chdir(dirname($archivo));
            require $archivo;
            return true;
        }
        return false; // estático: lo sirve el servidor embebido
    }
}

$_SERVER['SCRIPT_NAME'] = '/sitio.php';
$_SERVER['SCRIPT_FILENAME'] = $publico . '/sitio.php';
require $publico . '/sitio.php';
