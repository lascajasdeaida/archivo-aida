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
- **Registro e informe:** cada movimiento, fusión, renombre y descarte es un `proceso` de tipo `curaduria` (quién, cuándo, motivo,
  registros afectados) con todos sus cambios en `auditoria` (términos, alias y la lista de cada registro). Se deshace completo
  desde Vocabularios → **Cambios de curaduría**, que también se copia como texto o se baja en CSV para el equipo de digitalización
  y el cliente. Las fusiones y renombres, que antes no quedaban en el historial, ahora sí.
- Cada vez que una importación aplica una regla se informa, **agrupada por término y caja**, como incidencia informativa:
  `VOCAB_MOVIDO`, `VOCAB_FUSIONADO`, `VOCAB_RENOMBRADO`, `VOCAB_DESCARTADO` (`src/Alertas.php`).
- Deshacer el alta de un término (por ejemplo, el destino creado al mover) lo da de baja (`eliminado_en`). Si la planilla lo vuelve
  a traer, la importación lo revive como propuesto.

## Años (columna K) — 15/11/2026, decisión del PM

- Se guarda `anio` (año, o primer año del rango), `anio_hasta` (último año; igual a `anio` si es uno solo) y `anio_sin_confirmar`.
  El texto original queda en `anio_texto` y es lo que se muestra.
- **Un registro responde a todos los años de su rango**: «1981/82» aparece filtrando 1981 y filtrando 1982, y cuenta en cada
  década que toca (`src/Buscador.php`).
- **Con «?» el año es dudoso**: «1972?» filtra como 1972 pero queda **no confirmado**. En el backoffice se ve en rojo con el chip
  «Año no confirmado» (ficha y lista de la caja); en el sitio se muestra «1972 (sin confirmar)» (`Texto::anioEtiqueta`).
- Lo interpreta `Texto::anio()`, igual en la importación y al editar el año a mano:

| Formato | Ejemplos reales | Resultado |
|---|---|---|
| Año | `1974`, celda numérica | 1974 |
| Año con duda | `1972?`, `¿1984?`, `1989???`, `1977(¿?)` | 1972, sin confirmar |
| Rango con barra o guion | `1981/82`, `1981/1982`, `2004/ 2005`, `1982 /1986`, `1975-1979?`, `2000/1999` | 1981–1982 (desordenado: se ordena; con «?», sin confirmar) |
| Rango con palabras | `1960 a 2009`, `Entre 1983 y 1986`, `Desde 1971 hasta 1991`, `1988 y 1989` | 1960–2009 |
| Uno u otro | `1977 o 1978` | 1977–1978, sin confirmar |
| Lista | `1981/82/83`, `(1981.82,83)`, `1969. 1970. 1971`, `1998/2006/2007/2008` | del menor al mayor (1998–2008) |
| Fecha completa | `03/02/2006`, `02/02/1982 (sobre)`, `20 al 25 de junio de 1988` | el año de la fecha |
| Década | `años 70`, `década del 80`, `1970s` | 1970–1979 |
| Sin fecha | `sin fecha`, `s/f`, `Sin dato`, `No tiene fecha`, `sin precisar`, `?` | sin año, no se informa |
| Otro texto | `Posterior a 1990 porque…`, `Año XXII de la publicación`, `agosto/95 y agosto/97`, `1980? 1996/97/98/2006` | sin año; se informa (`ANIO_NO_INTERPRETADO`, informativa) |

- Años fuera de 1900–2100: no se toman. Dos dígitos después de un año completo siguen su siglo («1999/00» = 1999–2000).
- Los años se recalculan en cada importación. Con el Excel del 14/11/2026, de los **102 registros sin año filtrable pasan a
  filtrables 77** (51 rangos, 11 listas, 8 con duda, 7 fechas); quedan 25 sin año (20 «sin fecha» y 5 textos).
  En total: 62 registros con rango y 13 sin confirmar.
- Si el año se editó a mano (`anio_texto` bloqueado), los campos derivados salen del texto de la base, no del Excel.

## Cajas y períodos

- Una caja puede pertenecer a más de un período (`caja_periodo`, N:N). Períodos, cajas y vocabularios se administran desde el backoffice.
- El número de caja es el número físico del depósito. El resumen de la caja sale de la primera «Edición de catálogo» de la pestaña.
- **Unificar nombres parecidos** (15/11/2026): el numeral final («II», «91/92/93») y la aclaración entre paréntesis
  («(España)», «(Argentina)», también escrita sin paréntesis) **distinguen cajas**: no se proponen como errores de grafía.
  Se agrupan como «misma obra, distinta parte» o «versiones distintas» y solo se ofrece unificar el título conservando
  la parte o la aclaración de cada caja. Las cajas que un resumen presenta juntas («Cajas 8 y 12») se muestran con esa evidencia,
  sin sugerir un nombre salvo que uno contenga al otro («Azucena» / «Azucena Villaflor»).

## Pendientes

- Versiones livianas para web de los PDF pesados (hay archivos de 500–780 MB).
- Definir dónde se alojan los archivos definitivos (servidor propio vs. otro almacenamiento) y cómo se sincronizan desde Drive.
- Backoffice y front conectados a esta base.
