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
$email = $email !== null ? Aida\Usuarios::normalizarEmail($email) : null;
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
$error = Aida\Usuarios::errorClave($clave, $clave, $email);
if ($error) {
    fwrite(STDERR, $error . "\n");
    exit(1);
}
// Misma lógica que el backoffice: al actualizar una cuenta existente se anulan sus enlaces pendientes y se cierran
// sus sesiones abiertas (cambian la contraseña y quizá el perfil).
$us = new Aida\Usuarios(aida_db());
$u = $us->porEmail($email);
if ($u) {
    $us->actualizar((int)$u['id'], ['email' => $email, 'nombre' => (string)($nombre ?? $u['nombre']), 'rol' => $rol, 'activo' => 1]);
    $us->cambiarClave((int)$u['id'], $clave); // anula enlaces y cierra sesiones
    echo "Usuario $email actualizado ($rol). Sus sesiones abiertas se cerraron.\n";
} else {
    $id = $us->crear(['email' => $email, 'nombre' => (string)$nombre, 'rol' => $rol, 'activo' => 1]);
    $us->cambiarClave($id, $clave);
    echo "Usuario $email listo ($rol).\n";
}
