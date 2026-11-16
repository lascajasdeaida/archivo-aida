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
    // De dónde salen los archivos digitalizados (docs/despliegue.md «Origen de los archivos»).
    //   disco: carpeta propia con las carpetas de cajas (C1/, C2/, …); se entregan desde el servidor (como siempre).
    //   drive: provisorio (16/11/2026). Los PDF quedan en el Drive compartido del cliente; el sitio muestra el visor de Drive
    //          y la descarga redirige a Drive. Los id salen del listado de Drive (bin/drive-listado.php o Almacenamiento).
    'archivos' => [
        'origen' => 'disco',
        'raiz'   => '/var/www/aida-archivos',          // solo disco
        // 'drive' => [
        //     'listado'      => '/var/www/aida-privado/drive-listado.tsv', // copia del último listado (por defecto var/drive/listado.tsv)
        //     'visor_max_mb' => 100,   // más pesados: solo descarga (el visor de Drive falla con PDF grandes)
        // ],
        // 'cajas_total' => 70,         // para la proyección del informe de espacio (por defecto 70)
    ],
    // Forma anterior, sigue valiendo: sin 'archivos', el origen es disco con esta carpeta.
    // 'archivos_raiz' => '/var/www/aida-archivos',
    // Backups de la base (bin/backup.sh). Opcional: por defecto ../backups-aida y 8 copias
    // 'backup' => ['dir' => '/home/usuario/backups-aida', 'conservar' => 8],
    // Excel subidos desde el backoffice (Importaciones). Fuera de public/. Por defecto: var/importaciones del repo.
    // 'importaciones_dir' => '/var/www/aida-privado/importaciones',
    // Tamaño máximo del Excel que se puede subir, en MB (también lo limitan upload_max_filesize y post_max_size de PHP).
    // 'importar_max_mb' => 40,
    // Sitio público (docs/despliegue.md). url: dominio para canonical, Open Graph, sitemap y citas.
    // reescritura: forzar la forma de los enlaces (true = /explorar; false = sitio.php?ruta=explorar). Sin definir, el sitio
    // la detecta sola; el backoffice («Ver en el sitio») la toma de acá y por defecto usa URLs legibles.
    // 'sitio' => ['url' => 'https://lascajasdeaida.com.ar', 'reescritura' => false],
    // Secreto para firmar el formulario de consulta (64 caracteres al azar). Si falta, se crea solo en var/secreto.
    // 'secreto' => 'cambiar-por-64-caracteres-al-azar',
    // Consultas a pedido: clase que avisa cuando llega una (implementa Aida\NotificadorConsultas). Por ahora ninguna.
    // 'consultas' => ['notificador' => null],
];
