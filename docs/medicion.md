# Medición (GTM + GA4) — sitio institucional y archivo

Implementación del plan de medición (doc del Project `AIDA_plan_medicion_ga4.md`, sep. 2026), adaptada al modelo real del archivo.
Estadísticas · 16/11/2026.

- **Un contenedor y una propiedad para las dos superficies:** GTM `GTM-M83WNDH5`, GA4 `G-5EGY68N1ZX` (propiedad 378294927).
  El archivo se distingue por `tipo_contenido: 'archivo'` y `content_group` / `rama: 'Archivo'`. El institucional manda
  `tipo_contenido: 'sitio_institucional'` y `content_group` = `rama` (Inicio, Catálogo, Muestra, Conservación, Digitalización,
  Documental, Contacto).
- **Nada que identifique personas.** Registros por su código (`C2R5`), cajas por número, vocabularios por nombre. Nunca nombre,
  correo, teléfono ni mensaje de un formulario. Las búsquedas se limpian en el navegador (`[correo]`, `[numero]` para 7 o más
  cifras) y se cortan a 100 caracteres. Los enlaces de correo no mandan la dirección.
- Los parámetros van en snake_case y en castellano, salvo los estándar de GA4 (`search_term`, `content_type`, `item_id`).

## 1. Prueba y producción

Hoy todo está publicado en un dominio provisorio, `lascajasdeaida.com.ar.vcct10010.avnam.net`. El contenedor es el mismo en los
dos entornos y no se toca. Lo decide cada página, en el navegador, **antes de cargar GTM**:

| Host | Lo que se empuja al dataLayer con el contexto de la página |
|---|---|
| `lascajasdeaida.com.ar`, `www.lascajasdeaida.com.ar`, `lascajasdeaida.com`, `www.lascajasdeaida.com` | `entorno: 'produccion'` (sin `traffic_type` ni `debug_mode`) |
| cualquier otro (el provisorio, `127.0.0.1`, la Mac) | `entorno: 'prueba'`, `traffic_type: 'internal'`, `debug_mode: true` |
| archivo con sesión del backoffice abierta, en cualquier host | además `traffic_type: 'internal'` |

- En producción **no** se manda `debug_mode: false`: GA4 toma cualquier `debug_mode` como depuración.
- Los hosts están en `src/Web/Medicion.php` (`HOSTS_PRODUCCION`, se pueden pisar con `analitica.hosts_produccion`) y en
  `institucional/_build/generar.py` (`HOSTS_PRODUCCION`). Si el dominio definitivo es otro, se cambia ahí.
- Para que GA4 lo respete hacen falta los pasos de GTM y GA4 de las secciones 6 y 7.

## 2. Archivo (PHP)

### Carga

- `config/config.php`: `'analitica' => ['gtm' => 'GTM-M83WNDH5']`. **Sin esa clave no se imprime nada**: ni GTM, ni dataLayer,
  ni `public/sitio/medicion.js`, y `explorar.js` / `sitio.js` no empujan nada.
- `src/Web/Medicion.php` arma el contexto y el snippet. La única que lo usa es `SitioPublico::pagina()`. El layout
  `vistas/publico/_sitio.php` imprime el `<script>` de contexto y GTM en el `<head>`, el `<noscript>` al abrir el `<body>` y
  `medicion.js`.
- **El backoffice y su vista previa nunca miden.** `Rutas::vista_previa` usa las mismas plantillas pero no pasa `$medicion`, así
  que no hay GTM, no hay `data-medicion` y no se carga `medicion.js`. Lo cubre la prueba.
- `public/sitio/medicion.js` es lo único que escribe en el `dataLayer`. Expone `window.aidaMedir(evento, datos)` (limpia los
  textos y agrega `registro_codigo` / `caja_numero` de la página).

### Contexto por página (push sin `event`, antes de GTM)

Siempre: `sitio: 'lascajasdeaida.com.ar'`, `tipo_contenido: 'archivo'`, `rama: 'Archivo'`, `content_group: 'Archivo'`,
`seccion`, `entorno` (y `traffic_type`, `debug_mode` según la sección 1).

| `seccion` | Página | Además |
|---|---|---|
| `inicio` | Inicio | |
| `explorar` | Explorar | |
| `cajas` | Las 70 cajas | |
| `caja` | Una caja | `caja_numero`, `etapa` (si la caja tiene) |
| `registro` | Ficha | `registro_codigo`, `caja_numero`, `tipos_documento` (`Carta\|Guion`), `acceso_documento` (`libre`, `a_pedido`, `solo_ficha`), `digitalizado` (`si`, `no`), `etapa` |
| `consulta` | Formulario y confirmación | lo de la ficha + `tipo_consulta` (`a_pedido`, `original`) |
| `sobre` | Sobre el archivo | |
| `error` | 404, 410, 500 | `tipo_error` |

`etapa` sale de `caja_periodo`. Hoy la tabla está vacía y no se manda: el plan hablaba de «período / ámbito / soporte», pero el
modelo real tiene etapas (sin asignar), vocabularios, cajas y registros.

### Eventos del archivo

| Evento | Cuándo | Parámetros |
|---|---|---|
| `buscar_archivo` | Una vez por término. Al confirmar (Enter), o **1,5 s después** de la última respuesta mientras se escribe (Explorar busca a los 180 ms de cada pausa: eso no cuenta). También al llegar a Explorar con `?q=` desde el inicio, una caja o un enlace. Volver atrás o recargar la misma búsqueda no la repite | `search_term`, `resultados`, `origen_busqueda` (`inicio`, `caja`, `explorar`, `explorar_escritura`, `filtros`, `enlace`), `filtros_activos` (`tipo:carta\|decada:1980`), `cantidad_filtros`; con un código exacto (`C14R3`, va directo a la ficha) `tipo_busqueda: 'codigo'` |
| `busqueda_sin_resultados` | Una vez por combinación de término y filtros que da 0 | `search_term`, `origen_busqueda`, `filtros_activos`, `cantidad_filtros` |
| `aplicar_filtro` | Tilde de una faceta o rango de años | `filtro` (`tipo`, `decada`, `caja`, `descriptor`, `disponible`, `acceso`, `etapa`, `anio_rango`), `filtro_valor` (slug, o `1980-1990`) |
| `quitar_filtro` | Destilde, chip, sugerencia de «sin resultados», Limpiar | `filtro` (o `busqueda`, `todos`), `filtro_valor`, `ubicacion_enlace` (`chip`, `sin_resultados`) |
| `ordenar_resultados` | Cambio de orden | `orden_resultados` (`relevancia`, `caja`, `anio-asc`, `anio-desc`, `digital`) |
| `paginar_resultados` | Mostrar 20 más | `pagina`, `resultados` |
| `abrir_filtros` | Panel de filtros en el celular | — |
| `select_content` | Clic en un resultado | `content_type: 'registro'`, `item_id`, `registro_codigo`, `origen_busqueda` |
| `error_resultados` | Falla al cargar resultados | `tipo_error` (`conexion`, `cargar_mas`) |
| `ver_caja` | Página de una caja | `caja_numero`, `documentos` |
| `ver_registro` | Ficha | lo del contexto de la ficha |
| `ver_archivo` | Clic en «Ver documento» / «Ver parte N» (abre el visor) | `registro_codigo`, `caja_numero`, `archivo_parte`, `archivo_formato`, `origen_archivo` (`drive`, `disco`), `peso_mb`, `ubicacion_enlace` (`ficha`) |
| `descargar_archivo` | Clic en «Descargar» de la ficha o del visor (la parte abierta) | lo mismo, `ubicacion_enlace` (`ficha`, `visor`) |
| `compartir_registro` | Copiar cita / copiar enlace, cuando se copió | `metodo_compartir` (`copiar_cita`, `copiar_enlace`), `registro_codigo`, `caja_numero` |
| `iniciar_consulta` | Primer foco en el formulario de consulta | `registro_codigo`, `caja_numero` |
| `error_consulta` | El formulario volvió con errores | `campos_error` (nombres de campos: `correo,uso`), `registro_codigo` |
| `enviar_consulta` | Confirmación de la consulta (después de que el servidor la guardó). Una vez por envío: recargar no la repite | `registro_codigo`, `caja_numero`, `tipo_consulta` |

- **Ver y descargar se miden en el clic**, porque el archivo se entrega por redirección (`archivo.php` → visor `/preview` o
  descarga de Drive) y la página nunca ve la descarga. Los datos van en `data-medicion` / `data-medicion-datos` del botón.
- Sin JS no hay eventos del navegador, pero `evento_uso` cuenta igual (sección 4).
- Cambios de nombre respecto de lo que había en `explorar.js` y `sitio.js`: `search` → `buscar_archivo`, `filtro_aplicado` /
  `filtro_quitado` / `filtros_limpiados` → `aplicar_filtro` / `quitar_filtro`, `orden_cambiado` → `ordenar_resultados`,
  `cargar_mas` → `paginar_resultados`, `panel_filtros_abierto` → `abrir_filtros`, `consulta_iniciada` → `iniciar_consulta`,
  `consulta_error_validacion` → `error_consulta`, `consulta_enviada` → `enviar_consulta`. `codigo` / `caja` → `registro_codigo` /
  `caja_numero`. Esos nombres viejos nunca llegaron a GA4: no había GTM en el archivo.

## 3. Sitio institucional (estático)

`institucional/_build/generar.py` escribe en cada página el contexto con el entorno y GTM. `sitio/assets/js/analytics.js`
clasifica los clics y `sitio/assets/js/app.js` mide el formulario de la Muestra. Detalle en `institucional/sitio/NOTAS.md`.

| Evento | Cuándo | Parámetros |
|---|---|---|
| `clic_navegacion` | Menú (cuenta como cabecera) o pie hacia otra página | `destino_seccion`, `enlace_texto`, `ubicacion_enlace` |
| `clic_archivo` | **nuevo** · enlace al archivo en línea (`/archivo/…`, mismo dominio) | `destino_archivo` (`inicio` o la ruta), `enlace_texto`, `ubicacion_enlace` |
| `clic_red_social` | Instagram, Facebook, YouTube, X | `red_social`, `enlace_texto`, `ubicacion_enlace` |
| `clic_externo` | Otros dominios: video y nota de La Nación, Turismo BA, prensa, P3Design | `url_destino`, `dominio_destino`, `enlace_texto`, `ubicacion_enlace` |
| `clic_contacto` / `clic_donacion` | Correo (Sumate = donación) o teléfono | `metodo_contacto`, `destinatario` (**ahora** `casilla_proyecto` u `otro_<dominio>`, nunca la dirección), `enlace_texto` (un correo en el texto queda `[correo]`), `ubicacion_enlace` |
| `descarga_catalogo` / `descarga_documento` | PDF | `archivo_nombre`, `archivo_extension`, `enlace_texto`, `ubicacion_enlace` |
| `enviar_formulario_muestra` | **nuevo** · «¿Querés llevar la muestra a tu espacio?» enviado | `via_envio` (`correo`: arma el mail; `servidor`: POST al endpoint; `correo_respaldo`: falló el POST y abre el mail), `formulario: 'muestra_itinerante'` |
| `scroll_profundo` | 25 / 50 / 75 / 100 % | `porcentaje_scroll` |
| `tiempo_en_pagina` | 30 / 60 / 180 s con la pestaña visible | `segundos_en_pagina` |

- `enviar_formulario_muestra` reemplaza a `consulta_muestra`, que mandaba la **ciudad** escrita en el formulario (texto libre).
- La rama de Muestra ya no es «Muestra y No Museo»: el grupo de contenido «Muestra» empieza de cero (el histórico quedó con el
  nombre anterior).

## 4. `evento_uso` (conteo propio del servidor)

- `public/archivo.php` suma una fila por apertura (`vista`) o descarga (`descarga`) **después de los controles de acceso**,
  también con Drive (cuenta el pedido que redirige). No cuenta los tramos `Range` del visor, las consultas del equipo
  (sesión del backoffice) ni los restringidos (403). Verificado con Drive: el visor de la ficha y la descarga suman una fila
  cada uno.
- **Migración 007** (plan §10): `referer` es la ruta del sitio que lo pidió (`/registro/C1R33`, sin consulta) o solo el dominio
  si viene de afuera (`www.google.com`). `sesion_hash` son 16 caracteres de HMAC-SHA256 con el secreto del sitio sobre fecha, IP
  y navegador. Sirve para contar personas distintas por día y archivo; cambia cada día, no se revierte y **la IP no se guarda**.
  Columnas nuevas, nulables, más el índice `(archivo_id, creado_en)`. No toca filas existentes.
- Las búsquedas (`tipo = 'busqueda'`) no se cuentan en el servidor: `explorar.json` responde a cada pausa del tipeo. Se miden
  en GA4.
- Consulta de ejemplo: `SELECT archivo_id, tipo, DATE(creado_en) dia, COUNT(*) pedidos, COUNT(DISTINCT sesion_hash) personas
  FROM evento_uso GROUP BY 1, 2, 3`.

## 5. Dimensiones y métricas nuevas en GA4

Hoy hay 25 dimensiones y 4 métricas registradas. El límite es de 50 dimensiones de evento, 25 de usuario y 50 métricas. Las
nuevas son todas de **alcance evento**. Si alguna ya está registrada con el mismo parámetro, se saltea.

| Dimensión (nombre visible) | Parámetro |
|---|---|
| Entorno | `entorno` |
| Código de registro | `registro_codigo` |
| Número de caja | `caja_numero` |
| Tipos de documento | `tipos_documento` |
| Acceso al documento | `acceso_documento` |
| Etapa | `etapa` |
| Origen del archivo | `origen_archivo` |
| Origen de la búsqueda | `origen_busqueda` |
| Filtros activos | `filtros_activos` |
| Filtro | `filtro` |
| Valor del filtro | `filtro_valor` |
| Orden de resultados | `orden_resultados` |
| Método de compartir | `metodo_compartir` |
| Tipo de consulta | `tipo_consulta` |
| Vía de envío | `via_envio` |
| Destino en el archivo | `destino_archivo` |
| Término de búsqueda (archivo) | `search_term` |

| Métrica | Parámetro | Unidad |
|---|---|---|
| Resultados de búsqueda | `resultados` | Estándar |
| Peso del archivo (MB) | `peso_mb` | Estándar |

Son 17 dimensiones (25 + 17 = 42 de 50) y 2 métricas (6 de 50). Van sin registrar, porque se leen en DebugView o se derivan de
otras: `digitalizado`, `archivo_parte`, `archivo_formato`, `tipo_busqueda`, `cantidad_filtros`, `pagina`, `documentos`,
`tipo_error`, `campos_error`, `formulario`. `ubicacion_enlace` y `content_group` ya existen.

## 6. GTM: pasos en la interfaz (`GTM-M83WNDH5`)

1. **Variables → Variables definidas por el usuario → Nueva → Variable de capa de datos** (versión 2, sin valor predeterminado).
   Una por parámetro, con nombre `DLV - <parámetro>` (si ya existe, se saltea):
   `entorno`, `traffic_type`, `debug_mode`, `content_group`, `registro_codigo`, `caja_numero`, `tipos_documento`,
   `acceso_documento`, `digitalizado`, `etapa`, `origen_archivo`, `archivo_parte`, `archivo_formato`, `peso_mb`, `search_term`,
   `resultados`, `origen_busqueda`, `filtros_activos`, `cantidad_filtros`, `tipo_busqueda`, `filtro`, `filtro_valor`,
   `orden_resultados`, `pagina`, `documentos`, `metodo_compartir`, `tipo_consulta`, `campos_error`, `tipo_error`, `content_type`,
   `item_id`, `via_envio`, `formulario`, `destino_archivo`.
   Revisar en **Variables integradas** que esté tildada `Event`.
2. **Etiqueta de Google (G-5EGY68N1ZX) → Configuración → Parámetros de configuración → Agregar parámetro:**
   - `traffic_type` = `{{DLV - traffic_type}}`
   - `debug_mode` = `{{DLV - debug_mode}}`
   - `entorno` = `{{DLV - entorno}}`
   - `content_group` = `{{DLV - content_group}}` (si hoy toma `{{DLV - rama}}`, se puede dejar: en el institucional valen lo mismo
     y en el archivo las dos dicen «Archivo»)

   Sin valor, GTM no manda el parámetro: en producción el hit sale sin `tt` y sin `_dbg`.
   Activador: el que ya tiene (Initialization / All Pages).
3. **Activadores → Nuevo → Evento personalizado**, nombre `CE - Archivo`, tildar «Usar coincidencia de expresiones regulares»,
   nombre del evento:
   `^(buscar_archivo|busqueda_sin_resultados|aplicar_filtro|quitar_filtro|ordenar_resultados|paginar_resultados|abrir_filtros|select_content|error_resultados|ver_caja|ver_registro|ver_archivo|descargar_archivo|compartir_registro|iniciar_consulta|error_consulta|enviar_consulta)$`
   en «Todos los eventos personalizados».
4. **Etiquetas → Nueva → Google Analytics → Evento de Google Analytics**, nombre `GA4 - Evento - Archivo`. ID de medición
   `G-5EGY68N1ZX`. Nombre del evento `{{Event}}`. **Parámetros del evento:** una fila por cada variable del paso 1 salvo
   `entorno`, `traffic_type`, `debug_mode` y `content_group` (esas van por la etiqueta de Google). Nombre = parámetro, valor =
   `{{DLV - …}}`. Activador `CE - Archivo`. Los parámetros sin valor no se mandan, así que una sola etiqueta sirve para todos
   los eventos.
5. **Institucional:** en el activador de la etiqueta de eventos del sitio que ya existe, sumar `enviar_formulario_muestra` y
   `clic_archivo` (si es una expresión regular, agregarlos a la lista; si es un activador por evento, crear dos más y sumarlos a
   la etiqueta). En esa etiqueta, agregar los parámetros `via_envio`, `formulario` y `destino_archivo`. Si alguna etiqueta o
   activador escuchaba `consulta_muestra`, sacarlo.
6. **Vista previa** (Tag Assistant) con `http://lascajasdeaida.com.ar.vcct10010.avnam.net/` y con `/archivo/`:
   - el primer mensaje trae `entorno: prueba`, `traffic_type: internal`, `debug_mode: true`;
   - en los hits a GA4 aparecen `tt=internal` y `_dbg=1`;
   - buscar, filtrar, abrir una ficha, ver y descargar disparan `GA4 - Evento - Archivo` una vez cada uno.
7. **Enviar → Publicar**, con la versión «Archivo: eventos del §5 + entorno prueba/producción».

## 7. GA4: pasos en la interfaz (propiedad 378294927)

1. **Administrar → Recopilación y modificación de datos → Filtros de datos.** Abrir **Internal Traffic** (si no está: Crear
   filtro → Tráfico interno). Operación **Excluir**, parámetro `traffic_type`, valor **`internal`**.
   - Dejarlo primero en **Prueba** un par de días. En Exploraciones, la dimensión «Nombre del filtro de datos de prueba» tiene
     que marcar solo el tráfico del dominio provisorio y del equipo.
   - Después pasarlo a **Activo**. Desde ahí, lo de prueba **se excluye de los informes para siempre**. DebugView lo sigue
     mostrando (confirmalo la primera vez).
   - Opcional: activar también **Tráfico de desarrolladores**, que excluye todo lo que llega con `debug_mode`.
2. **Flujos de datos → Web → Configurar la etiqueta → Definir el tráfico interno:** no hace falta para esto, porque
   `traffic_type` ya lo manda la página. Sirve para el pendiente de las IP de P3Design y de catalogación: crear la regla con
   valor `internal` y esas IP.
3. **Administrar → Visualización de datos → Definiciones personalizadas → Crear dimensión personalizada** (alcance Evento),
   con las 17 de la sección 5. Después, en la pestaña **Métricas personalizadas**, `resultados` y `peso_mb`. Si GA4 rechaza
   `search_term` por reservado, usar la dimensión estándar «Término de búsqueda».
4. **Administrar → Visualización de datos → Eventos clave → Nuevo evento clave**, por nombre: `descargar_archivo`,
   `ver_archivo`, `enviar_consulta`, `enviar_formulario_muestra` y `descarga_catalogo`. Son los 5 que estaban pendientes para la
   publicación. Si el plan ya nombra otros, vale el plan.
5. **Flujos de datos → Web → Medición mejorada (engranaje):**
   - **Vistas de página → Configuración avanzada → destildar «Cambios de página basados en eventos del historial del
     navegador».** Explorar actualiza la URL con `history.replaceState` en cada filtro y en cada pausa del tipeo; con esa opción,
     cada una contaría como `page_view`.
   - **Búsqueda en el sitio → desactivar.** `view_search_results` contaría cada carga de Explorar con `?q=`, incluidos «atrás» y
     los enlaces compartidos. `buscar_archivo` lo reemplaza.
   - Descargas de archivos: se puede dejar. El PDF del catálogo manda además `file_download`, y las descargas del archivo
     (`archivo.php?…&descargar=1`) no tienen extensión, así que no las ve.
6. **DebugView** (Administrar → DebugView): desde el dominio provisorio tienen que aparecer los eventos de las secciones 2 y 3 con
   sus parámetros.
7. Al apuntar el dominio definitivo no hay que tocar GTM ni GA4, si es uno de los cuatro hosts de producción.

## 8. Pruebas

`pruebas/medicion.mjs` (Playwright). Stub del `dataLayer` que registra cada push y GTM simulado (no se pide el real). Los hosts
de prueba y de producción se simulan con `--host-resolver-rules`.

```sh
# archivo: base propia (schema.sql + demo.sql + migraciones), cajas 1–3 publicadas, ids de Drive cargados, origen drive
php -S 127.0.0.1:8106 -t public bin/router.php
# institucional
(cd ../institucional/sitio && python3 -m http.server 8766)
NODE_PATH=$(npm root -g) node pruebas/medicion.mjs            # o: archivo | institucional
```

Resultado del 16/11/2026: **67 ok, 0 fallas.** Cubre:

- contexto de cada sección y de las 7 páginas institucionales, con `prueba` en el host provisorio y `produccion` (sin
  `traffic_type` ni `debug_mode`) en el host simulado, con y sin www y en `.com`;
- un `buscar_archivo` por término al escribir, sin duplicar con Enter ni con «atrás»; sin resultados una vez; desde el inicio
  y desde una caja;
- filtros, chips, orden, paginación, `select_content`, `ver_caja`, `ver_registro`;
- `ver_archivo` y `descargar_archivo` con Drive (visor y PDF de más de 100 MB, solo descarga), `compartir_registro`;
- consulta: inicio, errores (solo nombres de campos), envío y recarga sin duplicar;
- el formulario de la Muestra por las tres vías;
- **sin datos personales en ningún push** (nombre, correo, teléfono, ciudad, mensaje ni ningún `@`);
- backoffice y vista previa: ningún push ni pedido a GTM; sitio con sesión del backoffice = tráfico interno.

Aparte: sin la clave `analitica`, ninguna página del archivo lleva GTM, dataLayer ni `medicion.js`, y Explorar, el visor y
«Copiar» funcionan sin errores de JS.

## 9. Pendientes y avisos

- **Consentimiento de cookies:** no hay aviso ni Consent Mode. GA4 y el visor embebido de Drive dejan cookies. Si se agrega un
  aviso, el contexto ya va antes de GTM: hace falta un `gtag('consent', 'default', …)` antes del contenedor y la plantilla de
  consentimiento en GTM.
- En el dominio provisorio por **http**, «Copiar cita» y «Copiar enlace» no funcionan: el navegador solo da el portapapeles
  en https o localhost. Con https se resuelve. La prueba lo hace sobre `127.0.0.1`.
- No tuve la copia local del plan (`AIDA_plan_medicion_ga4.md`): los nombres de las 25 dimensiones ya registradas no están
  verificados contra esta lista. Antes del paso 3 de GA4, comparar y saltear las repetidas.
- Panel de métricas en el backoffice con `evento_uso`: no está hecho.
