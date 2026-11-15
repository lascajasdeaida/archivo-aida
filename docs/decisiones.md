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

## Cajas y períodos

- Una caja puede pertenecer a más de un período (`caja_periodo`, N:N). Períodos, cajas y vocabularios se administran desde el backoffice.
- El número de caja es el número físico del depósito. El resumen de la caja sale de la primera «Edición de catálogo» de la pestaña.

## Pendientes

- Versiones livianas para web de los PDF pesados (hay archivos de 500–780 MB).
- Definir dónde se alojan los archivos definitivos (servidor propio vs. otro almacenamiento) y cómo se sincronizan desde Drive.
- Backoffice y front conectados a esta base.
