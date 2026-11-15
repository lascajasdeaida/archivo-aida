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
    // Backups de la base (bin/backup.sh). Opcional: por defecto ../backups-aida y 8 copias
    // 'backup' => ['dir' => '/home/usuario/backups-aida', 'conservar' => 8],
    // Excel subidos desde el backoffice (Importaciones). Fuera de public/. Por defecto: var/importaciones del repo.
    // 'importaciones_dir' => '/var/www/aida-privado/importaciones',
    // Tamaño máximo del Excel que se puede subir, en MB (también lo limitan upload_max_filesize y post_max_size de PHP).
    // 'importar_max_mb' => 40,
];
