# Lanzamiento: del dominio provisorio a lascajasdeaida.com.ar

Hoy todo corre en `http://lascajasdeaida.com.ar.vcct10010.avnam.net/` (sitio en la raíz, archivo en `/archivo/`,
backoffice en `/archivo/admin/`), sin indexar. El día del lanzamiento, en este orden:

## Antes (días previos)

- [ ] **Turnstile con claves reales**: widget en Cloudflare con los hosts `lascajasdeaida.com.ar`, `lascajasdeaida.com`
      y el provisorio. Cargar las claves (ver «Claves de Turnstile» abajo). Las de prueba dejan pasar todo.
- [ ] **GA4**: pasar el filtro **Internal Traffic** de Prueba a **Activo** (es irreversible para lo ya excluido).
- [ ] **Eventos clave** que falten en GA4 (Administrar › Eventos › Eventos recientes › estrella): `descargar_archivo`,
      `enviar_consulta`, `enviar_formulario_muestra`, `descarga_catalogo`. Aparecen en la lista recién cuando ocurren una vez.
      `ver_archivo` ya está marcado (21/11/2026).
- [ ] Respuestas del cliente cargadas (etapas, textos, accesos) y PR revisado por Omar y fusionado.
- [ ] Backup: `bash bin/backup.sh`.
- [ ] **lascajasdeaida.com como alias de lascajasdeaida.com.ar** (pedido de Andy, 27/11/2026). En el VPS hoy son dos
      servidores virtuales separados. Revisar qué tiene el de `.com` (respaldarlo si tiene algo), darlo de baja y volver
      a crearlo en Virtualmin como **alias** de `lascajasdeaida.com.ar`, con su certificado. El `.htaccess` que escribe
      `bin/lanzamiento.sh` ya lleva todo a `https://lascajasdeaida.com.ar` (sin www), así que el alias redirige solo.
      Apuntar el DNS de los dos dominios al VPS.

## El día

1. **DNS** (donde esté registrado el dominio, NIC.ar): registros `A` de `lascajasdeaida.com.ar` y `www` → `142.44.144.198`.
   Lo mismo para `lascajasdeaida.com` si se usa. Bajar antes el TTL a 300 para que el cambio sea rápido.
2. **Virtualmin**: agregar `www.lascajasdeaida.com.ar` (y `.com`) como alias del servidor virtual, si no están.
3. **Certificado**: Virtualmin › Servidor virtual › Configuración de servidor › SSL › Let's Encrypt, para todos los nombres
   anteriores. Requiere que el DNS ya apunte (paso 1).
4. **Script** (como el usuario del dominio, en `~/archivo-app`):
   ```
   bash bin/lanzamiento.sh --simular      # revisar lo que va a cambiar
   bash bin/lanzamiento.sh --aplicar      # con --http si el certificado todavía no está
   ```
   Cambia `config.php` (URLs del archivo y del backoffice), `.htaccess` (saca el noindex del dominio definitivo y redirige
   a https sin www), `robots.txt` (permite indexar, excluye `/archivo/admin/`) y la imagen para compartir.
   Volver atrás: `bash bin/lanzamiento.sh --revertir`.
5. **Generador del sitio institucional** (`_build/generar.py`): `URL_COMPARTIR = URL` para que las próximas publicaciones
   no vuelvan al dominio provisorio.
6. **Pruebas**: inicio, una página interior, `/archivo/`, una ficha, el visor de un PDF, ingreso al backoffice,
   «Olvidé mi contraseña» (que el enlace llegue con el dominio nuevo), formulario de la Muestra, consulta de un documento.
   Ver que `curl -I https://lascajasdeaida.com.ar/` ya no tenga `X-Robots-Tag`.
7. **Google Search Console**: dar de alta la propiedad del dominio y enviar `https://lascajasdeaida.com.ar/sitemap.xml`.
8. **Vista previa al compartir**: refrescar el link en el depurador de Facebook (https://developers.facebook.com/tools/debug/).

## Después

- Mail del servidor: con el dominio apuntando, revisar que los correos del backoffice no caigan en spam (SPF ya incluye
  avnam.net; sumar DKIM desde Virtualmin › Configuración de correo).
- Opcional: poner el dominio detrás de Cloudflare (proxy, reglas de WAF y Bot Fight Mode). Turnstile no protege el sitio
  entero, solo los formularios.
- Hosting definitivo de los PDF (hoy salen de Drive, provisorio).

## Claves de Turnstile

La clave del sitio es pública; la secreta no tiene que pasar por chats ni mails.

1. Cloudflare › Turnstile › Add widget. Nombre: Las cajas de Aída. Hostnames: los de arriba. Modo: Managed.
2. Guardar las dos claves en un archivo `turnstile.txt` en la carpeta `deploy` del proyecto, con dos líneas:
   `sitio=<site key>` y `secreto=<secret key>`.
3. Desde ahí se cargan en `config/config.php` del servidor (reemplazan las de prueba) y la del sitio va a
   `TURNSTILE_SITIO` de `_build/generar.py`. Después se borra `turnstile.txt`.
