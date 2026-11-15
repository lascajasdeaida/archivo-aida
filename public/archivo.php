<?php
declare(strict_types=1);

/**
 * Entrega de un archivo digital por id:  /archivo.php?id=123  [&descargar=1]
 *
 * Lo usan el sitio público y el backoffice («Ver PDF» de la ficha). Las reglas de acceso están
 * en src/Entrega.php. Nunca se recibe una ruta: solo el id de archivo_digital.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Aida\Entrega;
use Aida\Web\App;

// ¿Es un usuario del backoffice? Solo se abre la sesión si ya existe la cookie (al público no se le crea sesión),
// y se cierra enseguida para no bloquear el resto del backoffice mientras se transmite un PDF grande.
$interno = false;
if (isset($_COOKIE['aida_bo'])) {
    App::iniciarSesion();
    $interno = App::usuario() !== null && App::puede('ver');
    session_write_close();
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($metodo, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}

$cfg = aida_config();
$entrega = new Entrega(aida_db(), (string)($cfg['archivos_raiz'] ?? ''));
$a = $entrega->buscar((int)($_GET['id'] ?? 0));
$decision = Entrega::decidir($a, $interno);
$ruta = $decision === Entrega::SERVIR ? $entrega->rutaSegura($a) : null;

$pagina = static function (int $codigo, string $vista, array $datos = []): never {
    http_response_code($codigo);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-cache');
    header('X-Robots-Tag: noindex');
    extract($datos, EXTR_SKIP);
    require dirname(__DIR__) . "/vistas/publico/$vista.php";
    exit;
};

if ($decision === Entrega::A_PEDIDO) {
    // Restringido: la ficha es pública, así que se muestran código y título. «A revisar» todavía no se publica: sin datos.
    $pagina(403, 'a_pedido', ['a' => $a['acceso'] === 'restringido' ? $a : null]);
}
if ($decision !== Entrega::SERVIR || $ruta === null) {
    if ($decision === Entrega::SERVIR) {
        error_log("[aida-entrega] archivo {$a['id']} ({$a['ruta']}) en la base pero no en el disco");
    }
    $pagina(404, 'no_encontrado');
}

$descargar = !empty($_GET['descargar']);
$completo = $entrega->enviar($a, $ruta, $descargar, $interno);
// Se cuenta una vez por apertura (no por cada tramo que pide el visor) y no se cuentan las consultas del equipo.
if ($completo && !$interno && !isset($_SERVER['HTTP_RANGE'])) {
    $entrega->registrarUso($a, $descargar ? 'descarga' : 'vista');
}
