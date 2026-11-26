<?php
declare(strict_types=1);

/**
 * Sitio público del archivo. Front controller: todas las páginas pasan por acá (docs/sitio-publico.md).
 *
 *  - Con reescritura (producción, public/.htaccess o el bloque de nginx de docs/despliegue.md):
 *    /explorar, /caja/2-la-historia-oficial, /registro/C2R5, /consulta/C2R5, /sobre, /sitemap.xml…
 *  - Sin reescritura: sitio.php?ruta=explorar&q=… (o sitio.php/explorar donde el servidor pase PATH_INFO).
 *
 * Se llama sitio.php y no index.php para convivir con el WordPress de Local en la Mac del PM, que tiene su propio
 * public/index.php (docs/despliegue.md, “Convivir con WordPress”).
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Aida\Web\SitioPublico;

$ruta = $_GET['ruta'] ?? null;
if (!is_string($ruta)) {
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/sitio.php');
    $camino = rawurldecode((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
    if ($camino === $script || str_starts_with($camino, $script . '/')) {
        $ruta = substr($camino, strlen($script));                   // /sitio.php/explorar
    } else {
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');  // instalación en subcarpeta
        $ruta = $dir !== '' && str_starts_with($camino, $dir . '/') ? substr($camino, strlen($dir)) : $camino;
    }
}

SitioPublico::despachar($ruta);
