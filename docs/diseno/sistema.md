# Sistema visual del sitio público

Diseño web · 15/11/2026 · rama `diseno/sitio-publico`. Parte de la identidad del sitio actual
([`referencias/identidad-sitio-actual.md`](referencias/identidad-sitio-actual.md)) y de la estructura de UX
([`../ux/sitio-publico.md`](../ux/sitio-publico.md)). Tokens: [`public/sitio/tokens.css`](../../public/sitio/tokens.css). Componentes: [`componentes.md`](componentes.md).
Maquetas: [`maquetas/index.html`](maquetas/index.html) · catálogo vivo: [`maquetas/componentes.html`](maquetas/componentes.html).

## 1. Criterio

- **Archivo, no museo.** Papel crema, tinta cálida, banda marrón y un solo acento naranja. El sepia es el ambiente, nunca un filtro sobre los documentos.
- **La marca no se antepone al acceso.** Donde un color de la identidad no llega a AA, se oscurece para texto y controles y el original queda como acento decorativo.
- **Tres voces tipográficas.** Playfair Display (títulos, la voz literaria), Inter (lectura y controles), Courier Prime (códigos `C14R3`, kickers y datos de archivo: el guiño a la máquina de escribir).
- **Estados con forma, no solo color.** Cada estado tiene ícono y un rasgo de borde propio (ver §6).
- **Sistema antes que composición.** Cada bloque es un componente independiente; si el cliente cambia el layout, se reordenan piezas sin tocar estilos.

## 2. Paleta y contrastes

Medidos con la fórmula de luminancia relativa de WCAG 2.1 sobre los valores de `tokens.css`
(`node docs/diseno/contraste.js` regenera la tabla si cambia un color). ✔ = cumple el mínimo indicado.

| Texto / elemento | Fondo | Uso | Contraste | Mínimo AA |
|---|---|---|---|---|
| `tinta` #281D15 | `papel` #F8F6F1 | Texto principal | 15,23:1 ✔ | 4,5:1 |
| `tinta` #281D15 | `papel-2` #F4EFE6 | Texto en tarjetas | 14,36:1 ✔ | 4,5:1 |
| `tinta` #281D15 | `pergamino` #F2EBDE | Texto en bloque Documento / cajas | 13,87:1 ✔ | 4,5:1 |
| `tinta-2` #544537 | `papel` #F8F6F1 | Texto secundario | 8,52:1 ✔ | 4,5:1 |
| `tinta-2` #544537 | `papel-2` #F4EFE6 | Extracto en tarjetas | 8,03:1 ✔ | 4,5:1 |
| `tinta-2` #544537 | `sepia` #E4DBCD | Texto sobre sepia (pestaña, deshabilitado) | 6,71:1 ✔ | 4,5:1 |
| `apagado` #6B5D49 | `papel` #F8F6F1 | Metadatos, ayudas | 5,92:1 ✔ | 4,5:1 |
| `apagado` #6B5D49 | `papel-2` #F4EFE6 | Datos de la tarjeta | 5,58:1 ✔ | 4,5:1 |
| `apagado` #6B5D49 | `pergamino` #F2EBDE | Datos de caja | 5,39:1 ✔ | 4,5:1 |
| `apagado` #6B5D49 | `sepia` #E4DBCD | Botón deshabilitado | 4,66:1 | — |
| `naranja-800` #8F4C18 | `papel` #F8F6F1 | Enlaces | 6,05:1 ✔ | 4,5:1 |
| `naranja-800` #8F4C18 | `papel-2` #F4EFE6 | Enlaces en tarjetas | 5,70:1 ✔ | 4,5:1 |
| `naranja-800` #8F4C18 | `pergamino` #F2EBDE | Enlaces en bloque Documento | 5,51:1 ✔ | 4,5:1 |
| `blanco` #FFFFFF | `naranja-700` #A14B14 | Botón primario | 5,95:1 ✔ | 4,5:1 |
| `blanco` #FFFFFF | `naranja-900` #7A3E10 | Botón primario hover | 8,32:1 ✔ | 4,5:1 |
| `tinta` #281D15 | `naranja-suave` #F6E3CF | Chip activo, opción elegida | 13,16:1 ✔ | 4,5:1 |
| `naranja-900` #7A3E10 | `papel` #F8F6F1 | × del chip | 7,70:1 ✔ | 3:1 |
| `crema` #F3EAD9 | `marron` #493527 | Texto en cabecera/hero/pie | 9,65:1 ✔ | 4,5:1 |
| `ambar` #EA9A3E | `marron` #493527 | «de Aída», cifras, nav activa | 5,04:1 ✔ | 4,5:1 |
| `crema` #F3EAD9 | `marron-2` #3A2A1F | Visor | 11,48:1 ✔ | 4,5:1 |
| `ambar` #EA9A3E | `marron-2` #3A2A1F | Foco sobre oscuro (visor) | 6,00:1 ✔ | 3:1 |
| `digital` #3F6139 | `digital-suave` #E3EADB | Badge Documento digital | 5,72:1 ✔ | 4,5:1 |
| `pedido` #7A3E10 | `pedido-suave` #F6E3CF | Badge Consulta a pedido | 6,66:1 ✔ | 4,5:1 |
| `duda` #7A5A0E | `duda-suave` #F4E8C4 | Badge Año sin confirmar | 5,21:1 ✔ | 4,5:1 |
| `duda` #7A5A0E | `papel` #F8F6F1 | Año sin confirmar en texto | 5,90:1 ✔ | 4,5:1 |
| `error` #8C3A2E | `error-suave` #F2D9D2 | Resumen de errores | 5,66:1 ✔ | 4,5:1 |
| `error` #8C3A2E | `papel-2` #F4EFE6 | Error junto al campo | 6,64:1 ✔ | 4,5:1 |
| `borde` #8C7A62 | `papel` #F8F6F1 | Borde de controles (1.4.11) | 3,83:1 ✔ | 3:1 |
| `borde` #8C7A62 | `blanco` #FFFFFF | Borde de campos | 4,14:1 ✔ | 3:1 |
| `borde` #8C7A62 | `papel-2` #F4EFE6 | Borde de controles en formulario | 3,61:1 ✔ | 3:1 |
| `foco` #281D15 | `papel` #F8F6F1 | Anillo de foco | 15,23:1 ✔ | 3:1 |
| `naranja-700` #A14B14 | `papel` #F8F6F1 | Borde activo, barra de avance | 5,51:1 ✔ | 3:1 |
| `digital` #3F6139 | `blanco` #FFFFFF | Barra de avance (relleno) | 7,05:1 ✔ | 3:1 |
| `naranja` #DD7F2C | `papel` #F8F6F1 | Naranja de marca como texto (NO usar) | 2,72:1 | — |
| `blanco` #FFFFFF | `naranja` #DD7F2C | Texto blanco sobre naranja de marca (NO usar) | 2,94:1 | — |
| `gris-visor` #A8957E | `marron` #493527 | Botón del visor deshabilitado | 3,99:1 | — |

### Correcciones respecto del sitio actual

| Token del sitio actual | Problema | Decisión |
|---|---|---|
| `primary` 28 72% 52% (#DD7F2C) | 2,7:1 como texto; 2,9:1 con texto blanco. No sirve ni para botones ni para enlaces. | Queda como **naranja de marca decorativo** (filetes, ilustración, marcador de la miniatura). Botón primario en `naranja-700` #A14B14 (blanco 6,0:1); enlaces en `naranja-800` #8F4C18 (6,1:1, el mismo del backoffice). |
| `muted-foreground` 25 15% 45% (#847062) | 4,3:1 sobre el fondo: no llega a AA. | `apagado` #6B5D49 (5,9:1). |
| `border` 32 25% 82% (#DDD2C6) | 1,4:1: invisible como borde de campo o checkbox (WCAG 1.4.11). | Queda como `filete` para separadores. Los controles usan `borde` #8C7A62 (3,8:1). |
| `amber-glow` 32 80% 58% | Solo funciona sobre la banda marrón (5,0:1). | Se usa únicamente sobre `marron`/`marron-2`: «de Aída», cifras, navegación activa y anillo de foco en fondo oscuro. |

Se agregan colores de estado (`digital`, `pedido`, `duda`, `error`) en tonos tierra que conviven con la paleta.

## 3. Tipografía

| Rol | Familia | Celular | Escritorio (≥ 900 px) | Interlínea |
|---|---|---|---|---|
| Hero | Playfair Display 400 (2.ª línea en itálica ámbar) | 42 px | 68 px | 1,02 |
| H1 | Playfair Display 400 | 32 px | 44 px | 1,15 |
| H2 | Playfair Display 400 | 24 px | 32 px | 1,15 |
| H3 / título de tarjeta | Playfair Display 400 | 19 / 18 px | 21 / 18 px | 1,15–1,25 |
| Cuerpo | Inter 400 | 17 px | 17 px | 1,55 |
| Metadatos, etiquetas, ayudas | Inter 400–500 | 15 px | 15 px | 1,4 |
| Kicker, rótulos de la ficha, código | Courier Prime 400–700, mayúsculas, interletra .14–.2em | 13 px | 13 px | — |
| Cifras del inicio | Playfair Display 400 | 28 px | 36 px | 1,1 |

- Cuerpo en 17 px: el público incluye lectores mayores y se lee mucho texto de catálogo en el celular.
- 13 px solo en mayúsculas espaciadas o números (nunca párrafos). Ancho de párrafo máximo: 68 caracteres.
- Los registros sin título van en **itálica** (regla de UX §5.1): la itálica de Playfair los distingue sin color.
- Se cargan desde Google Fonts con `display=swap`. Pesos: Inter 400/500/600/700, Playfair 400/600 + itálicas, Courier Prime 400/700.

## 4. Espaciado, radios y sombras

- **Espaciado** en base 4: `--e-1` 4 · `--e-2` 8 · `--e-3` 12 · `--e-4` 16 · `--e-5` 24 · `--e-6` 32 · `--e-7` 48 · `--e-8` 64 px.
  Margen lateral 16 px en celular y 32 px en escritorio; ancho máximo 1240 px. Entre secciones del inicio, 48 px.
- **Objetivo táctil** mínimo 44 × 44 px (`--toque`); opciones de faceta 40 px de alto con la etiqueta entera clicable (UX §6).
- **Radios**: 4 px etiquetas y campos · 8 px tarjetas y botones (el `.5rem` del sitio actual) · 12 px paneles y visor · píldora para chips.
- **Sombras** en tinta cálida (rgba de #281D15), nunca negro: `--s-1` reposo, `--s-2` hover de tarjetas, `--s-hoja` para el papel del visor.

## 5. Estados de interacción

| Estado | Regla |
|---|---|
| Hover | Tarjetas: sombra `--s-2` y subrayado naranja de 2 px en el título. Botón primario: `naranja-900`. Secundario: fondo `papel-2` y borde `tinta`. Enlaces: subrayado de 2 px. |
| Foco visible | Anillo de **3 px** `tinta` con 2 px de separación en todo el sitio; sobre la banda marrón y el visor, ámbar. En tarjetas-enlace el anillo rodea toda la tarjeta (`:has(a:focus-visible)`). Nunca se quita el foco sin reemplazo. |
| Activo | Botón primario se hunde 1 px; chips y opciones elegidas en `naranja-suave` + negrita; navegación actual con barra ámbar de 3 px. |
| Deshabilitado | Fondo `sepia`, texto `apagado`, cursor no permitido. Se usa poco: opciones de etapa sin asignar, página anterior en la página 1 del visor, «Más relevantes» sin búsqueda. |
| Error | Borde y barra izquierda de 4 px `error`, mensaje con ícono de alerta debajo del campo y resumen arriba con enlaces. |
| Cargando | Esqueletos de tarjeta con brillo suave (se detiene con `prefers-reduced-motion`). |

## 6. Estados del documento (badges)

| Estado | Forma | Color | Ícono |
|---|---|---|---|
| Documento digital | Borde lleno | `digital` sobre `digital-suave` | hoja con texto |
| Solo ficha | Borde lleno sin relleno (neutral, no es un error) | `tinta-2` | ficha de catálogo |
| Consulta a pedido | **Borde punteado** | `pedido` sobre `pedido-suave` | candado |
| Año sin confirmar | Texto con **subrayado punteado** + «(sin confirmar)» + ícono | `duda` | signo de pregunta en círculo |
| Tipo de documento | Etiqueta neutra | `tinta-2` sobre `papel` | — |

Años: «1972?» → **1972 (sin confirmar)** marcado; «1981/82» es rango y se muestra tal cual; sin año → «Sin fecha».
En la ficha, debajo del año sin confirmar se muestra el valor del catálogo («En el catálogo: «1972?»») y la cita lo pone entre corchetes con «?».

## 7. Iconografía

- Sprite SVG propio (26 íconos) en `maquetas/maqueta.js` (`ICONOS`) y en el catálogo de componentes.
  Caja 24 × 24, trazo 1,75, puntas y uniones redondeadas, `currentColor`: heredan el color del texto.
- Siempre `aria-hidden="true"` y `focusable="false"`; el texto accesible lo da la etiqueta o un `aria-label` en el botón.
- Sin librerías de íconos ni logos de terceros. La marca es un rótulo tipográfico con un ícono de caja de archivo propio.

## 8. Tratamiento del sepia y del archivo

- **Fondo**: papel crema plano. La textura de papel (ruido SVG propio, opacidad 5 %) solo aparece en el hero, la miniatura y la hoja del visor.
- **Documentos sin filtro.** Las miniaturas y el visor muestran el escaneo tal cual: el sepia no se aplica sobre material de archivo (fidelidad y legibilidad).
- **Rótulo de caja**: la tarjeta de caja tiene pestaña superior y número en serif naranja, como una caja de depósito.
- **Hero**: ilustración SVG propia —rollo de film, hoja mecanografiada con correcciones a mano en naranja y teclas de máquina de escribir— sin fotos ni personas. En celular queda detrás del texto al 13 % de opacidad; en escritorio, a la derecha.
- **Courier Prime** para códigos y datos de archivo, no para lectura larga.

## 9. Responsive

Mobile first. Un solo corte principal en **900 px** (columna de filtros, ficha en dos columnas, navegación horizontal).
Cortes secundarios para grillas: 600 px (2 columnas de cajas, 3 de etapas) y 1000 px (3 columnas de cajas, 5 de etapas).
Verificado a 390 y 1366 px sin desborde horizontal (ver §10).

## 10. Verificación (15/11/2026)

Playwright + Chrome (`/usr/bin/google-chrome`) y axe-core 4.11 (WCAG 2.0/2.1 A y AA + buenas prácticas), 21 vistas a 390 px y 20 a 1366 px
(incluye estados: panel de filtros abierto, sin resultados, error, cargando, etapas sin asignar, visor abierto, errores de formulario).

- axe-core: **0 hallazgos** en todas las vistas.
- Desborde horizontal a 390 px: ninguno.
- Foco: 25 tabulaciones en inicio, explorar y ficha, a 390 y 1366: todos los elementos con anillo visible de ≥ 2 px.
- Capturas en `/home/claude/qa/diseno/` (`<vista>-390.png`, `<vista>-1366.png`, `foco-*.png`, `informe-qa.json`).
