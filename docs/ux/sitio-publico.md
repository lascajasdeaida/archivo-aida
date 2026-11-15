# Sitio público — arquitectura de información y comportamiento

UX/UI · 15/11/2026 · rama `ux/sitio-publico`. Wireframes navegables en [`wireframes/`](wireframes/index.html).
Define estructura y comportamiento; el estilo visual es de Diseño web. La maqueta v4 se tomó como referencia, no como base.

## 1. Para qué datos se diseña

Base `aida` al 15/11/2026 (lo que el sitio mostraría: sin bajas, sin «a revisar»):

| Dato | Valor | Consecuencia de diseño |
|---|---|---|
| Cajas | 70; 41 con texto de presentación, 29 sin él | La página de caja funciona sin texto: el listado manda. |
| Registros | 1.097 (C2R2 queda afuera: acceso «a revisar») | Volumen chico: filtros con conteos en vivo y sin paginar en exceso. |
| Sin título | 182 (17 %); en la caja 43, 42 de 44 | Se nombran por su tipo (§5.1). Nunca con el nombre de la caja. |
| Con título | 915, pero 323 repiten el nombre de la obra («Soldados y Soldaditos» × 20) | La tarjeta siempre muestra código, caja, año y el comienzo de la descripción, para distinguirlos. |
| Descripción | 1.096 tienen; promedio 350 caracteres, hasta 6.700 | Es el texto más rico: entra en la búsqueda y en la tarjeta (2 líneas). |
| Con documento digital | 145 (13 %), en solo 8 cajas | El 87 % es «solo ficha»: tiene que ser un estado normal, no un error (§5.3). |
| Restringidos | 34 en 12 cajas; 2 de ellos digitalizados | La ficha se ve; el documento se pide (§5.2). |
| Año limpio | 850; 102 con texto («2004/ 2005», «1975-1979?»); 145 sin nada | Faceta por década + «Sin fecha»; el rango de años excluye lo que no tiene año. |
| Años | 1947–2010; el grueso 1970–2009 | Histograma por década en la faceta. |
| Tipos de documento | 57 términos, todos «propuesto», con duplicados («Asistencia a la producción» / «…producción.», «Cable de agencia» / «Cable de Agencia de noticias», «testeo») | Las facetas muestran solo términos aprobados (D6). |
| Descriptores | 51, mismo problema («s», «g», «.», «Película de televisión?????») | Ídem. |
| Períodos («etapas») | 0 cargados | La faceta «Etapa» no existe hasta que se definan (D2). |
| PDF | 146; 62 pesan más de 50 MB y 30 más de 200 MB (máx. 780 MB) | El visor necesita la versión liviana para web (pendiente en decisiones.md). |
| Obras repartidas | Vientos de Agua (17, 19, 29, 32), Fade Out (45, 48, 49, 51, 59), Éramos tan jóvenes (30, 31, 34, 35) | «La misma obra sigue en otras cajas» (D4). |

## 2. Mapa de pantallas y URLs

URLs legibles, en castellano, sin extensiones. El código del registro (`C14R3`) es la clave pública y no cambia.

| Pantalla | URL | Wireframe |
|---|---|---|
| Inicio | `/` | `inicio.html` |
| Explorar / buscador | `/explorar?q=…&tipo=…&decada=…` | `explorar.html` |
| Las 70 cajas | `/cajas` | `cajas.html` |
| Caja | `/caja/14-zsherezade` (manda el número; si el texto no coincide, 301 a la canónica) | `caja.html?n=14` |
| Ficha | `/registro/C14R3` (`/registro/c14r3` y `/registro/C14R03` → 301) | `ficha.html?c=C14R3` |
| Visor | `/registro/C14R3/ver#p=5` (también abre dentro de la ficha) | sección `#visor` de la ficha |
| Descarga | `/registro/C14R3/descargar` | botón en la ficha |
| Consulta a pedido | `/consulta/C2R11` | `consulta.html?c=C2R11` |
| Sobre el archivo | `/sobre-el-archivo` (con anclas `#citar`, `#condiciones`, `#accesibilidad`) | `sobre.html` |

Parámetros de `/explorar` (todos combinables, compartibles y con «atrás» del navegador funcionando):

```
q=texto                      búsqueda libre
tipo=guion,carta             tipo de documento (slug del término aprobado)
descriptor=cine              descriptor (slug)
decada=1980,sin-fecha        década del año
desde=1983&hasta=1986        rango de años
caja=2,14                    número de caja
disponible=digital|ficha     con documento digital / solo ficha
acceso=libre|a-pedido
orden=relevancia|caja|anio-asc|anio-desc|digital
pagina=2
```

Navegación principal: Inicio · Explorar el archivo · Las 70 cajas · Sobre el archivo. Botón «Buscar» siempre visible en la cabecera (en celular, junto a «Menú»).

## 3. Qué muestra cada pantalla (jerarquía)

### Inicio
1. Nombre del archivo y una línea de qué es.
2. **Buscador** (lo primero accionable) + ejemplos de búsqueda.
3. Cifras en vivo: 70 cajas · 1.097 documentos · 145 digitalizados · 1947–2010.
4. Explorá por tipo de documento (10 más frecuentes + «Todos los tipos»).
5. Por época (décadas; pasa a etapas si se definen).
6. «Ya podés leerlos»: documentos digitalizados con título, uno por caja.
7. Las cajas: 9 primeras con avance de digitalización → «Ver las 70 cajas».
8. Sobre el archivo.

### Explorar (resultados)
- Título, buscador, **conteo** («99 resultados»; sin filtros: «1.097 documentos en el archivo»), orden.
- **Chips** de filtros activos, cada uno con su ✕ («Tipo de documento: Carta ✕») y «Limpiar todo» desde 2 filtros.
- Lista de tarjetas: miniatura o marcador · título (o tipo) · `C2R11 · Caja 2: La Historia Oficial · 1983` · 2 líneas de descripción · etiquetas (Documento digital / Solo ficha, Consulta a pedido, hasta 2 tipos). Toda la tarjeta es un único enlace.
- Filtros: columna izquierda fija en escritorio; en celular, botón «Filtros (n)» que abre un panel a pantalla completa (§4.3).

### Las 70 cajas
Grilla de cajas con número, nombre, documentos, años, digitalizados y barra de avance. Filtro por nombre/número y orden (número, A–Z, más digitalizadas).

### Caja
- Migas · «Caja 2 de 70» · nombre (h1).
- Datos: documentos, con documento digital, a pedido, años, sin título.
- Texto de presentación plegado a 4 líneas con «Leer más» (si no hay, no se reserva lugar).
- «Buscar en esta caja» (va a `/explorar?caja=2&q=…`) y «Ver esta caja en el buscador, con todos los filtros».
- «La misma obra sigue en otras cajas» cuando corresponde.
- Accesos rápidos por tipo dentro de la caja.
- Lista completa de documentos **en el orden físico** (máximo 44 por caja: sin paginar).
- Caja anterior / siguiente.

### Ficha
- Migas (Inicio › Caja › código) y «← Volver a los resultados» si se llegó desde Explorar (conserva filtros y posición).
- «Caja 14 · Registro 3 · Código C14R3», título (h1) y etiquetas.
- **Bloque Documento** (en celular va arriba de la ficha, en escritorio a la derecha): según el estado, Ver documento + Descargar (con peso), Pedir consulta, o Todavía no digitalizado (§5).
- Ficha del catálogo (lista de definiciones): Título, Tipo (enlaces a Explorar), Autor/es, Fecha (como dice el documento), Año (enlace a la década), Lugar, Páginas, Descriptores (enlaces), ISBN, Caja, Acceso. Los campos vacíos no se muestran.
- Descripción completa; Observaciones editoriales.
- Cómo citar + «Copiar cita» y «Copiar enlace».
- Documento anterior / siguiente de la misma caja.
- **No** se muestran: observaciones de conservación ni de digitalización, nomenclatura del Excel, campos internos.

### Visor
Barra con página anterior/siguiente, «Página n de N» editable, zoom −/+, ajustar al ancho, pantalla completa, descargar y cerrar. Teclado: ← → páginas, + − zoom, Esc cierra y devuelve el foco a «Ver documento». La URL guarda la página (`#p=5`) para compartir. Usa la versión web liviana; si no existe, ofrece la descarga avisando el peso.

### Consulta a pedido
Documento pedido arriba (tarjeta), explicación breve, formulario: nombre y apellido, correo, institución (opcional), motivo (lista), modalidad (copia digital / presencial, a confirmar), mensaje, aceptación de condiciones. Sin cuenta y sin captcha visual. Confirmación con número de consulta y plazo.

### Sobre el archivo
Quién fue Aída Bortnik, cómo se armó el catálogo, cómo usar el archivo (qué es una caja, un código, «Solo ficha», «A pedido»), cómo citar, condiciones de uso, declaración de accesibilidad, contacto. Textos del cliente.

## 4. Búsqueda y filtros combinables

### 4.1 Reglas
- **Dentro de una faceta, O; entre facetas, Y.** «Carta + Nota de prensa» suma; «Carta + Década de 1980» restringe.
- **Conteos en cada opción**, calculados con todos los demás filtros aplicados menos el de la propia faceta (así se ve cuánto suma marcar otra opción). Las opciones en 0 se ocultan, salvo las ya elegidas.
- Facetas, en este orden: Disponibilidad · Acceso · Tipo de documento · Año (histograma por década + desde/hasta) · Caja / obra · Descriptores · Etapa (cuando exista).
- Abiertas por defecto: Disponibilidad, Tipo y Año; el resto plegado salvo que tenga algo elegido.
- Más de 8 opciones: se ven las 8 con más resultados + «Ver N más». Tipo, Caja y Descriptores tienen un buscador dentro de la faceta.
- El cambio se aplica al instante (sin botón «Aplicar»), salvo el rango de años, que se aplica con su botón para no recalcular a cada tecla.
- La búsqueda libre ignora tildes y mayúsculas, exige todas las palabras y busca en título, autor, lugar, descripción, tipos, descriptores, nombre de caja, código y año. El código exacto (`C14R3`) va directo a la ficha.
- Orden por defecto: «Caja y número» sin búsqueda; «Más relevantes» con búsqueda (título pesa más que descripción; a igualdad, primero lo digitalizado).
- Todo el estado vive en la URL.

### 4.2 Carga progresiva
Primeros 20 resultados y botón «Mostrar 20 más» con «Mostrando 40 de 99» y barra de avance. Es un enlace real a `?pagina=3` (funciona sin JS y lo indexan los buscadores). Al cargar más, el foco va al primer resultado nuevo. Nada de scroll infinito: el pie y el «Volver a los resultados» tienen que poder alcanzarse.

### 4.3 Panel de filtros en celular
- Botón «Filtros (n)» a todo el ancho, debajo del buscador.
- Abre un diálogo modal a pantalla completa: cabecera fija con «Filtrar» y «Cerrar», facetas en acordeón, pie fijo con «Limpiar» y **«Ver 99 resultados»** (el número se actualiza en vivo).
- Foco atrapado adentro, Esc cierra, al cerrar el foco vuelve al botón «Filtros».
- En escritorio el mismo panel es una columna fija con scroll propio.

### 4.4 Estados
| Estado | Qué se ve |
|---|---|
| Sin filtros | Todo el archivo en orden de caja, conteo total. |
| Cargando | Esqueletos de tarjeta, `aria-busy`; el conteo anterior queda hasta tener el nuevo. |
| Sin resultados | «No encontramos documentos con esa combinación» + un botón por filtro activo con el número que daría quitarlo («Quitar «Década de 1940» → 99 resultados») + recordatorio de que muchos documentos no tienen título (buscar por tipo u obra). |
| Error | «No pudimos cargar los resultados. Tus filtros siguen guardados.» + Reintentar. |
| Vacío de caja | No ocurre hoy (todas tienen registros); si pasa: «Esta caja todavía no tiene documentos catalogados». |

## 5. Casos particulares

### 5.1 Registros sin título
- Se nombran por su **tipo de documento** en cursiva («*Sobre · Fotografía*»), con «Sin título ·» en la línea de datos y «(sin título)» para lectores de pantalla.
- Sin tipo tampoco (5 registros): «Documento sin título».
- La tarjeta suma el comienzo de la descripción, que es lo que realmente los distingue.
- En la ficha: «Este documento no tiene título en el catálogo; se lo nombra por su tipo». En la cita: el tipo entre corchetes.
- Corrige la maqueta v4, que mostraba el nombre de la caja como título.

### 5.2 Restringidos (consulta a pedido)
- Aparecen en búsquedas y cajas como cualquier otro, con la etiqueta «Consulta a pedido» (borde punteado + candado: no depende del color).
- Ficha completa visible (regla vigente). Sin miniatura ni visor.
- Bloque Documento: «Consulta a pedido. La ficha es pública, pero el documento se consulta pidiéndolo al archivo» + **«Pedir consulta de este documento»** → `/consulta/C2R11`.
- Faceta «Acceso» para quien quiera excluirlos.

### 5.3 No digitalizados («Solo ficha», 87 %)
- Etiqueta «Solo ficha», neutra. Miniatura reemplazada por un marcador «Sin imagen».
- En la ficha: «Todavía no está digitalizado. El archivo se digitaliza por etapas; por ahora podés ver la ficha del catálogo» + «Consultar por este documento» (D7).
- Faceta «Disponibilidad» para ver solo lo que se puede leer. El inicio destaca lo digitalizado.

### 5.4 Otros
- Título de más de 3 líneas: se corta en la tarjeta, completo en la ficha.
- Año en texto («2004/ 2005»): se muestra tal cual; para filtrar cuenta como sin fecha hasta que se interprete (D9).
- Archivo pesado: el visor usa la versión web; «Descargar PDF (780 MB)» siempre muestra el peso.
- Registro dado de baja o no publicado: 410 con «Este documento ya no está disponible» y enlace a su caja.
- Código inexistente: 404 con buscador.

## 6. Accesibilidad (WCAG 2.1 AA, requisito)

- Estructura: `header`/`nav`/`main`/`footer`, un h1 por página, encabezados en orden, «Saltar al contenido».
- Teclado completo: todo operable sin mouse; foco visible de 3 px; orden de tabulación = orden visual; tarjeta con un solo enlace (no tabular dos veces por lo mismo).
- Diálogo de filtros: `role="dialog"` + `aria-modal`, foco atrapado, Esc, foco devuelto.
- Facetas: `details/summary` + `fieldset/legend` + checkboxes nativos con `label`; el conteo se lee («99 resultados»).
- Cambios en vivo: el conteo se anuncia en una región `aria-live="polite"` con 600 ms de espera, para no hablar a cada clic.
- Estados nunca solo por color: «Documento digital ▣», «Solo ficha □», «Consulta a pedido» con borde punteado.
- Objetivos táctiles de 44 × 44 px como mínimo (opciones de faceta 40 px de alto con la etiqueta entera clicable).
- Formularios: etiqueta visible, ayuda asociada con `aria-describedby`, errores en texto junto al campo + resumen arriba con enlaces y foco en el resumen; `autocomplete` en nombre, correo e institución.
- Contraste: los grises del wireframe cumplen 4,5:1 sobre blanco (#555, #767676); Diseño web tiene que mantenerlo.
- Zoom 200 % y 320 px de ancho sin scroll horizontal. `prefers-reduced-motion` respetado.
- Documentos: el PDF escaneado no es accesible; la ficha y la descripción hacen de alternativa textual (D8).
- Verificado con axe-core 4 (WCAG 2.0/2.1 A y AA + buenas prácticas) en las 10 vistas, a 390 y 1366 px: sin hallazgos.

## 7. Eventos para Estadísticas (GA4)

Se cargan con `dataLayer.push`. **Nunca datos personales** (ni nombre, ni correo, ni mensaje de la consulta). En los wireframes, el botón «Eventos GA4» muestra lo que se enviaría.

| Evento | Cuándo | Parámetros |
|---|---|---|
| `search` (recomendado GA4) | Búsqueda enviada (inicio, explorar o desde URL) | `search_term`, `origen` |
| `busqueda_sin_resultados` | Resultado 0 | `search_term`, `filtros` («tipo:carta\|decada:1940») |
| `filtro_aplicado` / `filtro_quitado` | Marcar / desmarcar opción o chip | `filtro`, `valor` |
| `filtros_limpiados` | «Limpiar todo» | — |
| `orden_cambiado` | Cambio de orden | `orden` |
| `cargar_mas` | «Mostrar más» | `pagina` |
| `panel_filtros_abierto` | Abrir filtros en celular | — |
| `select_content` (recomendado) | Clic en una tarjeta | `content_type=registro`, `item_id`, `origen` (explorar, caja, inicio) |
| `ver_caja` | Vista de caja | `caja` |
| `ver_registro` | Vista de ficha | `codigo`, `caja`, `tipo`, `acceso`, `digitalizado`, `con_titulo` |
| `ver_documento` | Abrir el visor | `codigo`, `caja` |
| `visor_pagina` | Cambio de página en el visor | `codigo`, `pagina` |
| `file_download` (automático GA4, se completa) | Descarga del PDF | `file_name`, `file_extension`, `codigo`, `caja` |
| `consulta_iniciada` | Clic en «Pedir consulta» o primer foco en el formulario | `codigo`, `origen` |
| `consulta_error_validacion` | Envío con errores | `codigo`, `campos` |
| `consulta_enviada` | Pedido recibido | `codigo`, `caja`, `motivo` |
| `cita_copiada` / `share` | Copiar cita / enlace | `codigo` / `method`, `item_id` |
| `error_resultados` | Falla al cargar resultados | `tipo` |

Dimensiones personalizadas sugeridas (alcance evento): `codigo`, `caja`, `tipo`, `acceso`, `digitalizado`, `filtro`, `valor`. Conversiones: `consulta_enviada`, `file_download`, `ver_documento`. La tabla propia `evento_uso` (vista, descarga, búsqueda) se alimenta en el servidor con los mismos nombres.

## 8. Decisiones abiertas para el PM

| # | Decisión | Recomendación UX |
|---|---|---|
| D1 | **Login sí o no** | **No** para navegar, ver y descargar lo público, ni para pedir consultas (formulario con correo). Login solo si más adelante se da acceso en línea a restringidos para investigadores aprobados; el esquema ya tiene el rol `visitante`. |
| D2 | **Qué es «etapa»** | Que el cliente defina 4–6 etapas de la obra con años (p. ej. inicios, exilio, regreso y cine, televisión española, últimos años) y las asigne a cajas (`caja_periodo` ya existe, hoy vacía). Mientras tanto, la faceta es «Año» por década. No inventar etapas desde UX. |
| D3 | **Qué se muestra de los restringidos** | Ficha completa (regla vigente), sin miniatura ni visor. Pedir al cliente que revise los 34: hay recibos con montos y cartas personales (C2R11, C2R12, C4R6) cuya descripción quizá no deba ser pública; si alguno no puede mostrar la descripción, agregar un nivel «ficha reducida». Definir también si la consulta es por copia digital, presencial o ambas, y quién responde y en qué plazo. |
| D4 | **Agrupar cajas por obra** | Sí: un campo «obra» (o tabla) en el backoffice para unir 17/19/29/32 (Vientos de Agua), 45/48/49/51/59 (Fade Out), 30/31/34/35, 3/5, 9/13, 23/24, 8/12, 11/15. Habilita la faceta «Obra» y «La misma obra sigue en otras cajas» sin adivinar por nombre. |
| D5 | **Publicación por caja** | Hoy las 70 cajas tienen `publicada = 0`. Recomiendo publicar por caja a medida que se revisa su catálogo, y que el sitio muestre solo cajas publicadas (las cifras del inicio, también). Salir con las 8 cajas digitalizadas + las de catálogo revisado. |
| D6 | **Vocabularios sin curar** | Las facetas muestran solo términos **aprobados**. Antes de publicar, curar los 57 tipos y 51 descriptores (fusionar duplicados, borrar «testeo», «s», «g», «.»). Es trabajo de Datos y catálogo; sin eso, los filtros se ven descuidados. |
| D7 | **Qué ofrecer en lo no digitalizado** | «Consultar por este documento» con el mismo formulario (motivo «consultar el original»). Opcional más adelante: «Avisame cuando esté digitalizado» (requiere guardar correos: implica política de privacidad). |
| D8 | **Texto accesible de los documentos** | Hacer OCR en las versiones web cuando se generen (búsqueda dentro del documento y accesibilidad). Mientras tanto, la descripción es la alternativa. |
| D9 | **Años en texto** | Interpretar «2004/ 2005», «1975-1979?» como rango (`anio_desde`/`anio_hasta` por registro) para que entren en el filtro; marcar los dudosos («?») como aproximados. Pedido para Base de datos. |
| D10 | **Descarga** | Permitir descarga de lo público con el peso visible. Si el cliente no quiere descargas, dejar solo el visor. Bloquear descarga de PDF de más de 200 MB hasta tener la versión web (30 archivos). |
| D11 | **Licencia y cita** | El cliente tiene que definir condiciones de uso (p. ej. CC BY-NC) y el formato de cita; el wireframe usa uno provisorio. |
| D12 | **Orden por defecto en Explorar** | «Caja y número» (refleja el archivo). Alternativa: «Con documento digital primero» para que lo primero que se vea se pueda leer. |

## 9. Pedidos a otros roles

- **Base de datos:** rango de años por registro (D9); búsqueda sin tildes sobre título, autor, lugar, resumen, tipos, descriptores y nombre de caja; conteos por faceta en una consulta.
- **Datos y catálogo:** curaduría de vocabularios (D6); revisar C2R2 («VERLO»).
- **Programación:** filtros en URL con enlaces reales (funciona sin JS); 301 de códigos con ceros o minúsculas; versión web y miniatura de los PDF.
- **Diseño web:** mantener jerarquía, estados y etiquetas con forma además de color; contraste AA sobre la paleta de la marca.
- **Estadísticas:** registrar las dimensiones de §7 en GA4.

## 10. Wireframes

`docs/ux/wireframes/index.html` enlaza todo. Son HTML estático con `wf.css` (grises) y `wf.js` (comportamiento). Los datos salen de la base con:

```bash
php docs/ux/wireframes/generar-datos.php /ruta/a/config.php   # solo lectura; escribe datos.js
```

`datos.js` es un extracto (código, título, tipos, descriptores, año, caja, acceso, comienzo de la descripción; 4 fichas completas de ejemplo) sin registros dados de baja, no publicados ni «a revisar».
