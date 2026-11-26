<?php
declare(strict_types=1);

/**
 * Crea o actualiza un usuario del backoffice.
 *
 *   php bin/crear-usuario.php correo@dominio.com "Nombre" rol
 *
 * Roles: admin, editor, data_entry, qa. La contraseña se pide por consola (no queda en el historial de la terminal).
 */

require dirname(__DIR__) . '/src/bootstrap.php';

[$_, $email, $nombre, $rol] = $argv + [null, null, null, 'editor'];
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($rol, ['admin', 'editor', 'data_entry', 'qa'], true)) {
    fwrite(STDERR, "Uso: php bin/crear-usuario.php correo \"Nombre\" admin|editor|data_entry|qa\n");
    exit(1);
}
$clave = getenv('AIDA_CLAVE') ?: null;
if ($clave === null) {
    fwrite(STDOUT, 'Contraseña (mínimo 10 caracteres): ');
    system('stty -echo 2>/dev/null');
    $clave = trim((string)fgets(STDIN));
    system('stty echo 2>/dev/null');
    fwrite(STDOUT, "\n");
}
if (mb_strlen($clave) < 10) {
    fwrite(STDERR, "La contraseña tiene que tener al menos 10 caracteres.\n");
    exit(1);
}
aida_db()->prepare('INSERT INTO usuario (email, password_hash, nombre, rol) VALUES (?,?,?,?)
                    ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), nombre = VALUES(nombre), rol = VALUES(rol), activo = 1')
         ->execute([$email, password_hash($clave, PASSWORD_DEFAULT), $nombre, $rol]);
echo "Usuario $email listo ($rol).\n";
