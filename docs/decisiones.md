# Decisiones y reglas de negocio

Registro de las decisiones que condicionan el código. Cada una con su fecha y su motivo.

## Datos de origen

- **Excel del catálogo** — «Catálogo Aída Parte 1 BACKUP CONSERVACIÓN .xlsx», en el Drive del equipo de
  digitalización. Lo siguen completando. Una pestaña por caja («Caja 14 Zsherezade SDF»); la pestaña «ORIGINAL» se ignora.
- **Archivos** — carpeta de Drive «Selección de Cajas», una subcarpeta por caja (`C14/`) con un archivo por registro (`C14R3.pdf`).
  La entrega es **parcial e incremental**: van subiendo a medida que digitalizan (nov 2026).
- El equipo de digitalización **no va a cambiar** su forma de trabajo. La plataforma se adapta (14/11/2026).

## Estructura del Excel

- Fila 1: encabezados. Fila 2: subencabezados (Tipo 1/2/3, Descriptor 1..4). Datos desde la fila 3.
- Columnas **A–S fijas** en las 70 pestañas: Caja, Nombre de caja, N.º de registro, Tipo 1–3, Autor, Título, Lugar,
  Fecha como dice el documento, Año, Páginas, Resumen, Descriptor 1–4, ISBN, Observaciones/Editorial.
- Desde la **T** el orden varía por pestaña. Se ubican por el nombre del encabezado: Edición de catálogo,
  Observaciones de conservación, Nivel de acceso, Observaciones de digitalización, Nombre de archivo digital,
  Digitalización, Selección para digitalización, PRIVADO.
- En algunas pestañas (ej. Caja 2) los encabezados «Digitalización» y «Nombre de archivo digital» están **invertidos**
  respecto de los datos. Esas dos columnas se verifican por contenido (booleanos vs. códigos C#R#).

## Clave de los registros

- La clave es **caja + n.º de registro** → `C{caja}R{registro}`, sin ceros (`C14R3`).
- La columna «Nombre de archivo digital» **no manda**: es solo control cruzado. Tiene errores de tipeo reales
  (C43R5–R14 dicen `C35R…`, C10R14 dice `C10R12`, C43R34 dice `C43F34`). Cada diferencia se reporta.
- Al leer archivos se aceptan ceros a la izquierda: `C10R02.pdf` = `C10R2`.
- Una vez que hay archivos digitalizados, la nomenclatura de un registro queda congelada.

## Acceso (público / restringido)

- Por defecto, **todo es público** (14/11/2026).
- El equipo marca lo privado en la columna **PRIVADO**: VERDADERO → `restringido`; «VERLO» → `a_revisar`.
  Si tiene otro texto, se toma como público y se reporta (C6R5 tiene texto pegado por error).
- Si alguna vez completan «Nivel de Acceso» con Público/Restringido, esa columna tiene prioridad.
- Restringido = la ficha se ve, el documento se consulta a pedido.

## Digitalizado

- Lo define **la existencia del archivo**, no la columna «Digitalización» del Excel.
- Si el Excel dice digitalizado y el archivo no está → `PENDIENTE_DE_SUBIR` (informativo, es lo esperable mientras suben).
- Si hay archivo y el Excel dice que no → `EXCEL_DICE_NO_DIGITALIZADO` (para avisarle al equipo).

## Carga incremental

- Importador y escaneo se pueden correr las veces que haga falta.
- El importador crea lo nuevo y actualiza lo que cambió. **Nunca borra**: si un registro desaparece del Excel, se reporta.
- Los campos editados a mano en el backoffice se anotan en `campos_bloqueados` (JSON) y el importador no los pisa.
  También se pueden bloquear `tipos` y `descriptores` como conjunto.
- El escaneo marca `presente = 0` los archivos que ya no están, sin borrarlos de la base.
- **Circuito con la planilla (15/11/2026, decisión del PM):** si un campo bloqueado trae ahora en el Excel **el mismo valor**
  que la base (sin contar espacios ni mayúsculas; las tildes sí cuentan: «Aida» ≠ «Aída»), el equipo ya lo corrigió en su
  planilla: se saca de `campos_bloqueados` (vía Historial, dentro de la importación, así que se deshace con ella), vuelve a
  tomarse del Excel y se informa `CORREGIDO_EN_PLANILLA` (informativa, una por caja con la lista de campos). Vale para
  registros y para nombre y resumen de caja; el Año arrastra sus campos derivados. Si el Excel trae otro valor, el bloqueo
  sigue como siempre. Tipos y descriptores bloqueados no entran en esta regla.
- **Conservar nuestra versión (15/11/2026, decisión del PM):** si el Excel coincide con lo corregido a mano **solo
  ignorando mayúsculas y espacios** («aída  BORTNIK» vs «Aída Bortnik»), queda el valor de la base y el campo **sigue
  bloqueado**, para que ninguna importación posterior lo pise. Se informa en `CORREGIDO_EN_PLANILLA` como «difiere solo en
  mayúsculas o espacios: se conserva la versión de la base». Solo una coincidencia exacta desbloquea el campo.

## ABM desde el backoffice (14/11/2026)

- La base se modifica por dos vías: la importación del Excel y el **ABM directo** del backoffice (alta, modificación y baja
  de cajas, registros, archivos y vocabularios). Detalle en [`backoffice.md`](backoffice.md).
- **Bajas lógicas** (`eliminado_en`): lo dado de baja no se muestra y la importación y el escaneo no lo vuelven a crear,
  aunque siga en el Excel o en la carpeta. Se reporta para que el operador confirme la baja o restaure.
- Registros **creados en el backoffice** (`origen = 'backoffice'`): la importación no los modifica. Si el Excel trae uno
  con el mismo número, se reporta para que el operador decida cuál vale.
- Todo cambio queda en `auditoria` con el valor anterior y el nuevo de cada campo, tanto los del backoffice como los de
  cada importación. Cualquier cambio, o una importación completa, **se puede deshacer** (`src/Historial.php`).
  Si el mismo campo se modificó de nuevo después, la reversión no se aplica y se informa el conflicto.

## Corrección masiva por planilla (15/11/2026, decisión del PM)

- Con los errores de la planilla del equipo (fechas al revés, grafías de «Aída Bortnik», nombres de cajas) se hacen
  **las dos cosas**: se le pasa al equipo la lista (`docs/datos/correcciones-planilla.csv`) para que corrija su planilla,
  y se corrige ya en la base desde el backoffice con esa misma lista (Importaciones o Cajas → **Corrección masiva…**).
- El archivo (CSV o .xlsx) trae `registro` (C#R#, o «Caja N (todas las filas)» para el nombre de caja), `columna` con el
  nombre de la columna del Excel y `valor_nuevo` (o `correccion_sugerida`). Opcionales: `valor_actual` (si no coincide con la
  base hay **conflicto** y esa fila no se aplica) y `accion` («Revisar» se saltea). Tipos y descriptores no se corrigen así:
  se curan en Vocabularios.
- Siempre hay **vista previa**; al aplicar se recalcula y, si la base cambió en el medio, no se aplica nada.
- Se aplica con `Historial::actualizar` en un proceso de tipo `correccion`: queda auditado, **bloquea** los campos (la próxima
  importación no los pisa mientras la planilla siga con el error) y **se deshace completo** desde Importaciones.
  Cuando el equipo corrige su planilla, la regla del circuito (arriba, «Carga incremental») los desbloquea sola.
- Sirve para el **cambio masivo de acceso** (columna «Acceso» o «PRIVADO»: Público / Restringido / A revisar, o VERDADERO,
  FALSO, VERLO).
- **PRIVADO a público pide confirmación aparte (15/11/2026, decisión del PM):** las filas que pasan a público un registro que la
  planilla marca PRIVADO (`excel_privado = VERDADERO`) se listan destacadas en la vista previa y «Aplicar» queda deshabilitado
  hasta tildar «Confirmo que estos N registros que la planilla marca PRIVADO pasan a público». Se valida también en el servidor:
  sin la casilla, esas filas no se aplican (el resto sí) y se informa en el aviso y en el resumen del proceso. Igual que la ficha.
- Corregir el Año recalcula `anio`, `anio_hasta` y `anio_sin_confirmar` con `Texto::anio()`.
- Prueba con la lista real (302 filas): 258 se aplican (240 Autor, 14 fechas, 4 nombres de caja), 44 se saltean («Revisar»:
  25 Año, 18 Autor, 1 Descriptor), 0 conflictos. Deshecha completa, todo vuelve; aplicada de nuevo, la importación del Excel
  actual (con los errores) no pisa ninguna.
- **Registros cargados a mano:** `php bin/recalcular-anios.php [--simular]` les aplica `Texto::anio()` (los del Excel ya se
  recalculan en cada importación). Queda como proceso `correccion`, reversible.

## Alertas para el operador (14/11/2026)

- Cada incidencia tiene título, leyenda y acción sugerida en `src/Alertas.php`, y se marca si **requiere validación**.
- Las alertas no se duplican entre corridas y se cierran solas cuando el problema deja de ocurrir.
- El operador valida las pendientes con una nota; queda registrado quién y cuándo.

## Vocabularios

- Tipo de documento y Descriptor son **vocabularios curados**: el data entry solo elige de la lista (sep 2026).
- Valores nuevos que vengan del Excel entran como `propuesto` y se reportan para curaduría. Se comparan sin tildes ni mayúsculas.
- Un término se puede fusionar con otro (`fusionado_en`); el importador usa el destino de la fusión.
- Textos largos (> 80 caracteres) en una columna de vocabulario se descartan y se reportan: son resúmenes pegados en la columna equivocada.

### Curaduría: mover, fusionar, renombrar y descartar (15/11/2026, decisión del PM)

- **Tipo de documento** = qué es la pieza (carta, guion, recorte). **Descriptor** = de qué área o tema trata (cine, televisión).
  Un término cargado en el vocabulario equivocado **se mueve** («Televisión» de tipo a descriptor; «Carta», «Fotografía»,
  «Comunicado de prensa», «Referencia de época» de descriptor a tipo). Si en el destino ya hay un término equivalente,
  se fusiona en él; si no, se crea aprobado.
- **Regla en el importador** (`src/Vocabulario.php`): cada valor del Excel se resuelve al **término vigente**, en este orden:
  1. el término con esa clave en esa columna, siguiendo `fusionado_en` (fusión o movido, `regla`) hasta el vigente, que puede ser
     del otro vocabulario: «Televisión» en la columna Tipo se carga como descriptor, sin crear un término nuevo;
  2. si no existe, una **grafía anterior** de un término renombrado (`vocabulario_alias`): renombrar «Bordereaux» → «Bordereau»
     cambia la clave y deja «Bordereaux» como alias, así la planilla puede traer cualquiera de las dos;
  3. si el vigente está **descartado**, no se asigna.
  Los términos que llegan por una regla van después de los propios de la columna. No se bloquea nada: el Excel sigue actualizando
  los tipos y descriptores del registro.
- **Estado «descartado»** para términos basura («g», «s», «.», frases pegadas): deja de mostrarse y de asignarse, se quita de
  sus registros sin bloquear los demás tipos y descriptores, y la importación lo ignora. Motivo obligatorio, confirmación con doble
  validación. Se restaura deshaciendo el descarte.
- **Fusionar o mover en un término «propuesto» lo aprueba** (15/11/2026, decisión del PM): elegirlo como destino ya es curarlo.
  El cambio de estado queda en el historial dentro de la misma operación y se deshace con ella.
- **Registro e informe:** cada movimiento, fusión, renombre y descarte es un `proceso` de tipo `curaduria` (quién, cuándo, motivo,
  registros afectados) con todos sus cambios en `auditoria` (términos, alias y la lista de cada registro). Se deshace completo
  desde Vocabularios → **Cambios de curaduría**, que también se copia como texto o se baja en CSV para el equipo de digitalización
  y el cliente. Las fusiones y renombres, que antes no quedaban en el historial, ahora sí.
- Cada vez que una importación aplica una regla se informa, **agrupada por término y caja**, como incidencia informativa:
  `VOCAB_MOVIDO`, `VOCAB_FUSIONADO`, `VOCAB_RENOMBRADO`, `VOCAB_DESCARTADO` (`src/Alertas.php`).
- Deshacer el alta de un término (por ejemplo, el destino creado al mover) lo da de baja (`eliminado_en`). Si la planilla lo vuelve
  a traer, la importación lo revive como propuesto.

## Años (columna K) — 15/11/2026, decisión del PM

- **Un registro solo responde a los años que figuran en su columna Año** (15/11/2026, decisión del PM, ajusta la anterior):
  - un **rango** responde a todos sus años: «1981/82» aparece filtrando 1981 y 1982; «Entre 1983 y 1986», de 1983 a 1986;
  - una **lista salteada** responde **solo** a los años listados: «1998/2006/2007/2008» aparece con 1998, 2006, 2007 y 2008,
    **no** filtrando 2000–2005, y cuenta en los años 90 y en los 2000 (no en una década del medio).
- Los años de cada registro se guardan en **`registro_anio`** (una fila por año, con `sin_confirmar` por año). El filtro por año,
  rango y década y la faceta de décadas usan esa tabla (`src/Buscador.php`). En `registro` quedan como **resumen** `anio` (el menor),
  `anio_hasta` (el mayor) y `anio_sin_confirmar` (alguno dudoso), para ordenar y mostrar; son lo que audita el historial.
  El texto original queda en `anio_texto` y es lo que se muestra; el sitio muestra la lista con `Texto::aniosEtiqueta`
  («1998, 2006–2008»).
- `registro_anio` se deriva del año del registro (`src/Anios.php`) y nunca lo contradice: la ponen al día `Historial` (al editar el
  Año en la ficha, en la corrección masiva y **al deshacer**) y el `Importador`. No se audita aparte. Es un derivado: rehacer sus
  filas no es «borrar» en el sentido de la carga incremental.
- **Por qué una tabla** y no una lista en una columna: el filtro por año es un `anio = ?` sobre un índice (`anio, registro_id`), la
  faceta de décadas sale de contar filas, y funciona igual en MySQL 8.4 y MariaDB 10.11 (sin JSON_TABLE ni FIND_IN_SET).
- **Con «?» el año es dudoso**: «1972?» filtra como 1972 pero queda **no confirmado**. La duda va por parte: en
  «1980? 1996/97/98/2006» solo 1980 queda sin confirmar. En el backoffice se ve en rojo con el chip
  «Año no confirmado» (ficha y lista de la caja); en el sitio se muestra «1972 (sin confirmar)» (`Texto::anioEtiqueta`).
- Lo interpreta `Texto::anio()`, igual en la importación y al editar el año a mano:

| Formato | Ejemplos reales | Resultado |
|---|---|---|
| Año | `1974`, celda numérica | 1974 |
| Año con duda | `1972?`, `¿1984?`, `1989???`, `1977(¿?)` | 1972, sin confirmar |
| Rango con palabras o guion | `1960 a 2009`, `2001 a 2006`, `Entre 1983 y 1986`, `Desde 1971 hasta 1991`, `1975-1979?` | todos los años del rango (1960 a 2009; con «?», sin confirmar) |
| Años con barra, punto, coma o «y», seguidos | `1981/82`, `1981/1982`, `2004/ 2005`, `2000/1999`, `1981/82/83`, `(1981.82,83)`, `1969. 1970. 1971`, `1988 y 1989` | los años escritos; como son consecutivos, equivale a un rango (1981, 1982 y 1983) |
| Lista salteada | `1998/2006/2007/2008`, `1985/87/90`, `1981/1990/1993/1994/ 2004` | **solo** los años escritos (1998, 2006, 2007, 2008) |
| Uno u otro | `1977 o 1978` | los dos, sin confirmar |
| Varias partes con su duda | `1980? 1996/97/98/2006` | 1980 sin confirmar; 1996, 1997, 1998 y 2006 confirmados |
| Fecha completa | `03/02/2006`, `02/02/1982 (sobre)`, `20 al 25 de junio de 1988` | el año de la fecha |
| Década | `años 70`, `década del 80`, `1970s` | 1970–1979 |
| Sin fecha | `sin fecha`, `s/f`, `Sin dato`, `No tiene fecha`, `sin precisar`, `?` | sin año, no se informa |
| Otro texto | `Posterior a 1990 porque…`, `Año XXII de la publicación`, `agosto/95 y agosto/97` | sin año; se informa (`ANIO_NO_INTERPRETADO`, informativa) |

- Años fuera de 1900–2100: no se toman. Dos dígitos después de un año completo siguen su siglo («1999/00» = 1999–2000).
- Los años se recalculan en cada importación. Con el Excel del 14/11/2026, de los **102 registros sin año filtrable pasan a
  filtrables 77** (51 rangos, 11 listas, 8 con duda, 7 fechas); quedan 25 sin año (20 «sin fecha» y 5 textos).
  En total: 62 registros con rango y 13 sin confirmar.
- Si el año se editó a mano (`anio_texto` bloqueado), los campos derivados salen del texto de la base, no del Excel.
- **Barra entre dos años no seguidos** («1982 /1986», «1981/1983», «1973/1986»): se toma como **rango** (el registro aparece en
  todos los años del medio), pero **marcado sin confirmar** (en rojo en el backoffice, «(sin confirmar)» en el sitio) y avisado en
  Verificaciones como `ANIO_RANGO_SUPUESTO`, porque podría ser una lista de dos años (decisión del PM, 15/11/2026). Con el Excel
  del 14/11/2026 son 10 registros (C7R2, C15R17, C21R5–R8, C46R11, C46R19, C54R1, C54R2). Tres o más años salteados siguen
  siendo lista.
- **Cambio respecto de la regla anterior** (Excel del 14/11/2026, 1098 registros): **13 registros** cambian de años. 2 dejan de
  aparecer en años intermedios (las listas de 3 o más años salteados —C66R10, C66R13—), los 10 de arriba quedan como rango sin confirmar y 1 pasa a tener año
  (C66R18, «1980? 1996/97/98/2006»). Quedan 4 sin año por texto ilegible.
- **Migración 004** (`db/migraciones/004_registro_anio.sql`): crea `registro_anio` y la completa con los rangos que había; el
  `Migrador` corre después `Anios::reconstruir()`, que pasa las listas salteadas a sus años reales. Los textos que antes no se leían
  necesitan una importación o `php bin/recalcular-anios.php --todos` (cambian el resumen, que va por el historial).

## Cajas y períodos

- Una caja puede pertenecer a más de un período (`caja_periodo`, N:N). Períodos, cajas y vocabularios se administran desde el backoffice.
- El número de caja es el número físico del depósito. El resumen de la caja sale de la primera «Edición de catálogo» de la pestaña.
- **Unificar nombres parecidos** (15/11/2026): el numeral final («II», «91/92/93») y la aclaración entre paréntesis
  («(España)», «(Argentina)», también escrita sin paréntesis) **distinguen cajas**: no se proponen como errores de grafía.
  Se agrupan como «misma obra, distinta parte» o «versiones distintas» y solo se ofrece unificar el título conservando
  la parte o la aclaración de cada caja. Las cajas que un resumen presenta juntas («Cajas 8 y 12») se muestran con esa evidencia,
  sin sugerir un nombre salvo que uno contenga al otro («Azucena» / «Azucena Villaflor»).

## Sitio público (15/11/2026)

- **Publicación por caja** (decisión del PM): el sitio muestra solo las cajas con `publicada = 1` y, de ellas, los registros sin baja,
  `publicado = 1` y con acceso distinto de «a revisar». Se publica y despublica desde la página de la caja en el backoffice
  (admin y editor), por `Historial`. Si no hay ninguna publicada, el inicio muestra «Todavía no hay cajas publicadas» (no un error).
  Las cifras del sitio (inicio, «Sobre») cuentan solo lo publicado.
- **Etapas:** la tabla `periodo` está vacía; mientras tanto el inicio muestra las 5 etapas del sitio actual
  (`Sitio::ETAPAS_SITIO_ACTUAL`), todas «Sin cajas asignadas», y la faceta «Etapa» no aparece en Explorar. Cuando se carguen
  períodos y se asignen a cajas publicadas, mandan ellos (enlace y faceta por slug del nombre).
- **Facetas de tipo y descriptor** muestran los términos aprobados **y los propuestos** (los mismos que la ficha), sin descartados,
  fusionados ni basura sin letras. Hoy los 108 términos están «propuesto»: mostrar solo aprobados dejaría las facetas vacías.
  Términos con la misma grafía se juntan por slug en la URL (`tipo=carta`).
- **Consultas a pedido:** tabla `consulta` (migración 005). Los datos personales quedan solo ahí y en el backoffice; el historial
  guarda el alta sin ellos (origen `sitio`). Anti-spam sin servicios externos: campo trampa, tiempo mínimo de 3 s firmado,
  CSRF por cookie (SameSite=Strict) y como máximo 5 envíos por hora por IP (guardada como HMAC). No se envían correos todavía.
- **Front controller `public/sitio.php`**, no `index.php`, para convivir con el WordPress de Local en la Mac del PM
  (`docs/despliegue.md`).

## Diseño del sitio público (15/11/2026)

- **Dirección visual: B · Museografía con la máquina de escribir y el cursor de C** (decisión del PM, 15/11/2026; primero eligió C · Sala oscura y
  ese mismo día pasó a B conservando de C la máquina de escribir y el cursor). Reemplaza el sistema anterior (papel sepia, banda marrón, Playfair Display).
  Detalle en `docs/diseno/sistema.md`; tokens en `public/sitio/tokens.css`.
- Paleta de las paredes de la muestra: azul #0D5C9E, rojo-naranja #EF6A3A, crema #F5F0E4 y tinta #1D1815. Títulos en Oswald (la condensada del logo),
  lectura en Inter, Courier Prime para citas, códigos, rótulos, metadatos y buscador. Logo nuevo de cajas en cabecera (tinta) y pie (calado blanco).
- **Lectura larga fuera de la monoespaciada** (advertencia aceptada por el PM): descripciones, resúmenes y «Sobre» en Inter 17/18 px, interlínea 1,65,
  66 caracteres, tinta #2B231C (no la máxima).
- **Título que se tipea con cursor** solo en el inicio, la caja y la ficha: una vez, sin movimiento con `prefers-reduced-motion`, título completo para
  lectores de pantalla desde el principio, cursor que titila 4 veces y queda fijo.
- Fotos en blanco y negro solo como ambiente (hero); los documentos nunca llevan filtro ni grano.

## Diseño del archivo: lenguaje de sistema (16/11/2026)

- **El archivo (catálogo en línea) usa un lenguaje de sistema, distinto del inmersivo del sitio institucional** (decisión del PM, 16/11/2026).
  El sitio institucional sigue con la dirección B; los dos comparten **logo, paleta y el cursor** de la máquina de escribir.
  Detalle en `docs/diseno/sistema.md` «Archivo: sistema».
- **Menos variables:** dos familias (Inter para todo; Courier Prime solo para códigos `C2R5` y el texto del buscador), tres niveles de lectura
  (título de pantalla, texto, dato secundario), fondo blanco neutro. La condensada (Oswald) queda solo en el logo. Sin rótulos en caja alta espaciada.
- **Azul y rojo-naranja como acentos funcionales:** azul para acción, enlace, foco y selección; rojo-naranja para el cursor del buscador y el
  resaltado del término buscado. Sin bandas, sombras ni grano.
- **Búsqueda primero:** el inicio es el buscador (con el cursor) + cifras en una línea + accesos rápidos; Explorar muestra una fila por registro
  (código, título y tipos, caja, año, acceso) con conteo y orden arriba, busca mientras se escribe (180 ms después de la última tecla) y resalta el
  término; atajo `/` para ir al buscador desde cualquier página.
- **Ficha y caja como vista de registro:** metadatos en lista compacta, acciones agrupadas (ver, descargar, pedir consulta, copiar cita, copiar enlace),
  anterior/siguiente y volver a los resultados. **Sin tipeo animado de títulos**: el cursor queda solo en el buscador.
- **Las cajas como módulos-caja** (decisión del PM, 16/11/2026): cada caja se muestra como el frente de una caja de archivo en línea fina (tapa,
  troquel, rótulo con número a máquina, obra y años; registros y digitalización al pie), en Las cajas, en el inicio y junto al título de cada caja:
  el anclaje físico de lo que se relevó. Las cajas se ordenan también por año.
- **Backoffice: mismo eje que el archivo, contrastado por colorimetría** (decisión del PM, 16/11/2026): mismas familias (Inter; mono solo para
  códigos, números de caja/registro y campos técnicos), escala, pesos, radio y foco que el archivo. Se distingue por el color: barra y login en tinta
  oscura azulada con el logo de cajas en blanco, ítem activo y contadores en rojo-naranja, área gris fría con tarjetas y tablas blancas; azul para
  acciones y enlaces. Intención de los botones: azul = guardar/acción principal (antes naranja), verde = crear o recuperar, rojo = baja o deshacer.
  Detalle en `docs/diseno/sistema.md` “Backoffice: sistema”.

## Origen de los archivos: Drive del cliente, provisorio (16/11/2026, decisión del PM)

- **Los PDF se sirven provisoriamente desde el Drive compartido del cliente** (carpeta «Selección de Cajas»), hasta dimensionar
  un hosting dedicado. **Por qué:** la muestra (`datos/listado_drive_20261114.tsv`) son 146 PDF de 8 cajas = **16 GB**, promedio
  109 MB por registro, máximo 780 MB; proyectado a las 70 cajas, del orden de **120–140 GB** (informe de Almacenamiento). Antes de
  contratar ese espacio se publica con lo que ya está en Drive.
- **Origen intercambiable** (`config/config.php`, `'archivos' => ['origen' => 'disco' | 'drive']`, por defecto `disco`). La interfaz
  `Aida\Archivos\Origen` tiene dos implementaciones, `Disco` (la carpeta propia, como hasta ahora) y `Drive`. Escaneo, entrega,
  sitio y backoffice le preguntan al origen; nada más sabe de dónde sale el archivo. Volver a disco es cambiar la configuración y
  escanear (`docs/despliegue.md`): los id de Drive se conservan.
- **Ids de Drive por listado**, porque hoy no hay acceso a la API: TSV o CSV con ruta (`C1/C1R31.pdf`), id de Drive (o el enlace),
  bytes y, opcional, si está compartido «cualquiera con el enlace». Se carga con `php bin/drive-listado.php listado.tsv [--simular]`
  o en Almacenamiento (siempre se simula primero). Cargarlo **es** el escaneo con origen Drive: asocia por ruta igual que el disco,
  marca no presente lo que ya no figura y guarda `archivo_digital.drive_id` y `drive_compartido` (migración 006). Los cambios de id
  y de «compartido» en archivos que ya estaban quedan en el historial del escaneo (se deshacen con el proceso). Una línea sin id
  no borra el id guardado (nada se borra en un escaneo); «compartido» sí se toma del listado nuevo (vacío = sin dato). Queda una copia
  normalizada del último listado (`archivos.drive.listado`, por defecto `var/drive/listado.tsv`) que usa `bin/escanear.php`.
  Con credenciales de cuenta de servicio, `Drive::listar()` podría pedirle los archivos a la API; no está implementado.
- **Entrega igual de controlada:** `public/archivo.php` hace los mismos controles que con disco (caja publicada, registro publicado,
  acceso público; restringido o a revisar → 403 «a pedido», nunca redirige). Si pasa: **ver** → redirección al visor embebido de
  Drive (`/file/d/ID/preview`), que la ficha abre en su visor (iframe); **descargar** → redirección a la descarga de Drive
  (`/uc?export=download&id=ID`). El id no aparece en el HTML del sitio, solo en la redirección. Respuesta sin caché
  (`no-store`): si el registro pasa a restringido, el próximo pedido ya no redirige. El equipo del backoffice también es
  redirigido a Drive para lo restringido o no publicado (para abrirlo necesita permiso en el Drive del cliente).
- **Más de 100 MB: solo descarga** (`archivos.drive.visor_max_mb`), con aviso del peso en la ficha: el visor de Drive falla con
  PDF grandes. En la muestra son **46 de 146** archivos. Con disco no hay límite (el navegador pide el PDF de a partes).
- **Alertas** (escaneo con origen Drive, se cierran solas cuando dejan de ocurrir): `DRIVE_RESTRINGIDO_COMPARTIDO` (error, a
  validar: un restringido o a revisar figura compartido con cualquiera con el enlace), `DRIVE_PUBLICO_SIN_COMPARTIR` (un público
  publicado no está compartido: el visor va a pedir iniciar sesión), `DRIVE_SIN_ID` y `LISTADO_LINEA_INVALIDA`. Se recalculan en
  cada carga del listado o con «Revisar de nuevo con el último listado»; la ficha del backoffice además lo avisa en el momento.
  Un escaneo con origen disco no tiene datos de Drive: esas alertas se cierran («Ya no ocurre»).
- **Riesgos aceptados:**
  - el visor de Drive con PDF grandes (de ahí el límite de 100 MB) y su aspecto, que no controlamos;
  - la **cuota de descargas de Drive**: un archivo muy descargado puede quedar bloqueado unas horas («cuota excedida»), y para
    archivos grandes Drive muestra antes un aviso de que no puede analizarlo con el antivirus;
  - **los permisos los maneja el cliente** en Drive: un restringido compartido por error se abre con el enlace aunque el sitio no
    lo entregue (por eso la alerta), y un público sin compartir no se ve;
  - los id cambian si el cliente re-sube un archivo: hay que volver a cargar el listado;
  - el visor embebido carga recursos de Google en la ficha (cookies de terceros, privacidad de los visitantes).

## Medición: prueba y producción (16/11/2026, pedido del PM)

- Un solo contenedor GTM (`GTM-M83WNDH5`) y una sola propiedad GA4 (`G-5EGY68N1ZX`) para el sitio institucional y el archivo;
  el archivo se distingue por `tipo_contenido: 'archivo'` y `content_group: 'Archivo'`. Detalle en [`medicion.md`](medicion.md).
- **Prueba / producción por host**, sin tocar el contenedor: fuera de `lascajasdeaida.com.ar`, `www.lascajasdeaida.com.ar` y
  `lascajasdeaida.com` (con y sin www), el dataLayer recibe antes de GTM `entorno: 'prueba'`, `traffic_type: 'internal'` y
  `debug_mode: true`; en producción, `entorno: 'produccion'` y nada más. Quien navega el sitio con sesión del backoffice
  también es `traffic_type: 'internal'`.
- El archivo carga GTM solo con `'analitica' => ['gtm' => …]` en `config/config.php`. El backoffice y su vista previa **nunca**
  miden.
- **Nada que identifique personas:** códigos de registro y números de caja, nunca datos del formulario. Las búsquedas se limpian
  en el navegador (correos y números de 7 o más cifras); los enlaces de correo no mandan la dirección.
- `evento_uso` guarda además la página que pidió el archivo (ruta del sitio o dominio externo) y un hash del día (fecha, IP y
  navegador con el secreto del sitio) para contar personas distintas sin guardar la IP (migración 007).

## Pendientes

- Versiones livianas para web de los PDF pesados (hay archivos de 500–780 MB).
- Definir dónde se alojan los archivos definitivos (servidor propio vs. otro almacenamiento) y cómo se sincronizan desde Drive.
  Mientras tanto se sirven desde Drive (16/11/2026); el informe de Almacenamiento da el tamaño para dimensionarlo.
- Acceso a la API de Drive (cuenta de servicio) para no depender de un listado armado a mano.
- Backoffice y front conectados a esta base.

## Comillas en los textos visibles (16/11/2026)

Decisión del PM: en todo texto que ve el público o el equipo (sitio, backoffice, mensajes, citas) se usan comillas dobles “ ” y no las angulares « ». Las expresiones que leen datos siguen aceptando « » en la entrada (la planilla puede traerlas).
