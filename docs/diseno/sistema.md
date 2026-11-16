# Sistema visual: archivo e institucional

Diseño web · 16/11/2026.

- **El archivo (catálogo en línea, `vistas/publico/` + `public/sitio/`) usa un lenguaje de sistema**: sección «Archivo: sistema», abajo.
  Rama `diseno/archivo-sistema`. Decisión del PM del 16/11/2026 ([`../decisiones.md`](../decisiones.md) «Diseño del archivo: lenguaje de sistema»).
- **El backoffice usa el mismo lenguaje de sistema, contrastado por colorimetría**: sección «Backoffice: sistema», abajo.
- **El sitio institucional sigue con la dirección B inmersiva** (Museografía con la máquina de escribir y el cursor de C), documentada en §1–§11.
  Esos tokens ya no están en `public/sitio/tokens.css` (el archivo los reemplazó); quedan en la historia de git (`35b0f85`) y en la rama del sitio institucional.
- Lo que comparten: el **logo** de cajas, la **paleta** (azul `#0D5C9E`, rojo-naranja `#EF6A3A`, tinta `#1D1815`) y el **cursor** de la máquina de escribir.

## Archivo: sistema

Pedido del PM: «cuando digo sistema no me refiero a aburrido, pero 3 o 4 niveles de lectura y tipografías me parece mucho. Me siento más cómodo con
menos variables, más prioridad a resultado de búsqueda rápido, y a todo lo que debe priorizar un sistema de archivo».
Tokens: [`public/sitio/tokens.css`](../../public/sitio/tokens.css). Hoja: [`public/sitio/estilo.css`](../../public/sitio/estilo.css).

### A.1 Criterio

- **Menos variables.** 2 familias, 3 niveles de lectura, 2 pesos (400 y 600), 1 radio (2 px), 1 grosor de línea (1 px; 2 px solo bajo los títulos de bloque).
- **Búsqueda primero.** El inicio es el buscador; Explorar es una lista densa de una fila por registro; la ficha y la caja son vistas de registro.
- **Fondo blanco y neutro**, paneles en `fondo-2` (#F4F3F0, apenas cálido: el papel de B). Sin bandas, sin sombras, sin grano, sin fotos de ambiente.
- **Acentos funcionales, no decorativos.** Azul = acción primaria, enlace, foco, selección (fila en hover, opción elegida, filtro activo, navegación actual).
  Rojo-naranja = el cursor del buscador y el resaltado del término buscado (fondo `resaltado`). Los estados del documento conservan sus colores y formas.
- **Densidad de herramienta**: filas de 40–56 px en escritorio, objetivos táctiles de 40–44 px en celular.

### A.2 Tipografía: 2 familias, 3 niveles

| Nivel | Uso | Familia | Celular | Escritorio | Interlínea |
|---|---|---|---|---|---|
| **1 · Título de pantalla** | Un `h1` por página | Inter 600 | 24 px | 28 px | 1,2 |
| **2 · Texto** | Interfaz, filas, metadatos, prosa; `h2`/`h3` son este nivel en 600 | Inter 400 / 600 | 16 px | 16 px | 1,5 (prosa 1,65, 70 caracteres) |
| **3 · Dato secundario** | Tipos, caja, año, conteos, ayudas, migas, etiquetas | Inter 400 | 14 px | 14 px | 1,5 |
| Código | `C2R5` (filas, ficha, migas, anterior/siguiente) | Courier Prime 700 | como su contexto | | |
| Buscador | Lo que se escribe en el campo | Courier Prime 400 | 17–18 px | | |
| Número de caja | El rótulo del módulo-caja (§A.10): única cifra grande | Courier Prime 700 | 28–36 px | 28–44 px | 1 |

- **Oswald sale del archivo**: la condensada queda solo en el logo (SVG). No se usa en el título del inicio: el inicio es el buscador y no suma.
- **Sin rótulos en caja alta espaciada.** El orden lo dan los títulos de bloque (16 px 600 con línea de 2 px) y el encabezado de columnas.
- **Registros sin título:** itálica (antes Courier itálica): se distinguen por forma, no por color.
- Cifras con `tabular-nums` para que las columnas de conteos y años se alineen.

### A.3 Paleta y contrastes

Medidos con `node docs/diseno/contraste.js` (WCAG 2.1, luminancia relativa) sobre `tokens.css`.

| Texto / elemento | Fondo | Uso | Contraste | Mínimo AA |
|---|---|---|---|---|
| `texto` #1D1815 | `fondo` #FFFFFF | Títulos, texto, filas | 17,59:1 ✔ | 4,5:1 |
| `texto` #1D1815 | `fondo-2` #F4F3F0 | Texto en paneles (facetas, acciones, cita, pie) | 15,85:1 ✔ | 4,5:1 |
| `texto` #1D1815 | `azul-suave` #EAF1F8 | Texto en fila con hover y opción elegida | 15,44:1 ✔ | 4,5:1 |
| `texto` #1D1815 | `resaltado` #FFE0CC | Término buscado (<mark>) | 14,06:1 ✔ | 4,5:1 |
| `texto-2` #5A544D | `fondo` #FFFFFF | Dato secundario | 7,47:1 ✔ | 4,5:1 |
| `texto-2` #5A544D | `fondo-2` #F4F3F0 | Dato secundario en paneles y encabezado de columnas | 6,73:1 ✔ | 4,5:1 |
| `texto-2` #5A544D | `azul-suave` #EAF1F8 | Dato secundario en fila con hover | 6,56:1 ✔ | 4,5:1 |
| `azul` #0D5C9E | `fondo` #FFFFFF | Enlaces y títulos de fila | 6,90:1 ✔ | 4,5:1 |
| `azul` #0D5C9E | `fondo-2` #F4F3F0 | Enlaces en paneles | 6,22:1 ✔ | 4,5:1 |
| `azul` #0D5C9E | `resaltado` #FFE0CC | Término buscado dentro de un título-enlace | 5,52:1 ✔ | 4,5:1 |
| `azul-osc` #0A4A80 | `azul-suave` #EAF1F8 | Título de fila en hover | 8,00:1 ✔ | 4,5:1 |
| `blanco` #FFFFFF | `azul` #0D5C9E | Botón primario, chip activo en hover, conteo de faceta | 6,90:1 ✔ | 4,5:1 |
| `blanco` #FFFFFF | `azul-osc` #0A4A80 | Botón primario en hover | 9,11:1 ✔ | 4,5:1 |
| `blanco` #FFFFFF | `texto` #1D1815 | Barra del visor | 17,59:1 ✔ | 4,5:1 |
| `blanco` #FFFFFF | `visor` #3A3734 | Zona del visor | 11,82:1 ✔ | 4,5:1 |
| `digital` #2F5A2A | `digital-suave` #E6EEDF | Badge Documento digital | 6,75:1 ✔ | 4,5:1 |
| `pedido` #A8361A | `pedido-suave` #FDEDE5 | Badge Consulta a pedido | 5,74:1 ✔ | 4,5:1 |
| `duda` #7A5A0E | `duda-suave` #F4E8C4 | Badge Año sin confirmar | 5,21:1 ✔ | 4,5:1 |
| `duda` #7A5A0E | `fondo` #FFFFFF | Año sin confirmar en texto | 6,37:1 ✔ | 4,5:1 |
| `duda` #7A5A0E | `azul-suave` #EAF1F8 | Año sin confirmar en fila con hover | 5,59:1 ✔ | 4,5:1 |
| `error` #A12A1A | `error-suave` #F9E1DA | Resumen de errores | 5,87:1 ✔ | 4,5:1 |
| `error` #A12A1A | `fondo` #FFFFFF | Error junto al campo | 7,34:1 ✔ | 4,5:1 |
| `borde` #767069 | `fondo` #FFFFFF | Borde de controles (1.4.11) | 4,89:1 ✔ | 3:1 |
| `borde` #767069 | `fondo-2` #F4F3F0 | Borde de controles en paneles | 4,41:1 ✔ | 3:1 |
| `azul` #0D5C9E | `fondo` #FFFFFF | Anillo de foco, checkbox, barra de avance | 6,90:1 ✔ | 3:1 |
| `azul` #0D5C9E | `azul-suave` #EAF1F8 | Anillo de foco sobre fila con hover | 6,06:1 ✔ | 3:1 |
| `blanco` #FFFFFF | `visor` #3A3734 | Anillo de foco en el visor | 11,82:1 ✔ | 3:1 |
| `rojo` #EF6A3A | `fondo` #FFFFFF | Cursor del buscador (decorativo, NO texto) | 3,09:1 | — |

Notas: el rojo-naranja no es texto ni borde de control; el cursor es decorativo (3,1:1 igual se ve). El resaltado lleva tinta o el azul del enlace encima.

### A.4 El cursor: solo en el buscador

El concepto que le gusta al PM se conserva en un solo lugar: **el buscador**. Inicio, Explorar, caja y 404 llevan el mismo campo.

- Campo vacío y sin foco: un bloque rojo bajo la línea (la tecla subrayado de la máquina, `.buscador__cursor`, `aria-hidden`) delante del marcador,
  que titila **4 veces (≈ 4 s, menos de 5 s: WCAG 2.2.2) y queda fijo**. Con `prefers-reduced-motion: reduce` no titila.
- Al enfocar, el bloque desaparece y lo reemplaza el cursor real del campo, también rojo (`caret-color`), y el texto se escribe en Courier Prime.
- **Sin tipeo animado de títulos** en el archivo (ni inicio, ni caja, ni ficha). `sitio.js` ya no tiene el código de tipeo.

### A.5 Pantallas

**Inicio.** Título «Buscar en el archivo de Aída Bortnik», buscador grande (56 px) con el cursor, ejemplos de búsqueda y **cifras en una línea**
(cajas publicadas de 70 · documentos · digitalizados · años). Debajo, las **cajas como módulos-caja chicos** (§A.10) y **accesos rápidos** en dos
índices (Tipos de documento, Décadas; Etapas aparece bajo Décadas solo cuando haya períodos asignados a cajas publicadas) y los últimos documentos digitales en filas. Sin hero, sin foto,
sin frase; «Sobre el archivo», «Cómo citar» y «Cómo consultar a pedido» en una línea al pie.

**Explorar.** Buscador arriba; a la izquierda, facetas compactas (panel `fondo-2`, 14 px, opciones de 32 px) y en celular el panel a pantalla completa
de siempre; arriba de la lista, **conteo y orden** en una barra y los **chips de filtros activos** (azul suave con borde azul). La lista es una
**fila por registro**, con encabezado de columnas en escritorio:

| Código | Documento | Caja | Año | Acceso |
|---|---|---|---|---|
| `C2R5` (mono) | Título en azul (enlace) + tipos; si hay búsqueda, el fragmento del resumen donde aparece | Caja N · nombre | Año (dudoso punteado) | Digital / A pedido / Solo ficha |

En celular la fila se apila: código · año · estado / título / tipos / caja. Toda la fila es un enlace (`h3 > a::after`), hover en azul suave, foco con
anillo azul interior. El encabezado de columnas es `aria-hidden`: cada celda se entiende sola («Caja 2: …», «Documento Digital», «Consulta A pedido»).

**Resaltado del término.** `Html::resaltar()` envuelve los términos de la búsqueda (2 letras o más) en `<mark>`, sin tildes ni mayúsculas como la
base («aida» marca «Aída»), en código, título, tipos, caja y fragmento. `Html::fragmento()` recorta ~170 caracteres del resumen alrededor de la
primera coincidencia; para eso la tarjeta trae ahora el `resumen` completo (`Sitio::armarTarjetas`, una línea). Se resalta en el servidor: funciona sin JS.

**Ficha y caja: vista de registro.** Barra superior con migas y **Anterior / Siguiente** (código o número; con el nombre en el pie desde 700 px),
«Volver a los resultados» si se llegó desde Explorar, línea de dato (`C2R5` · Caja 2, registro 5), título y estados. En la ficha, a la derecha
(arriba en celular) el panel **Documento** con las acciones agrupadas: Ver documento (primaria) · Descargar · Pedir consulta / Consultar por este
documento · Copiar cita · Copiar enlace. Metadatos en una **lista de definición compacta** (`dt` 14 px gris, `dd` 16 px, 150 px de columna, filas con
línea de 1 px), descripción en prosa y cita en un panel. La caja muestra cifras en una línea, barra de avance, resumen plegado a 4 líneas, buscador de
la caja, tipos como accesos y la lista de registros con las mismas filas (sin la columna Caja).

**Las cajas.** Grilla de **módulos-caja** (§A.10) con búsqueda por nombre o número arriba (filtra mientras se escribe), orden por número, título,
año (las cajas sin años al final) o más digitalizadas, y conteo. 2 columnas a 390 px; columnas de 176 px como mínimo desde 600 px (4 a 900, 6 a 1366).
La página de una caja lleva el mismo módulo, chico y decorativo, junto al título.

### A.10 Módulo-caja: el anclaje físico (16/11/2026)

Pedido del PM: «La sección "cajas" que refleje módulos con cierto diseño minimalista de una caja, haciendo alusión a un anclaje físico de lo que se
relevó». Parcial [`vistas/publico/_modulo_caja.php`](../../vistas/publico/_modulo_caja.php), estilos «Módulo-caja» en `estilo.css`. Solo CSS, sin imágenes.

- **El dibujo:** el frente de una caja de archivo de cartón en línea fina (1,5 px tinta): la **tapa** (una franja apenas más ancha, en `fondo-2`), el
  **troquel** de la manija (una ranura redondeada) y el **rótulo pegado** (marco de 1 px) con el **número grande a máquina**, la obra y los años. Al pie
  del frente, el **contenido**: cantidad de registros (y a pedido) y la barra de digitalización con su porcentaje. Sin texturas, ilustración ni sombras.
- **Hover y foco:** la tapa se levanta 5 px con un giro de 1,5° (160 ms) y la caja pasa a azul: la caja «se abre». Con `prefers-reduced-motion` solo
  cambia el color. Foco: anillo azul de 3 px alrededor de toda la caja (`:has(a:focus-visible)`).
- **Accesibilidad:** tapa, troquel y número son `aria-hidden`; el enlace (el título, extendido a toda la caja con `::after`) dice «Caja N: nombre»; la
  barra tiene `role="img"` con «N % digitalizado».
- **Variantes:** `grande` (Las cajas), `chica` (inicio: número 28 px, título 14 px) y `encabezado` (página de la caja: sin enlace ni contenido, todo
  `aria-hidden`, 92/128 px de ancho, junto al `h1`).
- Es la única excepción a los tres niveles: el número de la caja, como en el rótulo físico.

### A.6 Respuesta inmediata

- **Búsqueda mientras se escribe** en Explorar: 180 ms después de la última tecla se piden los resultados (`explorar.json`) y se reemplazan facetas,
  chips y lista; una consulta por pausa, no por letra. El código exacto (`C14R3`) y las letras sueltas no disparan la búsqueda (Enter con el código
  lleva a la ficha). El evento `search` de Estadísticas sale tras 1,5 s quieto, una vez por término (antes solo con Enter).
- Si la persona no eligió orden, al buscar se ordena por relevancia y sin búsqueda por caja (igual que el servidor).
- **Sin animaciones de entrada.** La lista se atenúa al 55 % solo si la respuesta tarda más de 150 ms (`transition-delay`), así no parpadea.
  Esqueleto liviano (filas grises fijas, sin brillo) solo cuando no había lista.
- **Atajo `/`**: enfoca el buscador de la página (Explorar, inicio, caja, filtro de Las cajas) y selecciona su texto; en las demás páginas lleva a
  Explorar con el foco en el buscador. No actúa dentro de campos ni con modificadores. Los campos llevan `aria-keyshortcuts="/"` y la tecla se
  muestra en la cabecera y en la ayuda del buscador (solo escritorio con mouse).

### A.7 Estados y lo que se mantiene

- Badges con forma: digital **borde lleno** + hoja; a pedido **borde rayado** + candado; año sin confirmar **punteado** + «?»; solo ficha neutro + ficha.
- Foco: anillo azul de 3 px con 2 px de separación (interior en filas); blanco en el visor. Visor neutro oscuro (#3A3734), documento sin filtro.
- `prefers-reduced-motion`, funcionamiento sin JS (formulario GET, chips y «Mostrar más» como enlaces), vista previa del backoffice (banner propio
  sin cambios, verificado), sin desbordes a 390 px.

### A.8 Verificación (16/11/2026)

Playwright + Chrome y axe-core 4.11 (WCAG 2.0/2.1/2.2 A y AA + buenas prácticas), base `aida_ds` con las cajas 1, 2, 3, 6, 14 y 20 publicadas,
puerto 8103. Script `/home/claude/qa/archivo-sistema/qa.py`; capturas antes/después e `informe-qa.json` en `/home/claude/qa/archivo-sistema/`.

- 23 vistas a 390 y 21 a 1366 (inicio, explorar sin filtros / con búsqueda / filtrado / sin resultados / panel, cajas y sin coincidencias, cajas 2 y 14,
  fichas digital / a pedido / solo ficha / año dudoso, visor, consulta con errores y enviada, «Sobre», 404, 410, menú, vista previa de ficha y de caja no
  publicada). **axe: 0 hallazgos. Desborde: ninguno. Errores de JS: ninguno.**
- Foco: 30 tabulaciones en inicio, explorar, ficha y caja, a 390 y 1366: todas con anillo visible.
- Atajo `/`: desde la ficha lleva a Explorar con foco en `#q`; en inicio y caja enfoca su buscador.
- Cursor: titila 4 veces (1,05 s) y queda fijo; con foco desaparece; con movimiento reducido, sin animación.
- **Tiempos** (de la última tecla o el clic hasta que cambia la lista, 5 mediciones por caso, servidor local):
  escribir **≈ 212 ms** de mediana (180 ms son la espera a que la persona deje de tipear; la respuesta y el pintado, ≈ 30 ms);
  marcar un filtro **≈ 30 ms** (390: 32 ms, 1366: 34 ms de mediana).

**Módulo-caja (16/11/2026, segunda pasada).** Mismo script con salida en `/home/claude/qa/archivo-sistema/cajas/` (capturas antes/después, hover,
foco): 26 vistas a 390 y 24 a 1366, incluidas Las cajas por año, por título, filtrada y sin coincidencias. **axe: 0. Desborde: ninguno. JS: sin
errores.** Foco: 30 tabulaciones en Las cajas e inicio, todas con anillo (el anillo está en el `li`, 5 niveles arriba del enlace).

### A.9 Pendientes

- Las maquetas estáticas de `docs/diseno/maquetas/` siguen con el marcado de B (hero, bandas): usan esta hoja y se ven a medio camino. Las plantillas PHP son la referencia.
- Medir los tiempos contra el servidor real con la base completa publicada (acá: 135 registros publicados).
- Si el PM quiere, atajos `j`/`k` o flechas para recorrer la lista y `[`/`]` para anterior/siguiente en la ficha.


## Backoffice: sistema

Pedido del PM (16/11/2026): «El back quedó con el formato anterior que habíamos trabajado. Llevarlo al mismo eje del front, pero contrastarlo desde
la colorimetría. El login ponerlo con el logo de AIDA, lo mismo que el encabezado.» Rama `diseno/backoffice-sistema`.
Tokens: [`public/admin/tokens.css`](../../public/admin/tokens.css) (copia de los del archivo + colorimetría propia; el back no carga la hoja del sitio).
Hoja: [`public/admin/estilo.css`](../../public/admin/estilo.css), reescrita entera (ya no hay capas de ajustes encimadas).

### B.1 Mismo eje que el archivo

- **Familias:** Inter en toda la interfaz (menú, tablas, rótulos, campos, botones). Courier Prime solo en `.codigo` (C14R3 en migas, eyebrow,
  listas de la caja y de verificaciones, consultas; número de caja en la lista de cajas) y en `<code>` (rutas, ids de Drive, columnas de la planilla).
  Sale Playfair Display y sale el Courier general.
- **Escala y pesos:** los tres niveles del archivo (título 24/28 px, texto 16 px, dato 14 px), 400 y 600. Títulos de tarjeta como los títulos de
  bloque del archivo: 16 px 600 con línea de 2 px en tinta. Sin rótulos en caja alta espaciada (el eyebrow es dato secundario).
- **Forma:** radio de 2 px, líneas de 1 px, sin sombras ni bandas. Foco: anillo azul de 3 px con 2 px de separación; celeste `barra-foco` sobre la barra.
- **Tablas:** como las listas del archivo: encabezado 14 px 600 en `texto-2` sobre `fondo-2`, filas con línea de 1 px, hover en `azul-suave`,
  cifras tabulares. En celular cada tabla se desplaza dentro de su propia región (con foco y nombre únicos), nunca la página.
- **Controles:** 40 px de alto (densidad de herramienta; el sitio usa 44), chicos de 32 px. Borde `borde` (4,3:1 o más en todos los fondos).

### B.2 Contraste por colorimetría

| Zona | Color | Por qué |
|---|---|---|
| Barra superior y navegación | `barra` #141B24, texto `barra-texto-2` #B9C3CF, activo blanco 600 sobre `barra-2` #243142 con **filete inferior de 3 px rojo-naranja** | De un vistazo: “estoy en la herramienta”. El sitio tiene cabecera blanca. |
| Contadores (verificaciones, consultas, parecidos) | `rojo` #EF6A3A con tinta encima | Rojo-naranja = dónde estás y cuánto falta. Nunca texto rojo sobre claro. |
| Área de trabajo | `area` #EDF0F3 (gris frío) | Separa las tarjetas sin sombras; el sitio es blanco con paneles cálidos. |
| Tarjetas, tablas, filtros | `fondo` #FFFFFF; encabezados y fieldset en `fondo-2` #F5F7F9 | |
| Acciones y enlaces | `azul` #0D5C9E | Igual que el archivo. Guardar pasa de naranja a azul. |
| Crear / recuperar · baja / deshacer | `digital` #2F5A2A · `error` #A12A1A | Se conservan los colores por intención del back. |
| Ingreso | Fondo `barra`, logo grande en blanco, formulario en tarjeta blanca con filete rojo-naranja | Misma colorimetría que la barra. |

**Estados, forma + color** (ninguno depende solo del color):

- Chips: ok = **círculo lleno**, borde lleno, verde · atención (no públicos, parcial, propuesto, no publicado) = **rombo** y **borde discontinuo**,
  ocre · error / baja / año no confirmado / conflicto = **triángulo**, rojo · acción pendiente (🔔 N, consulta nueva, se aplican) = **cuadrado**, azul.
  Neutro: sin marca. Las marcas son `::before` sin texto (no las lee el lector de pantalla).
- Año no confirmado en texto: rojo con **subrayado punteado**. Campo editado a mano: **barra interior de 4 px** ocre + candado.
- Avisos y leyendas: **barra izquierda de 4 px** (ok verde, error rojo, atención ocre, restringido rojo-ladrillo `pedido`, neutra gris).
- Verificaciones: barra izquierda ocre (pendiente), gris (informativa), verde (resuelta). Zona de riesgo y confirmaciones: filete superior rojo de 4 px.
- Doble validación: botón rojo con pulso (sin pulso con `prefers-reduced-motion`) y aviso rojo al lado.

### B.3 Logo

- Encabezado: [`public/admin/img/logo-claro.svg`](../../public/admin/img/logo-claro.svg) (38 px de alto), con el rótulo chico “Backoffice” separado
  por un filete. Es el logo calado (cajas blancas, letras que dejan ver el fondo), derivado de `sitio/img/logo-calado-blanco.svg`; el archivo usa
  `logo-tinta.svg` sobre blanco. Enlaza a Cajas.
- Ingreso: el mismo SVG, 300 px de ancho (72 % del ancho en celular), centrado, como `h1` con texto alternativo; “Archivo · Backoffice” debajo.
  El formulario ahora está dentro de `<main>` (antes axe marcaba la falta de región principal).

### B.4 Contrastes

Medidos con `node docs/diseno/contraste.js admin` sobre `public/admin/tokens.css`.

| Texto / elemento | Fondo | Uso | Contraste | Mínimo AA |
|---|---|---|---|---|
| `barra-texto` #FFFFFF | `barra` #141B24 | Logo, ítem activo y Salir en la barra | 17,33:1 ✔ | 4,5:1 |
| `barra-texto` #FFFFFF | `barra-2` #243142 | Ítem activo y hover de la barra | 13,18:1 ✔ | 4,5:1 |
| `barra-texto-2` #B9C3CF | `barra` #141B24 | Ítems de navegación, rótulo “Backoffice”, usuario; rótulo del login | 9,71:1 ✔ | 4,5:1 |
| `barra-texto-2` #B9C3CF | `barra-2` #243142 | Ítem en hover antes de aclararse | 7,39:1 ✔ | 4,5:1 |
| `texto` #1D1815 | `rojo` #EF6A3A | Contador (verificaciones, consultas, parecidos) | 5,69:1 ✔ | 4,5:1 |
| `rojo` #EF6A3A | `barra` #141B24 | Marca del ítem activo (3 px) y filete del login | 5,60:1 ✔ | 3:1 |
| `rojo` #EF6A3A | `barra-2` #243142 | Marca del ítem activo sobre su fondo | 4,26:1 ✔ | 3:1 |
| `barra-foco` #8CC8FF | `barra` #141B24 | Anillo de foco en la barra y el fondo del login | 9,75:1 ✔ | 3:1 |
| `barra-foco` #8CC8FF | `barra-2` #243142 | Anillo de foco en el ítem activo | 7,42:1 ✔ | 3:1 |
| `texto` #1D1815 | `area` #EDF0F3 | Títulos y texto sobre el área de trabajo | 15,38:1 ✔ | 4,5:1 |
| `texto-2` #5A544D | `area` #EDF0F3 | Migas, eyebrow, pie, nota sobre el área | 6,53:1 ✔ | 4,5:1 |
| `azul` #0D5C9E | `area` #EDF0F3 | Enlaces sobre el área (migas en hover, avisos) | 6,04:1 ✔ | 4,5:1 |
| `texto` #1D1815 | `fondo` #FFFFFF | Texto en tarjetas y tablas | 17,59:1 ✔ | 4,5:1 |
| `texto-2` #5A544D | `fondo` #FFFFFF | Dato secundario en tarjetas | 7,47:1 ✔ | 4,5:1 |
| `texto-2` #5A544D | `fondo-2` #F5F7F9 | Encabezado de columnas, fieldset | 6,96:1 ✔ | 4,5:1 |
| `texto` #1D1815 | `azul-suave` #EAF1F8 | Fila en hover, término elegido | 15,44:1 ✔ | 4,5:1 |
| `azul` #0D5C9E | `fondo` #FFFFFF | Enlaces, códigos en tablas | 6,90:1 ✔ | 4,5:1 |
| `azul-osc` #0A4A80 | `azul-suave` #EAF1F8 | Enlace en fila con hover, chip de acción pendiente | 8,00:1 ✔ | 4,5:1 |
| `blanco` #FFFFFF | `azul` #0D5C9E | Botón primario (Guardar, Ingresar) | 6,90:1 ✔ | 4,5:1 |
| `blanco` #FFFFFF | `digital` #2F5A2A | Botón crear o recuperar | 8,03:1 ✔ | 4,5:1 |
| `blanco` #FFFFFF | `error` #A12A1A | Botón de baja y doble validación armada | 7,34:1 ✔ | 4,5:1 |
| `error` #A12A1A | `fondo` #FFFFFF | Botón de borde rojo, “Zona de riesgo”, aviso de doble validación | 7,34:1 ✔ | 4,5:1 |
| `digital` #2F5A2A | `digital-suave` #E6EEDF | Chip ok (círculo) | 6,75:1 ✔ | 4,5:1 |
| `duda` #7A5A0E | `duda-suave` #F4E8C4 | Chip atención (rombo, borde discontinuo), leyenda | 5,21:1 ✔ | 4,5:1 |
| `error` #A12A1A | `error-suave` #F9E1DA | Chip error/baja/sin confirmar (triángulo), aviso de error | 5,87:1 ✔ | 4,5:1 |
| `pedido` #A8361A | `pedido-suave` #FDEDE5 | Leyenda “Restringido” | 5,74:1 ✔ | 4,5:1 |
| `duda` #7A5A0E | `fondo` #FFFFFF | Candado de campo editado a mano | 6,37:1 ✔ | 4,5:1 |
| `error` #A12A1A | `fondo` #FFFFFF | Año no confirmado (subrayado punteado) | 7,34:1 ✔ | 4,5:1 |
| `texto` #1D1815 | `digital-suave` #E6EEDF | Texto en aviso ok | 14,79:1 ✔ | 4,5:1 |
| `texto` #1D1815 | `error-suave` #F9E1DA | Texto en aviso de error y leyenda de baja | 14,08:1 ✔ | 4,5:1 |
| `borde` #767069 | `fondo` #FFFFFF | Borde de controles (1.4.11) | 4,89:1 ✔ | 3:1 |
| `borde` #767069 | `fondo-2` #F5F7F9 | Borde de controles en fieldset | 4,56:1 ✔ | 3:1 |
| `borde` #767069 | `area` #EDF0F3 | Borde de controles sobre el área | 4,28:1 ✔ | 3:1 |
| `azul` #0D5C9E | `fondo` #FFFFFF | Anillo de foco en el área | 6,90:1 ✔ | 3:1 |
| `azul` #0D5C9E | `area` #EDF0F3 | Anillo de foco sobre el área | 6,04:1 ✔ | 3:1 |
| `duda` #7A5A0E | `fondo` #FFFFFF | Barra de 4 px del campo bloqueado | 6,37:1 ✔ | 3:1 |

### B.5 Cambios de marcado (sin cambios de comportamiento ni de textos)

- `_layout.php`: logo + “Backoffice” en lugar de “Archivo · Las cajas de Aída”; `aria-label` en la navegación; las regiones de tabla desplazable
  se numeran cuando hay más de una en la página (axe: `landmark-unique` en Unificar).
- `login.php`: logo grande y `<main>`; sale el título “Las cajas de *Aída*” (lo dice el logo).
- Pestañas de Verificaciones y Vocabularios con `aria-label` propio; encabezado vacío de Unificar con texto para lector de pantalla.
- Clase `.codigo` en códigos y números de caja (registro, caja, cajas, consultas, consulta, verificaciones); `.etiqueta` en los rótulos de
  Tipo de documento y Descriptores (antes estilo en línea con variables viejas); `correccion.php` usa `--c-error-suave`.

### B.6 Verificación (16/11/2026)

Playwright + Chrome y axe-core 4.11 (WCAG 2.0/2.1/2.2 A y AA + buenas prácticas), base `aida_bk` (esquema + demo + migraciones, 2 consultas de
prueba, C14R4 con campos bloqueados, C14R2 dado de baja), puerto 8105, sesión creada directamente. Script `/home/claude/qa/backoffice-sistema/scripts/qa.py`;
capturas en `/home/claude/qa/backoffice-sistema/antes/` y `despues/`, informes `informe-antes.json` e `informe-despues.json`.

- 32 vistas a 390 y 1366: ingreso (y con error), cajas (y filtrada), caja 14 y 2, nueva caja, unificar, confirmar baja de caja, registro editar
  (C14R4, bloqueado), registro C2R5, registro dado de baja, nuevo registro, confirmar baja de registro, procesos, importar, corrección masiva,
  verificaciones (pendientes, informativas, resueltas), consultas y consulta, vocabularios (tipos y descriptores), curaduría, informe de curaduría,
  almacenamiento, base de datos, vista previa de registro y de caja, 404, doble validación armada.
- **Antes:** axe con hallazgos en 9 vistas por ancho (región principal en el ingreso, `landmark-unique` en verificaciones, vocabularios y unificar, encabezado vacío en unificar), desborde a 390 px en consultas (53 px) y
  verificaciones, una región sin anillo de foco. **Después: axe 0, desborde 0, errores de JS 0.** Familias en uso: solo Inter y Courier Prime.
- Foco: 30 tabulaciones en cajas, registro y verificaciones y 8 en el ingreso, a 390 y 1366: todas con anillo visible.

### B.7 Pendientes

- Algunos detalles de verificaciones guardados en la base traen comillas « » (por ejemplo “PRIVADO dice «VERLO»”): vienen del texto de la
  incidencia guardada antes del cambio de comillas, no de la plantilla ni de `src/` (que ya no genera « »). Quedan así en las incidencias viejas.
- La vista previa (banner gris pizarra, `public/sitio/vista-previa.css`) no se tocó: es del sitio.
- El emoji 🔔 del menú y de los chips sigue (es texto): si el PM quiere, reemplazarlo por un ícono SVG del sistema.

---

# Sitio institucional: dirección B inmersiva (referencia)

Lo que sigue describe la dirección B tal como quedó el 16/11/2026 en `35b0f85`. **Ya no se aplica al archivo.**

## 1. Criterio

- **Las paredes de la muestra.** Azul `#0D5C9E` y rojo-naranja `#EF6A3A` del montaje en el Museo del Cine, sobre papel crema `#F5F0E4` y tinta `#1D1815`.
  El azul es la pared de las pantallas de entrada (hero del inicio, banda de la caja y de la ficha) y el color de acción; el rojo, el bloque que encastra.
- **Bloques que encastran, como las cajas del logo.** Sin redondeos; filetes gruesos (10 px rojo bajo las bandas, 4 px tinta bajo la cabecera); tarjetas con
  borde de tinta y, al pasar el mouse, una sombra seca desplazada (`6px 6px 0`) como cajas apiladas; un cuadrado rojo antes de cada título de sección y
  sobre la foto del hero.
- **Fotos en blanco y negro.** La foto del hero (material de las cajas, provisto por el cliente) va en escala de grises con grano. **Nunca** se filtran ni se
  les pone grano a los documentos: miniaturas y visor muestran el escaneo tal cual.
- **La máquina de escribir de C, donde el archivo «se escribió a máquina»:** citas, códigos (`C2R5`), rótulos, metadatos, conteos, el buscador y el
  título que se tipea con cursor (§5). La lectura larga, en cambio, va en una sans legible (§3).
- **La marca no se antepone al acceso.** El rojo de marca no llega a 3:1 sobre crema: nunca es texto ni borde de control; con texto encima va solo tinta.
- **Estados con forma, no solo color** (§7).

## 2. Paleta y contrastes

Medidos con la fórmula de luminancia relativa de WCAG 2.1 sobre los valores de `tokens.css`
(`node docs/diseno/contraste.js` regenera la tabla). ✔ = cumple el mínimo indicado. El grano sobre el azul es ruido negro al 7 % como máximo:
solo oscurece el fondo, así que el texto blanco gana contraste (fila «peor caso» calculada con la mezcla).

| Texto / elemento | Fondo | Uso | Contraste | Mínimo AA |
|---|---|---|---|---|
| `tinta` #1D1815 | `crema` #F5F0E4 | Títulos y texto de interfaz | 15,47:1 ✔ | 4,5:1 |
| `texto` #2B231C | `crema` #F5F0E4 | Lectura larga (descripción, resumen, «Sobre») | 13,58:1 ✔ | 4,5:1 |
| `texto` #2B231C | `blanco` #FFFFFF | Lectura en tarjetas | 15,44:1 ✔ | 4,5:1 |
| `texto-2` #4A3D31 | `crema` #F5F0E4 | Texto secundario | 9,22:1 ✔ | 4,5:1 |
| `texto-2` #4A3D31 | `blanco` #FFFFFF | Extracto en tarjetas | 10,48:1 ✔ | 4,5:1 |
| `apagado` #5E5043 | `crema` #F5F0E4 | Metadatos, ayudas | 6,83:1 ✔ | 4,5:1 |
| `apagado` #5E5043 | `blanco` #FFFFFF | Datos a máquina en tarjetas | 7,76:1 ✔ | 4,5:1 |
| `apagado` #5E5043 | `crema-2` #ECE4D3 | Metadatos sobre crema-2 | 6,14:1 ✔ | 4,5:1 |
| `enlace` #0D5C9E | `crema` #F5F0E4 | Enlaces | 6,07:1 ✔ | 4,5:1 |
| `enlace` #0D5C9E | `blanco` #FFFFFF | Enlaces en tarjetas y formulario | 6,90:1 ✔ | 4,5:1 |
| `enlace` #0D5C9E | `crema-2` #ECE4D3 | Enlaces sobre crema-2 | 5,46:1 ✔ | 4,5:1 |
| `enlace-hover` #08417A | `crema` #F5F0E4 | Enlace hover | 9,02:1 ✔ | 4,5:1 |
| `rotulo` #B8391A | `crema` #F5F0E4 | Rótulos a máquina (kicker, dt de la ficha) | 5,08:1 ✔ | 4,5:1 |
| `rotulo` #B8391A | `blanco` #FFFFFF | Rótulo «Caja» en tarjeta | 5,77:1 ✔ | 4,5:1 |
| `blanco` #FFFFFF | `azul` #0D5C9E | Texto sobre la pared azul; botón primario | 6,90:1 ✔ | 4,5:1 |
| `blanco` #FFFFFF | `azul-con-grano` #0C5693 | Texto sobre azul con grano (peor caso) | 7,59:1 ✔ | 4,5:1 |
| `rotulo-claro` #FFD8A8 | `azul` #0D5C9E | Rótulos y «de Aída» sobre azul | 5,14:1 ✔ | 4,5:1 |
| `blanco` #FFFFFF | `tinta` #1D1815 | Hover del botón primario | 17,59:1 ✔ | 4,5:1 |
| `tinta` #1D1815 | `rojo` #EF6A3A | Botón sobre azul (Buscar del hero); etapas rojas | 5,69:1 ✔ | 4,5:1 |
| `crema` #F5F0E4 | `tinta` #1D1815 | Pie, etapa tinta, barra del visor | 15,47:1 ✔ | 4,5:1 |
| `blanco` #FFFFFF | `azul-osc` #0A4A80 | Visor | 9,11:1 ✔ | 4,5:1 |
| `digital` #2F5A2A | `digital-suave` #E6EEDF | Badge Documento digital | 6,75:1 ✔ | 4,5:1 |
| `pedido` #A8361A | `pedido-suave` #FDEDE5 | Badge Consulta a pedido | 5,74:1 ✔ | 4,5:1 |
| `duda` #7A5A0E | `duda-suave` #F4E8C4 | Badge Año sin confirmar | 5,21:1 ✔ | 4,5:1 |
| `duda` #7A5A0E | `crema` #F5F0E4 | Año sin confirmar en texto | 5,60:1 ✔ | 4,5:1 |
| `duda` #7A5A0E | `blanco` #FFFFFF | Año sin confirmar en tarjeta | 6,37:1 ✔ | 4,5:1 |
| `error` #A12A1A | `error-suave` #F9E1DA | Resumen de errores | 5,87:1 ✔ | 4,5:1 |
| `error` #A12A1A | `blanco` #FFFFFF | Error junto al campo | 7,34:1 ✔ | 4,5:1 |
| `borde` #7D6C56 | `crema` #F5F0E4 | Borde de controles (1.4.11) | 4,45:1 ✔ | 3:1 |
| `borde` #7D6C56 | `blanco` #FFFFFF | Borde de campos | 5,06:1 ✔ | 3:1 |
| `tinta` #1D1815 | `crema` #F5F0E4 | Anillo de foco y bordes de tarjeta | 15,47:1 ✔ | 3:1 |
| `blanco` #FFFFFF | `azul` #0D5C9E | Anillo de foco sobre azul | 6,90:1 ✔ | 3:1 |
| `azul` #0D5C9E | `blanco` #FFFFFF | Barra de avance y checkbox | 6,90:1 ✔ | 3:1 |
| `rojo` #EF6A3A | `crema` #F5F0E4 | Rojo de marca como texto (NO usar) | 2,72:1 | — |
| `rojo` #EF6A3A | `azul` #0D5C9E | Cursor y encastre rojo sobre azul (decorativo) | 2,23:1 | — |

Notas:
- **Texto de lectura `texto` #2B231C (13,6:1), no la tinta pura (15,5:1)** y sobre crema, no sobre blanco: menos deslumbramiento en párrafos largos sin perder
  holgura sobre AA (y AAA, 7:1).
- **El rojo `#EF6A3A`** se usa como superficie (botón «Buscar» del hero, etapas, encastres, pestaña de la tarjeta de caja, filetes) y como cursor.
  Como texto sobre claro se usa `rotulo` #B8391A (5,1:1).
- **Cursor rojo sobre azul (2,2:1):** es decorativo (no transmite información; el título está completo) y por eso no tiene mínimo; se ve por tamaño y por
  la diferencia de tono, no por luminancia.
- Sobre azul y tinta el anillo de foco es blanco (6,9:1 y 17,6:1).

## 3. Tipografía y lectura cómoda

| Rol | Familia | Celular | Escritorio (≥ 900 px) | Interlínea |
|---|---|---|---|---|
| Hero | Oswald 500, mayúsculas | 52 px | 88 px | 0,98 |
| H1 de página | Oswald 500, mayúsculas | 36 px | 52 px | 1,05 |
| H1 de ficha con título de más de 60 caracteres | Oswald 500, **sin** mayúsculas | 24 px | 35 px | 1,12 |
| H2 | Oswald 500, mayúsculas | 28 px | 36 px | 1,05 |
| H3, título de tarjeta | Oswald 500 (tarjetas sin mayúsculas) | 20–22 px | 20–24 px | 1,2 |
| Botones, navegación, facetas | Oswald 500, mayúsculas, interletra .06–.1em | 16–17 px | 16–17 px | — |
| **Lectura larga** (descripción, resumen de caja, «Sobre») | **Inter 400** | **17 px** | **18 px** | **1,65** |
| Interfaz, campos, extractos | Inter 400–600 | 15–17 px | 15–17 px | 1,55–1,6 |
| Máquina de escribir: código, metadatos de tarjeta, conteos, buscador, cita | Courier Prime 400/700 | 15–17 px | 15–17 px | 1,45–1,6 |
| Rótulos (kicker, `dt` de la ficha, «Caja», «Anterior») | Courier Prime 700, mayúsculas, interletra .14–.2em | 13 px | 13 px | — |
| Sin título | Courier Prime **itálica** | como su contexto | | |

**Por qué la lectura larga no va en máquina de escribir.** El PM aceptó la advertencia de C: la monoespaciada cansa en textos largos (todas las letras
miden lo mismo, la palabra pierde su forma y el ojo hace más fijaciones; en Courier, además, el trazo es fino y parejo). En el archivo se lee mucho:
descripciones de 150–300 palabras, resúmenes de caja, listas de 20 resultados. Con B la decisión cae sola, pero el concepto se conserva:

1. **Prosa en Inter 400** a 17 px (celular) / 18 px (escritorio), interlínea **1,65**, medida **66 caracteres** (`--medida: 66ch`), color `texto` #2B231C sobre crema.
   Clases `.lectura`, `.prosa`, `.ficha__texto` y el cuerpo del resumen de caja.
2. **Courier Prime solo en piezas cortas** que en el archivo físico eran «de máquina»: la cita (se copia tal cual, 2–3 líneas), los códigos, la línea de datos
   de cada tarjeta (`C1R3 · Caja 1: … · 1988`), los rótulos y conteos y el texto que se escribe en el buscador. Nunca un párrafo.
3. Courier Prime en metadatos va a **15 px y en 700 para el código**: compensa el trazo fino.
4. Los títulos de catálogo largos no se ponen en mayúsculas (una condensada en mayúsculas de 3 líneas se lee mal).
5. Registros sin título: Courier itálica (regla de UX §5.1): se distinguen por forma, no por color.

Fuentes de Google Fonts con `display=swap`: Oswald 400/500/600, Inter 400/500/600/700 + itálica 400, Courier Prime 400/700 + itálica 400.

## 4. Espaciado, forma y sombras

- **Espaciado** en base 4: `--e-1` 4 · `--e-2` 8 · `--e-3` 12 · `--e-4` 16 · `--e-5` 24 · `--e-6` 32 · `--e-7` 48 · `--e-8` 64 px.
  Margen lateral 16 px en celular y 32 px en escritorio; ancho máximo 1240 px.
- **Objetivo táctil** mínimo 44 × 44 px; opciones de faceta 40 px de alto con la etiqueta entera clicable.
- **Forma:** radios en 0 (las cajas del logo). Bordes de tarjeta 1,5 px; filete rojo `--banda` 10 px; encastre `--encastre` 14 px (marcador de la miniatura).
- **Sombras:** `--s-2` seca y desplazada (hover de tarjetas, cajas, etapas y vecinos); `--s-hoja` solo para el documento del visor.

## 5. El cursor: dónde se tipea y por qué

El efecto de tipeo va **solo en tres títulos de pantalla** (`h1[data-tipeo]`):

| Dónde | Por qué suma |
|---|---|
| **Inicio** («Las cajas de Aída») | Es la entrada: presenta la idea de la mujer detrás de la máquina de escribir una sola vez, sobre la pared azul. |
| **Ficha del documento** (el título del registro) | Es el momento del archivo: el título «se escribe» como en la ficha de catálogo mecanografiada. |
| **Caja** (el nombre de la obra) | El rótulo de la caja se tipea como el que se pegaba en la caja física. |

No va en Explorar, las cajas, «Sobre», formularios ni mensajes: ahí la persona viene a hacer algo y el efecto demoraría. El buscador conserva la máquina sin
movimiento: el texto que se escribe y el marcador van en Courier Prime y el cursor del campo (`caret-color`) es rojo.

Reglas (`public/sitio/sitio.js`, `estilo.css` «Título que se tipea»):
- **Una vez** por carga de página, a 38 ms por letra y como máximo **1,4 s** en total (títulos largos van más rápido).
- **Lectores de pantalla:** el `h1` contiene desde el principio una copia `.solo-lector` con el título completo; la copia que se tipea es `aria-hidden`.
  Verificado: durante el tipeo el árbol de accesibilidad expone `heading "La Historia Oficial" [level=1]`. Al terminar se restituye el marcado original.
- **Sin saltos:** todas las letras ocupan su lugar desde el inicio (se revelan con `opacity`), la página no se mueve.
- **`prefers-reduced-motion: reduce`:** no se tipea; el título está entero desde el primer cuadro con el cursor **fijo**.
- **Sin JS:** título entero con cursor fijo. Si el JS falla, una animación de resguardo muestra el título a los 1,2 s (nunca queda oculto).
- **El cursor** es un bloque rojo bajo la línea de base (la tecla subrayado de la máquina). Al terminar titila **4 veces (≈ 4 s, menos de 5 s: WCAG 2.2.2)** y queda fijo.

## 6. Estados de interacción

| Estado | Regla |
|---|---|
| Hover | Tarjetas, cajas, etapas y vecinos: borde tinta + sombra seca desplazada (sin desplazamiento con movimiento reducido) y subrayado rojo de 3 px en el título. Botón primario: azul → tinta. Secundario: se rellena de tinta. Enlaces: subrayado de 2 px y azul más oscuro. |
| Foco visible | Anillo de **3 px** tinta con 2 px de separación; **blanco** sobre azul, tinta y visor. En tarjetas-enlace el anillo rodea toda la tarjeta (`:has(a:focus-visible)`). |
| Activo | Botones se hunden 2 px; navegación actual con barra roja de 5 px (izquierda en el menú del celular); opción de faceta elegida con barra azul de 4 px + negrita; filtro activo como chip azul lleno. |
| Deshabilitado | Fondo `crema-2`, texto `apagado`, cursor no permitido. |
| Error | Borde y barra izquierda de 4 px `error`, mensaje con ícono debajo del campo, resumen arriba con borde de 8 px. |
| Cargando | Esqueletos con brillo suave (se detiene con movimiento reducido); lista al 60 % de opacidad mientras llega `explorar.json`. |

## 7. Estados del documento (badges) y visor

| Estado | Forma | Color | Ícono |
|---|---|---|---|
| Documento digital | **Borde lleno** + miniatura de hoja con renglones y esquina roja | `digital` sobre `digital-suave` | hoja con texto |
| Solo ficha | Borde lleno neutro, miniatura vacía con ícono | `texto-2` sobre blanco | ficha de catálogo |
| Consulta a pedido | **Borde rayado** (badge, miniatura, aviso y bloque Documento) | `pedido` sobre `pedido-suave` | candado |
| Año sin confirmar | Badge con **borde punteado**; en texto, **subrayado punteado** + «(sin confirmar)» + ícono | `duda` | «?» en círculo |
| Tipo de documento | Etiqueta neutra | tinta sobre blanco | — |

Los badges conservan su propio fondo dentro de la banda azul, así se leen igual que en las tarjetas.
**Visor:** pared azul oscuro con barra de tinta y filete rojo, botones en condensada blanca; el documento se muestra en blanco, sin grano ni filtro. La zona
del documento es una región enfocable (`tabindex="0"`) para poder desplazarla con el teclado.

## 8. Grano de película

`--grano`: ruido SVG propio, negro al 7 %. Solo en la pared azul (hero y bandas) y sobre la foto del hero. No va sobre el papel crema (donde se lee), ni
sobre tarjetas, ni sobre documentos o el visor. Es fijo (sin animación): la versión animada de C movía todo el fondo de forma permanente.

## 9. Responsive

Mobile first. Corte principal en **900 px** (navegación horizontal, columna de filtros, ficha en dos columnas, hero con foto a la derecha).
Cortes secundarios: 600 px (2 columnas de cajas; etapas 3 + 2), 1000 px (3 columnas de cajas, 5 etapas en fila).
En celular el hero pone texto y buscador primero y la foto (200 px) al pie, a sangre.

## 10. Vista previa del backoffice

Usa las mismas plantillas. El banner (`vista-previa.css`) sigue en gris pizarra con filete amarillo y letra del sistema: no comparte ningún color ni
tipografía con B, así que no se confunde con la página. Verificado a 390 y 1366.

## 11. Verificación (16/11/2026)

Playwright + Chrome (`/usr/bin/google-chrome`) y axe-core 4.11 (WCAG 2.0/2.1 A y AA + buenas prácticas), base `aida_dc` con las cajas 1, 2, 6 y 14
publicadas. Script: `/home/claude/qa/direccion-c/qa.py`; capturas e informe (`informe-qa.json`) en la misma carpeta.

- 24 vistas a 390 y 22 a 1366: inicio, explorar (sin filtros, filtrado, sin resultados, panel de filtros abierto), cajas (y sin coincidencias), caja 2 y 14,
  fichas digital / a pedido / solo ficha / año sin confirmar, visor abierto, consulta (a pedido, por el original, con errores, enviada), «Sobre», 404, 410,
  menú del celular, y vista previa de ficha y de caja no publicada. **axe-core: 0 hallazgos. Desborde horizontal: ninguno. Errores de JS: ninguno.**
- Foco: 25 tabulaciones en inicio, explorar y ficha, a 390 y 1366: todos con anillo visible de 3 px.
- Tipeo: lector de pantalla con el título completo durante el tipeo; con movimiento reducido, sin tipeo y título visible al primer cuadro; sin JS, título entero.
