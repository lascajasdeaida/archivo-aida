# Backoffice — ABM y alertas para el operador

Requisitos funcionales del backoffice. El modelo de datos y el importador ya los soportan (esquema v0.3);
falta construir las pantallas.

## 1. ABM directo

La base no se modifica solo con el Excel: desde el backoffice se puede **dar de alta, modificar y dar de baja**
cualquier caja, registro y componente (tipos, descriptores, archivos, períodos, vocabularios).

| Entidad | Alta | Modificación | Baja |
|---|---|---|---|
| Caja | número (sugerido, se puede forzar), nombre, resumen, períodos | todos los campos | lógica (`eliminado_en`) |
| Registro | dentro de una caja; número sugerido (el siguiente libre, no reutiliza liberados) | todos los campos, tipos, descriptores, acceso | lógica |
| Archivo digital | subir o asociar un archivo a un registro | reemplazar, cambiar orden/parte | lógica (deja de mostrarse; el archivo físico no se borra) |
| Tipo / Descriptor | nuevo término aprobado | renombrar, aprobar un «propuesto», fusionar dos términos | solo si no tiene registros; si tiene, fusionar |
| Período | nombre, años, orden | todos los campos | solo si no tiene cajas |

Reglas:

- **Las bajas son lógicas.** Nada se borra físicamente de la base desde el backoffice; se puede **restaurar**.
  Un borrado definitivo solo lo hace un admin, aparte, y con doble confirmación.
- Lo que se da de baja **no lo vuelve a crear** ni la importación ni el escaneo, aunque siga en el Excel o en la carpeta.
- Al **editar a mano** un campo de un registro o una caja que vino del Excel, el campo se agrega a `campos_bloqueados`
  y las próximas importaciones ya no lo pisan.
- Un registro **creado desde el backoffice** queda con `origen = 'backoffice'` y la importación no lo modifica.
- Con archivos ya digitalizados, la nomenclatura (`C14R3`) queda congelada: cambiar caja o número pide confirmación de editor o admin.
- Todo cambio queda en `auditoria` (quién, cuándo, qué campo, valor anterior y nuevo).

## 2. Leyendas en la ficha (siempre visibles)

| Situación | Leyenda | Acción disponible |
|---|---|---|
| Campo editado a mano | «Editado a mano el {fecha} por {usuario}. La planilla ya no actualiza este campo.» | **Volver a tomar el valor del Excel** (desbloquea) |
| Registro creado en el backoffice | «Cargado a mano. La planilla no lo modifica.» | **Pasar a origen Excel** |
| Registro o caja dado de baja | «Dado de baja el {fecha} por {usuario}. No se muestra en el sitio.» | **Restaurar** |
| Acceso restringido | «Restringido: la ficha se ve, el documento se consulta a pedido.» | Cambiar acceso |
| Acceso a revisar | «Acceso sin definir (la planilla dice “VERLO”). No se publica hasta decidir.» | Público / Restringido |
| Sin archivo | «Todavía sin digitalizar.» Si el Excel lo marca digitalizado: «La planilla lo marca digitalizado; el archivo todavía no subió.» | Subir o asociar archivo |
| Diferencia con el Excel | «En la planilla figura {valor}.» (nomenclatura, digitalización) | Ninguna |

## 3. Confirmaciones antes de cambios sensibles

Antes de ejecutar, el operador ve qué se va a afectar y confirma:

- **Baja de una caja:** «Vas a dar de baja la Caja {n} con {x} registros y {y} archivos. No se van a mostrar en el sitio. Se puede restaurar.»
- **Baja de un registro con archivos:** «El registro {código} tiene {y} archivos asociados. Se ocultan junto con el registro.»
- **Cambio de acceso a público** de un registro que la planilla marca PRIVADO: «La planilla lo marca como privado. ¿Publicarlo igual?»
- **Cambiar caja o número** de un registro digitalizado: «Cambia la nomenclatura {anterior} → {nueva}. Los archivos hay que renombrarlos.»
- **Desbloquear un campo:** «Se va a reemplazar «{valor actual}» por lo que diga la planilla en la próxima importación.»
- **Fusionar términos:** «{x} registros pasan de «{origen}» a «{destino}».»

## 4. Panel de alertas

Cada importación y cada escaneo generan incidencias (`incidencia`). El catálogo con título, leyenda y acción
sugerida de cada una está en `src/Alertas.php`.

- **Pendientes de validar** (`requiere_validacion = 1`, `resuelta = 0`): contador visible en la barra superior del backoffice
  y en la ficha de la caja y del registro afectados.
- El operador abre la alerta, ve la leyenda y la acción sugerida, decide y la marca **validada** con una nota
  (`resuelta_por`, `resuelta_en`, `resolucion`).
- **Informativas** (`requiere_validacion = 0`): se listan aparte, sin contador. Sirven para avisarle al equipo de digitalización.
- Una alerta igual a otra abierta no se duplica: se mantiene una sola.
- Si en una corrida posterior el problema ya no aparece, la alerta **se cierra sola** con la nota «Ya no ocurre».
- Después de cada importación o escaneo, el backoffice muestra un resumen: nuevos, actualizados, sin cambios,
  y alertas nuevas que hay que validar.
- Filtros: por caja, por tipo de alerta, por estado (pendiente / validada / cerrada sola) y por fecha.

## 5. Perfiles

| Acción | admin | editor | data_entry | qa |
|---|---|---|---|---|
| Alta y modificación de registros | ✅ | ✅ | ✅ | — |
| Bajas y restauraciones | ✅ | ✅ | — | — |
| Cambiar acceso (público/restringido) | ✅ | ✅ | — | — |
| Forzar número / cambiar nomenclatura | ✅ | ✅ (con confirmación) | — | — |
| Curar vocabularios (aprobar, fusionar) | ✅ | ✅ | — | — |
| Validar alertas | ✅ | ✅ | — | ✅ |
| Importar Excel / correr escaneo | ✅ | ✅ | — | — |
| Usuarios y borrado definitivo | ✅ | — | — | — |
