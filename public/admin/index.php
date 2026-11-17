<?php
declare(strict_types=1);

/**
 * Backoffice del archivo. Front controller: ?r=ruta
 * Apuntar el servidor web a public/ (el resto del repo no debe quedar expuesto).
 */

require dirname(__DIR__, 2) . '/src/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Web/ayudas.php';

use Aida\Web\App;
use Aida\Web\Rutas;

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

App::iniciarSesion();
App::revalidarSesion();

$ruta = (string)($_GET['r'] ?? (App::usuario() ? 'cajas' : 'login'));
$publicas = ['login', 'olvide', 'restablecer'];
$rutas = [
    'login', 'salir', 'cajas', 'cajas_unificar', 'caja', 'caja_nueva', 'caja_guardar', 'caja_baja', 'caja_restaurar',
    'registro', 'registro_guardar', 'registro_nuevo', 'registro_baja', 'registro_restaurar',
    'campo_desbloquear', 'origen_excel', 'archivo_baja', 'archivo_restaurar', 'deshacer',
    'notificaciones', 'validar', 'procesos', 'proceso_deshacer', 'importar', 'importar_confirmar', 'importar_descartar', 'correccion', 'correccion_aplicar', 'correccion_descartar', 'vocabularios', 'vocab_accion', 'vocab_curar', 'vocab_informe', 'curaduria_deshacer', 'base', 'vista_previa',
    'caja_publicar', 'consultas', 'consulta', 'consulta_guardar',
    'almacenamiento', 'drive_listado_aplicar', 'drive_listado_descartar', 'drive_reescanear',
    'usuarios', 'usuario', 'usuario_guardar', 'usuario_enlace', 'olvide', 'restablecer', 'cuenta',
];

if (!in_array($ruta, $rutas, true)) {
    http_response_code(404);
    App::render('error', ['titulo' => 'Página inexistente', 'mensaje' => 'La dirección no corresponde a ninguna sección.']);
    exit;
}
if (!in_array($ruta, $publicas, true) && !App::usuario()) {
    App::redirigir('login');
}

try {
    Rutas::$ruta();
} catch (Throwable $e) {
    error_log('[aida-bo] ' . $e);
    http_response_code(500);
    App::render('error', ['titulo' => 'Error', 'mensaje' => 'Algo falló al procesar el pedido. Ya quedó registrado; si se repite, avisá al equipo técnico.']);
}
