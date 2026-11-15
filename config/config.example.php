<?php
// Copiar como config/config.php y completar. config.php no se sube al repositorio.
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'aida',
        'user' => 'aida',
        'pass' => 'cambiar',
        // 'socket' => '/ruta/al/mysqld.sock',   // si se conecta por socket (p. ej. Local), en lugar de host y puerto
    ],
    // Carpeta raíz donde están las carpetas de cajas (C1/, C2/, …) con los PDF
    'archivos_raiz' => '/var/www/aida-archivos',
];
