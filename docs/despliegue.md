# Despliegue del sitio público y el backoffice

El servidor web apunta a `public/` (el resto del repo no queda expuesto). En `public/` conviven:

- `sitio.php` — sitio público (front controller), `archivo.php` — entrega de documentos, `admin/` — backoffice,
  `sitio/` — CSS y JS del sitio.

## Producción: Apache

`public/.htaccess` ya trae lo necesario (`DirectoryIndex sitio.php` y la reescritura). Hace falta `mod_rewrite` y
`AllowOverride All` (o al menos `FileInfo Options Indexes`) en el virtual host. Sin `mod_rewrite` el sitio funciona igual con
`sitio.php?ruta=…`.

## Producción: nginx

```nginx
server {
    server_name lascajasdeaida.com.ar;
    root /var/www/aida/public;
    index sitio.php;

    location ~ /\. { deny all; }

    # Lo que existe se sirve tal cual (admin/, sitio/*.css, archivo.php); lo demás va al sitio
    location / {
        try_files $uri $uri/ /sitio.php$is_args$args;
    }

    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;   # ajustar a la versión de PHP del servidor
    }
}
```

`sitio.php` lee la ruta de `REQUEST_URI`, así que no hace falta pasar `?ruta=`.

## Convivir con WordPress (Mac del PM, Local)

En la Mac, `public/` es también la raíz de un WordPress de Local, con su propio `public/index.php` (y sus `wp-*`), sin
versionar. Por eso **el sitio no usa `public/index.php`**: si el repo trajera uno, `git pull` se negaría a pisar el del WordPress
(o lo pisaría). El front controller se llama `public/sitio.php` y no choca con nada de WordPress.

Cómo se usa en cada lugar:

| Dónde | Cómo se entra | Enlaces |
|---|---|---|
| Producción (Apache o nginx de arriba) | `https://lascajasdeaida.com.ar/explorar` | legibles |
| Mac con Local, sin tocar nginx | `http://<sitio>.local/sitio.php` | `sitio.php?ruta=explorar&…` (se detecta solo) |
| Mac con Local, con URLs legibles bajo un prefijo | ver abajo | legibles |
| Desarrollo con PHP embebido | `php -S 127.0.0.1:8100 -t public bin/router.php` | legibles (y `sitio.php?ruta=` también anda) |

En la Mac **no hace falta configurar nada**: entrando por `sitio.php` todos los enlaces del sitio se arman con `?ruta=`, y
WordPress sigue respondiendo en `/`. Para que el botón «Ver en el sitio» del backoffice use esa forma, en `config/config.php`:

```php
'sitio' => ['reescritura' => false],
```

Si se quieren URLs legibles en la Mac sin sacar a WordPress de `/`, se puede servir el sitio bajo un prefijo agregando en
Local (`conf/nginx/site.conf.hbs`, antes del `location /` de WordPress) — opcional, y con `reescritura` sin definir:

```nginx
location = /sitio.php { try_files $uri =404; include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root/sitio.php; fastcgi_pass php; }
location ~ ^/(explorar|explorar\.json|cajas|caja/|registro/|consulta/|sobre|sitemap\.xml) {
    try_files /nada /sitio.php$is_args$args;
}
```

(`fastcgi_pass php` es el upstream que define Local; revisar el nombre en su `site.conf.hbs`.) Ojo: así `/sitemap.xml` y `/sobre`
dejan de ser de WordPress en esa máquina.

## Al instalar o actualizar

1. `php bin/migrar.php` (o «Base de datos» en el backoffice): la 005 crea `consulta`.
2. En `config/config.php`: `sitio.url` si el dominio no es el de producción; `secreto` (64 caracteres) o permiso de escritura
   en `var/` para que se cree `var/secreto`.
3. Publicar las cajas desde el backoffice (Cajas → caja → «Publicar la caja»). Sin ninguna publicada el inicio lo avisa.
4. Dar de alta el sitemap (`/sitemap.xml`) en Search Console cuando haya cajas publicadas.
