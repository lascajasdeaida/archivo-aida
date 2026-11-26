# Sitio público — cómo está programado

Diseño y comportamiento aprobados: `docs/ux/sitio-publico.md` (UX) y `docs/diseno/` (maquetas y componentes).
Instalación en el servidor y en la Mac: [`despliegue.md`](despliegue.md).

## Piezas

| Pieza | Qué hace |
|---|---|
| `public/sitio.php` | Front controller. Saca la ruta de la URL (o de `?ruta=`) y llama a `SitioPublico::despachar()`. |
| `public/.htaccess` | Apache: lo que existe se sirve tal cual; el resto va a `sitio.php`. |
| `bin/router.php` | Lo mismo para el servidor embebido: `php -S 127.0.0.1:8100 -t public bin/router.php`. |
| `src/Web/SitioPublico.php` | Controladores: una función por página, encabezados, CSRF del formulario, 404/410, sitemap. |
| `src/Web/Enlaces.php` | Todas las URL del sitio. Las plantillas nunca arman una URL a mano. |
| `src/Sitio.php` | Datos de las páginas (solo lectura). Modo público: lo que no se ve no existe. |
| `src/Buscador.php` | Texto + filtros + facetas (modo público). |
| `src/Consultas.php` | Formulario de consulta: validación, guardado, seguimiento en el backoffice. |
| `vistas/publico/*.php` | Plantillas. Todas terminan en `_sitio.php` (cabecera, pie, metadatos). |
| `public/sitio/estilo.css`, `sitio.js`, `explorar.js` | Estilos de Diseño web (más un bloque final de Programación) y comportamiento. |

## Rutas

| URL legible | Sin reescritura | Plantilla | Notas |
|---|---|---|---|
| `/` | `sitio.php` | `inicio.php` | Si no hay cajas publicadas: «Todavía no hay cajas publicadas». |
| `/explorar?q=…&tipo=…` | `sitio.php?ruta=explorar&…` | `explorar.php` + `_explorar_*.php` | `noindex,follow`. Parámetros abajo. |
| `/explorar.json?…` | `sitio.php?ruta=explorar.json&…` | (JSON con los mismos fragmentos) | Lo usa `explorar.js`. |
| `/cajas` | `?ruta=cajas` | `cajas.php` | `?q=` y `?orden=numero|nombre|digit`. |
| `/caja/2-la-historia-oficial` | `?ruta=caja/2-la-historia-oficial` | `caja.php` | Manda el número; otro texto o `/caja/2` → 301. |
| `/registro/C2R5` | `?ruta=registro/C2R5` | `ficha.php` | `c2r5`, `C2R05` → 301. Existe pero no se publica → 410 (`no_disponible.php`). |
| `/consulta/C2R5` | `?ruta=consulta/C2R5` | `consulta.php` | GET y POST. `?motivo=original` cuando no es restringido. `noindex`. |
| `/consulta/C2R5/enviada?n=&k=` | ídem | `consulta_enviada.php` | Después del POST (303). `k` = firma de `n`. |
| `/sobre` | `?ruta=sobre` | `sobre.php` | `/sobre-el-archivo` → 301. Marcadores «Texto a definir por el cliente». |
| `/sitemap.xml`, `/robots.txt` | ídem | — | Sitemap: inicio, cajas, sobre, cajas publicadas y registros visibles. |
| cualquier otra | | `pagina_404.php` | Con buscador. Errores: `error.php` (500). |

Documentos: `archivo.php?id=…` (sin cambios, `src/Entrega.php`).

### Parámetros de Explorar

`q`, `tipo` y `descriptor` (slug del término, lista con coma), `decada` (`1980,sin-fecha`), `desde`/`hasta`, `etapa` (slug),
`caja` (números), `disponible` (`digital|ficha`), `acceso` (`libre|a-pedido`), `orden`
(`relevancia|caja|anio-asc|anio-desc|digital`), `pagina`, `primera`.
Dentro de una faceta, O; entre facetas, Y. Un formulario sin JS manda `tipo[]=…`: el servidor redirige a la forma con comas.
`pagina=3` sin JS muestra los resultados 41–60; con JS, «Mostrar más» agrega y guarda `primera=1&pagina=3`, y al recargar se
arma la lista acumulada (hasta 10 páginas). El código exacto (`C14R3`) en `q` va directo a la ficha.

### Con y sin JS

Todo funciona sin JS: el panel de filtros es un formulario GET (en celular se muestra en la página), los chips, «Limpiar» y
«Mostrar más» son enlaces. Con JS (`explorar.js`): filtros, orden y rango sin recargar (`explorar.json` devuelve facetas, chips,
resultados y paginación ya armados con las mismas plantillas), «Mostrar más» agrega y pone el foco en el primero nuevo, panel de
filtros como diálogo a pantalla completa en celular (foco atrapado, Esc, foco devuelto), conteo anunciado en `aria-live` a los
600 ms, eventos GA4 de `docs/ux/sitio-publico.md` §7 en `dataLayer` (nunca datos personales).

## Encabezados

`_sitio.php` recibe `$meta` (`descripcion`, `canonical`, `og_tipo`) y `$robots`: `<title>`, `meta description`, `canonical` y Open
Graph básico (sin imagen: falta el logo en .ai). El dominio sale de `config['sitio']['url']` (por defecto
`https://lascajasdeaida.com.ar`); canonical y sitemap siempre usan la URL legible.

## Consulta a pedido

Campos: nombre, correo, institución (opcional), uso previsto (lista), modalidad, mensaje, condiciones. Validación en el servidor
(`Consultas::validar`), errores junto a cada campo y resumen arriba con foco. Anti-spam: campo trampa `sitio_web` (si viene
completo se muestra la confirmación sin guardar), `_i` = instante firmado (mínimo 3 s, máximo 3 h), `_t` = cookie `aida_form`
(CSRF sin sesión) y 5 envíos por hora por IP. El secreto de las firmas: `config['secreto']` o `var/secreto` (se crea solo).

**Punto de extensión para avisar por correo:** implementar `Aida\NotificadorConsultas::nueva(array $consulta)` y configurarla en
`config['consultas']['notificador']`. Se llama después de guardar; si falla, la consulta igual queda guardada.

## Cómo agregar una página

1. En `SitioPublico::despachar()`, sumar la ruta al `match` (ej. `$ruta === 'prensa' => self::prensa()`).
2. Escribir el método: arma los datos y llama a `self::pagina('prensa', [...datos, 'meta' => ['descripcion' => …, 'canonical' => Enlaces::absoluta('prensa')], 'seccion' => …])`.
3. Crear `vistas/publico/prensa.php`: definir `$tituloPagina`, armar el HTML entre `ob_start()` y `$contenido = ob_get_clean();`
   y terminar con `require __DIR__ . '/_sitio.php';`. Todas las URL con `$enl` (`$enl->ruta('prensa')`).
4. Si va en el menú, agregarla en `_sitio.php`; si es indexable, en `SitioPublico::sitemap()`.
5. Formularios GET: si `!$enl->reescritura`, agregar `<input type="hidden" name="ruta" value="…">`.
6. Probar con y sin reescritura (`/prensa` y `/sitio.php?ruta=prensa`), a 390 y 1366, y con axe.
