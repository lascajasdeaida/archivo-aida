# Backoffice — ABM y alertas para el operador

Requisitos funcionales del backoffice. El modelo de datos y el importador ya los soportan (esquema v0.4).

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
- Todo cambio queda en `auditoria` (quién, cuándo, qué campo, valor anterior y nuevo) y **se puede deshacer** (ver §5).

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

## 4. Notificaciones: verificaciones pendientes

Sección propia del backoffice, con un contador siempre visible en la barra superior
(«🔔 12 verificaciones pendientes»). Reúne todo lo que necesita una decisión del operador.

Cada importación y cada escaneo generan incidencias (`incidencia`). El catálogo con título, leyenda y acción
sugerida de cada una está en `src/Alertas.php`.

**Bandeja de pendientes** (`requiere_validacion = 1`, `resuelta = 0`)
- Una tarjeta por alerta: título, leyenda en lenguaje llano, caja y registro afectados (con link a la ficha) y acción sugerida.
- Botones según el caso: **Validar** (con nota obligatoria), **Ir a la ficha**, **Restaurar**, **Confirmar baja**, **Elegir acceso**.
- Al validar queda registrado quién, cuándo y qué decidió (`resuelta_por`, `resuelta_en`, `resolucion`).
- Agrupadas por caja y por tipo, con filtros por estado (pendiente / validada / cerrada sola) y por fecha.

**Informativas** (`requiere_validacion = 0`): pestaña aparte, sin contador. Sirven para pasarle correcciones
al equipo de digitalización (por ejemplo, nomenclaturas mal tipeadas). Exportables a Excel.

**Comportamiento**
- Una alerta igual a otra abierta no se duplica.
- Si en una corrida posterior el problema ya no aparece, se cierra sola con la nota «Ya no ocurre».
- Después de cada importación o escaneo, el backoffice muestra un resumen: nuevos, actualizados, sin cambios,
  y cuántas verificaciones nuevas quedaron pendientes.
- La ficha de la caja y la del registro muestran sus propias alertas abiertas arriba de todo.

## 5. Historial y volver atrás

Todo cambio queda registrado **con el valor anterior y el nuevo de cada campo** (`auditoria`), venga del backoffice
o de una importación, y se puede deshacer. La lógica está en `src/Historial.php`; el backoffice hace todas sus altas,
modificaciones y bajas a través de esa clase.

- **En la ficha del registro o de la caja:** pestaña «Historial» con cada cambio (fecha, usuario u origen, campo,
  antes → después) y un botón **Deshacer** por cambio.
- **En la lista de importaciones y escaneos:** botón **Deshacer esta importación**, que revierte todos sus cambios
  del último al primero.
- Deshacer un **alta** deja el registro dado de baja (no se borra nada). Deshacer una **baja** lo restaura.
- **Conflictos:** si después del cambio que se quiere deshacer alguien volvió a modificar el mismo campo, no se aplica
  nada y se muestra: «El título se modificó de nuevo después: hoy vale X, el cambio lo había dejado en Y». El operador
  puede deshacer primero el cambio más nuevo o forzar (solo editor o admin, con confirmación).
- Deshacer también queda en el historial, así que **una reversión se puede volver a deshacer**.
- Mientras no exista el backoffice, se puede usar `php bin/revertir.php` (ver README).

## 6. Perfiles

| Acción | admin | editor | data_entry | qa |
|---|---|---|---|---|
| Alta y modificación de registros | ✅ | ✅ | ✅ | — |
| Bajas y restauraciones | ✅ | ✅ | — | — |
| Cambiar acceso (público/restringido) | ✅ | ✅ | — | — |
| Forzar número / cambiar nomenclatura | ✅ | ✅ (con confirmación) | — | — |
| Curar vocabularios (aprobar, fusionar) | ✅ | ✅ | — | — |
| Validar alertas | ✅ | ✅ | — | ✅ |
| Deshacer cambios propios | ✅ | ✅ | ✅ | — |
| Deshacer cambios de otros o importaciones completas | ✅ | ✅ | — | — |
| Forzar una reversión con conflictos | ✅ | ✅ | — | — |
| Importar Excel / correr escaneo | ✅ | ✅ | — | — |
| Usuarios y borrado definitivo | ✅ | — | — | — |

## 7. Ver y entregar archivos (`public/archivo.php`)

Un solo punto de entrega para el sitio y el backoffice: `/archivo.php?id={id de archivo_digital}` (`&descargar=1` para bajarlo).
Nunca recibe una ruta: la saca de la base y la resuelve con `realpath` dentro de `archivos_raiz`. La lógica está en
`src/Entrega.php` (`buscar()`, `decidir()`, `rutaSegura()`, `enviar()`), lista para que el sitio público la reuse.

| Situación | Público | Usuario del backoffice |
|---|---|---|
| Archivo quitado, no presente en el último escaneo, o registro/caja dados de baja | 404 «Documento no disponible» | 404 |
| Registro no publicado | 404 | Lo ve |
| Acceso `restringido` | 403 «Documento de consulta a pedido» (con código, título y caja) | Lo ve |
| Acceso `a_revisar` | 403 «Documento de consulta a pedido» (sin datos del registro) | Lo ve |
| Público y publicado | Lo ve | Lo ve |

- Formatos que se entregan: PDF, imágenes, audio y video (lista en `Entrega::MIME`). Otro formato → 404.
- Encabezados: `Content-Type`, `Content-Disposition: inline` (o `attachment` con `descargar=1`), `Accept-Ranges`,
  `X-Content-Type-Options: nosniff`, `ETag`/`Last-Modified` (responde 304), `Cache-Control: private` (`no-cache` para el backoffice),
  `X-Frame-Options: SAMEORIGIN`. Soporta `Range` de un tramo (206 / 416) e `If-Range`: el visor del navegador pide
  los PDF pesados de a partes.
- Usuario del backoffice = cookie de sesión `aida_bo` con un usuario con permiso `ver`. Al público no se le crea sesión.
  La sesión se cierra antes de transmitir, así un PDF grande no bloquea el resto del backoffice.
- **Métricas:** cada apertura suma una fila en `evento_uso` (`vista` o `descarga`) con registro, archivo y fecha. Sin IP,
  usuario ni datos del navegador. Se cuenta solo el primer pedido (sin `Range`), no cada tramo del visor, y **no se cuentan
  las consultas del equipo** desde el backoffice.
- **En la ficha del registro**, cada archivo presente tiene «Ver PDF ↗» (pestaña nueva, visor del navegador), «Descargar» y
  «Vista previa ↓» (panel a todo el ancho debajo de la ficha; el iframe se crea recién al pedirlo). Si el registro no se puede
  ver en el sitio, lo aclara.

## 8. Importar el Excel desde el backoffice

**Importaciones → Subir Excel e importar…** (permiso `procesos`: admin y editor).

1. Se sube el .xlsx (opcionalmente solo algunas cajas: `1, 2, 10-14`). Se valida extensión, tamaño (`importar_max_mb`,
   40 MB por defecto, y los topes de PHP), que sea un zip con `xl/workbook.xml` y que no se infle a más de 400 MB al descomprimir.
2. Se **simula siempre** primero: el importador corre entero y se deshace. Se ven contadores y avisos agrupados
   (con cuáles van a quedar para validar). Nada se guarda.
3. **Importar de verdad** (naranja, guardar, con doble validación `data-doble`) importa **el mismo archivo** que se simuló.
   La simulación vale 2 horas y es de la sesión de quien la hizo. «Descartar» la borra.
4. Al terminar se muestra el resultado y queda en la lista de importaciones, desde donde se puede **deshacer completa**.

- El Excel se guarda fuera de `public/` (`importaciones_dir`, por defecto `var/importaciones/`, no versionado) con nombre
  generado. Las simulaciones sin confirmar de más de 4 horas se borran al subir otro Excel. El importado queda como
  `importacion-{proceso}-{fecha}.xlsx` para saber qué planilla generó cada cambio. En la bitácora figura el nombre original.
- Un candado en la base (`GET_LOCK('aida_importacion')`) impide dos importaciones al mismo tiempo (también contra la consola).
- Reusa `Aida\Importador` (mismas reglas). `php bin/importar.php` sigue igual.
- Tiempo medido con el catálogo completo (70 pestañas, ~1.100 registros): **5–7 s** la simulación y otro tanto la importación,
  memoria máxima baja (lee con XMLReader). El servidor necesita `upload_max_filesize` y `post_max_size` mayores que el Excel.

## 9. Colores de los botones

Por intención (`public/admin/estilo.css`): **verde** crear o agregar (`btn-crear`), **naranja** guardar o aplicar (`btn-primario`),
**rojo** quitar o dar de baja (`btn-peligro`, `btn-peligro-borde`). Lo que cambia muchos datos de una vez lleva `data-doble`
(primer clic arma, segundo confirma) aunque se pueda deshacer.
