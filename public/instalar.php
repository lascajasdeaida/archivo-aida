<?php
declare(strict_types=1);

/**
 * Instalador para un entorno de prueba (Local, VPS de staging).
 * Crea las tablas, carga datos/demo.sql si existe y da de alta al primer administrador.
 * Se bloquea solo cuando ya hay un administrador. Borrar este archivo después de usarlo.
 */

require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/Web/ayudas.php';

$raiz = dirname(__DIR__);
$msg = null;
$hecho = false;

try {
    $db = aida_db();
} catch (Throwable $e) {
    http_response_code(500);
    exit('No se pudo conectar a la base. Revisá config/config.php. Detalle: ' . e($e->getMessage()));
}

$tablas = (int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'usuario'")->fetchColumn();
$hayAdmin = $tablas && (int)$db->query("SELECT COUNT(*) FROM usuario WHERE rol = 'admin'")->fetchColumn() > 0;

if ($hayAdmin) {
    $hecho = true;
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $clave = (string)($_POST['clave'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'El correo no es válido.';
    } elseif (mb_strlen($clave) < 10) {
        $msg = 'La contraseña tiene que tener al menos 10 caracteres.';
    } else {
        try {
            $ejecutar = function (string $archivo) use ($db): int {
                $sql = (string)file_get_contents($archivo);
                $sql = preg_replace('~^/\*M?!.*?\*/;?\s*$~m', '', $sql);
                $n = 0;
                foreach (preg_split('/;\s*\n/', $sql) as $sentencia) {
                    $s = trim(preg_replace('/^--.*$/m', '', $sentencia));
                    if ($s !== '') {
                        $db->exec($s);
                        $n++;
                    }
                }
                return $n;
            };
            if (!$tablas) {
                $ejecutar("$raiz/db/schema.sql");
            }
            $datos = 0;
            if (is_file("$raiz/datos/demo.sql") && !(int)$db->query('SELECT COUNT(*) FROM registro')->fetchColumn()) {
                $db->exec('SET FOREIGN_KEY_CHECKS = 0');
                $datos = $ejecutar("$raiz/datos/demo.sql");
                $db->exec('SET FOREIGN_KEY_CHECKS = 1');
            }
            $db->prepare("INSERT INTO usuario (email, password_hash, nombre, rol) VALUES (?,?,?,'admin')")
               ->execute([$email, password_hash($clave, PASSWORD_DEFAULT), $nombre ?: null]);
            $hecho = true;
        } catch (Throwable $e) {
            $msg = 'Falló la instalación: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Instalar · Las cajas de Aída</title><link rel="stylesheet" href="admin/estilo.css"></head>
<body class="pagina-login">
<div class="caja-login">
  <p class="eyebrow">Archivo · Instalación</p>
  <h1>Las cajas de <em>Aída</em></h1>
  <?php if ($hecho): ?>
    <div class="aviso aviso-ok">Listo. La base está instalada y hay un administrador.</div>
    <?php $c = (int)$db->query('SELECT COUNT(*) FROM caja')->fetchColumn(); $r = (int)$db->query('SELECT COUNT(*) FROM registro')->fetchColumn(); ?>
    <p class="nota"><?= $c ?> cajas y <?= $r ?> registros cargados.</p>
    <p><a class="btn btn-primario" href="admin/">Entrar al backoffice</a></p>
    <p class="nota">Por seguridad, borrá <code>instalar.php</code> cuando termines.</p>
  <?php else: ?>
    <p class="nota">Crea las tablas, carga los datos de prueba y el primer usuario administrador.</p>
    <?php if ($msg): ?><div class="aviso aviso-error"><?= e($msg) ?></div><?php endif ?>
    <form method="post">
      <label>Tu nombre<input type="text" name="nombre" value="<?= e($_POST['nombre'] ?? '') ?>"></label>
      <label>Correo<input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
      <label>Contraseña (mínimo 10 caracteres)<input type="password" name="clave" required minlength="10" autocomplete="new-password"></label>
      <button class="btn btn-primario">Instalar</button>
    </form>
  <?php endif ?>
</div>
</body>
</html>
