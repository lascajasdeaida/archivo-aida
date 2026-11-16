# Sistema visual del sitio público

Diseño web · 16/11/2026 · rama `diseno/direccion-c`. **Dirección B · Museografía con la máquina de escribir y el cursor de C**
(decisión del PM del 15/11/2026, ver [`../decisiones.md`](../decisiones.md) «Diseño del sitio público»). Reemplaza el sistema
anterior (papel sepia + Playfair), que queda en la historia de git.
Referencias: propuestas `equipo/direcciones/propuestas/b/` y `…/c/` (construidas con `equipo/direcciones/_build/`) y la estructura de UX
([`../ux/sitio-publico.md`](../ux/sitio-publico.md)). Tokens: [`public/sitio/tokens.css`](../../public/sitio/tokens.css). Componentes: [`componentes.md`](componentes.md).

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
