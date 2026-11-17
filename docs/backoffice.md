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
| Tipo / Descriptor | nuevo término aprobado | renombrar, aprobar un «propuesto», fusionar, mover al otro vocabulario (§10) | descartar (basura) o fusionar; se puede deshacer |
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
- **Fusionar términos:** «{x} registros pasan de «{origen}» al término elegido. {n} ya lo tenían.»
- **Mover un término al otro vocabulario:** «{x} registros pierden el tipo «{término}» y suman el descriptor elegido. {n} ya lo tenían.
  {m} registros quedan sin tipo de documento: {códigos}.» Ofrece el término equivalente del destino (recomendado), crear uno nuevo u otro.
- **Descartar un término:** «Se quita de {x} registros. Los demás tipos y descriptores no se tocan ni se bloquean.» Motivo obligatorio.
- **Deshacer un cambio de curaduría:** «Vuelve todo a como estaba antes del {fecha}…»

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
al equipo de digitalización (por ejemplo, nomenclaturas mal tipeadas). Exportables a Excel. Incluyen las reglas de curaduría
que aplicó la importación (`VOCAB_MOVIDO`, `VOCAB_FUSIONADO`, `VOCAB_RENOMBRADO`, `VOCAB_DESCARTADO`, una por término y caja,
con los registros), los años que no se pudieron leer (`ANIO_NO_INTERPRETADO`) y los campos corregidos a mano que el equipo
ya corrigió en su planilla (`CORREGIDO_EN_PLANILLA`, una por caja: se desbloquearon y vuelven a tomarse del Excel).

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
- Los cambios de un registro que vienen de un **cambio de curaduría** (§10) muestran «curaduría #N» y se deshacen completos
  desde Cambios de curaduría, no uno por uno. Si se intenta deshacer uno suelto y el término ya no corresponde
  («Televisión» ya es descriptor), el conflicto lo explica.
- Las listas de tipos y descriptores se comparan por término vigente: un renombre o una fusión posteriores no cuentan como conflicto.

## 6. Perfiles

| Acción | admin | editor | data_entry | qa |
|---|---|---|---|---|
| Alta y modificación de registros | ✅ | ✅ | ✅ | — |
| Bajas y restauraciones | ✅ | ✅ | — | — |
| Cambiar acceso (público/restringido) | ✅ | ✅ | — | — |
| Forzar número / cambiar nomenclatura | ✅ | ✅ (con confirmación) | — | — |
| Curar vocabularios (aprobar, renombrar, fusionar, mover, descartar, deshacer) | ✅ | ✅ | — | — |
| Validar alertas | ✅ | ✅ | — | ✅ |
| Deshacer cambios propios | ✅ | ✅ | ✅ | — |
| Deshacer cambios de otros o importaciones completas | ✅ | ✅ | — | — |
| Forzar una reversión con conflictos | ✅ | ✅ | — | — |
| Importar Excel / correr escaneo | ✅ | ✅ | — | — |
| Corrección masiva por planilla (incluye cambio masivo de acceso) | ✅ | ✅ | — | — |
| Publicar / despublicar una caja en el sitio | ✅ | ✅ | — | — |
| Ver y atender consultas del sitio (datos personales) | ✅ | ✅ | — | — |
| Usuarios y borrado definitivo | ✅ | — | — | — |

## 7. Ver y entregar archivos (`public/archivo.php`)

Un solo punto de entrega para el sitio y el backoffice: `/archivo.php?id={id de archivo_digital}` (`&descargar=1` para bajarlo).
Nunca recibe una ruta: solo el id. Las reglas de acceso están en `src/Entrega.php` (`buscar()`, `decidir()`); cómo se entrega lo
resuelve el origen de archivos (`src/Archivos/`, `docs/decisiones.md` 16/11/2026): **disco** resuelve la ruta con `realpath`
dentro de la carpeta y transmite el archivo (`Disco::rutaSegura()`); **drive** redirige al visor o a la descarga de Drive después
de los mismos controles, y los de más de 100 MB solo se descargan (la ficha lo avisa con el peso).

| Situación | Público | Usuario del backoffice |
|---|---|---|
| Archivo quitado, no presente en el último escaneo, o registro/caja dados de baja | 404 «Documento no disponible» | 404 |
| Registro no publicado | 404 | Lo ve |
| Acceso `restringido` | 403 «Documento de consulta a pedido» (con código, título y caja) | Lo ve |
| Acceso `a_revisar` | 403 «Documento de consulta a pedido» (sin datos del registro) | Lo ve |
| Público y publicado | Lo ve | Lo ve |

- Formatos que se entregan: PDF, imágenes, audio y video (lista en `Entrega::MIME`). Otro formato → 404.
- Encabezados (origen disco): `Content-Type`, `Content-Disposition: inline` (o `attachment` con `descargar=1`), `Accept-Ranges`,
  `X-Content-Type-Options: nosniff`, `ETag`/`Last-Modified` (responde 304), `Cache-Control: private` (`no-cache` para el backoffice),
  `X-Frame-Options: SAMEORIGIN`. Soporta `Range` de un tramo (206 / 416) e `If-Range`: el visor del navegador pide
  los PDF pesados de a partes.
- Usuario del backoffice = cookie de sesión `aida_bo` con un usuario con permiso `ver`. Al público no se le crea sesión.
  La sesión se cierra antes de transmitir, así un PDF grande no bloquea el resto del backoffice.
- **Métricas:** cada apertura suma una fila en `evento_uso` (`vista` o `descarga`) con registro, archivo y fecha, la página que
  lo pidió (`referer`: ruta del sitio o solo el dominio de afuera) y un `sesion_hash` del día (HMAC de fecha, IP y navegador,
  16 caracteres: cuenta personas distintas por día sin guardar la IP; migración 007, `docs/medicion.md`). Sin IP ni usuario. Se cuenta solo el primer pedido (sin `Range`), no cada tramo del visor, y **no se cuentan
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

## 10. Curaduría de vocabularios (15/11/2026)

Reglas en `docs/decisiones.md` («Curaduría»). Lógica en `src/Curaduria.php` y `src/Vocabulario.php`.

**Vocabularios** (`?r=vocabularios`): por defecto muestra los términos vigentes (propuestos y aprobados); el filtro de estado
muestra también fusionados, movidos y descartados. Cada término vigente tiene:

| Acción | Botón | Qué hace |
|---|---|---|
| Aprobar | verde | `propuesto` → `aprobado` (queda en el historial del término) |
| Renombrar | naranja, en línea, motivo opcional | Cambia nombre y clave. La grafía anterior queda como alias («También reconoce…») |
| Fusionar… | pantalla de confirmación | Pasa sus registros a otro término del mismo vocabulario. Si el destino estaba «propuesto», queda aprobado |
| Mover a {otro vocabulario}… | pantalla de confirmación | Pasa sus registros al otro vocabulario: a un término equivalente (recomendado), a uno nuevo o a otro |
| Descartar… | rojo, pantalla de confirmación | Lo quita de sus registros y lo esconde. Motivo obligatorio |
| Restaurar | verde, `data-doble` (descartados) | Deshace el descarte: vuelve a la lista y a sus registros |

Las tres pantallas de confirmación (`?r=vocab_curar&op=mover|fusionar|descartar&id=`) muestran cuántos registros cambian,
cuántos ya tenían el destino (se actualiza al elegir otro) y cuáles quedan sin tipo o sin descriptor. Se confirman con la casilla
«Entiendo lo que va a pasar» y el botón rojo con `data-doble` (doble validación).

**Cambios de curaduría** (`?r=vocab_informe`): todos los movimientos, fusiones, renombres y descartes con fecha, quién,
motivo, registros afectados (desplegable con los códigos) y estado (vigente / deshecho, cuándo y por quién).
- **Copiar como texto:** resumen de los vigentes, listo para pegar en un mail al equipo de digitalización o al cliente.
- **Descargar CSV** (`&formato=csv`): todos, separado por «;», UTF-8 con BOM (lo abre Excel en castellano).
- **Deshacer…** (rojo, con confirmación): revierte la operación completa. Si después algún registro cambió (a mano o por una
  importación), no se aplica nada y se muestran los conflictos; un editor puede forzar.

Las operaciones no aparecen en la lista de Importaciones (son procesos de tipo `curaduria`).

## 11. Años en la ficha y en la lista de la caja (15/11/2026)

- Debajo del campo Año, la ficha dice a qué años responde en el filtro (`registro_anio`): «Para el filtro por año: 1981–1982»,
  «1998, 2006–2008», «1980 (sin confirmar), 1996–1998, 2006», o «Sin año para el filtro…» si no se pudo leer.
- **Año no confirmado** («1972?»): chip rojo en la cabecera de la ficha y en la lista de la caja (el año, en rojo), con la
  aclaración de que en el sitio se verá «1972 (sin confirmar)».
- Un texto que no es un año lleva el chip «sin año» en la lista de la caja.
- Al editar el Año a mano se aplican las mismas reglas que en la importación.

## 12. Unificar nombres de cajas (15/11/2026)

`?r=cajas_unificar` agrupa las cajas con nombres parecidos en cuatro clases, en este orden:

| Clase | Ejemplo | Qué ofrece |
|---|---|---|
| Misma obra, distinta grafía | 3 «Noticia de un secuestro» / 5 «Noticias de un secuetro» | Unificar con un nombre (sugerido: el que tiene más tildes y menos mayúsculas) |
| El resumen las presenta juntas | 8 «Why Me?» / 12 «La Vìctima» («Cajas 8 y 12») | Unificar, sin sugerencia salvo que un nombre contenga al otro (9 / 13 → «Azucena Villaflor») |
| Misma obra, distinta parte: no unificar | 23 «Cenizas del Paraíso» / 24 «… II»; 49 / 59 «Fade Out 91/92/93», «95/96/97» | Solo unificar el título; cada caja conserva su numeral |
| Versiones distintas: no unificar | 30, 31, 34 «(España)» / 35 «(Argentina)» | Solo unificar el título; cada caja conserva su aclaración (también la escrita sin paréntesis: «… España») |

Ninguna caja viene tildada. Cada cambio queda en el historial de la caja (se puede deshacer) y bloquea el nombre para el Excel.

## 13. Corrección masiva por planilla (15/11/2026)

**Importaciones → Corrección masiva…** (también desde Cajas). Permiso `correccion`: admin y editor. Reglas en
`docs/decisiones.md` («Corrección masiva por planilla»); lógica en `src/Correccion.php`.

1. Se sube un **CSV** (coma, punto y coma o tabulación; UTF-8 o Windows-1252) o un **.xlsx** (primera hoja), hasta 5 MB.
   Encabezados en la primera fila:

| Encabezado | Obligatorio | Qué va |
|---|---|---|
| `registro` | sí | `C14R3` (se aceptan ceros: `C14R03`), o `Caja 5 (todas las filas)` para un dato de la caja |
| `columna` | sí | Nombre de la columna del Excel: Autor, Título, Lugar, Fecha como dice el documento, Año, Páginas, Resumen, ISBN, Observaciones/Editorial, Edición de catálogo, Observaciones de conservación, Observaciones de digitalización, Selección para digitalización, Acceso / PRIVADO / Nivel de acceso, Nombre de caja (columna B), Resumen de la caja |
| `valor_nuevo` | sí | El valor corregido. También vale `correccion_sugerida` (la lista `docs/datos/correcciones-planilla.csv` se sube tal cual). Vacío o «—»: se saltea |
| `valor_actual` | no | Lo que se espera que haya hoy. Si no coincide con la base (sin contar espacios ni mayúsculas) → **conflicto**, no se aplica. Vacío: no se controla |
| `accion` | no | «Revisar» → se saltea y se lista aparte |

   Columnas rechazadas, con el motivo en la vista previa: Tipo y Descriptor (se curan en Vocabularios), Caja y N.º de registro
   (son la clave), Digitalización (la define el archivo) y Nombre de archivo digital (control cruzado).
2. **Vista previa** (no guarda nada): contadores y una tabla por estado — se aplican, conflictos, con error, se saltean, ya estaban
   así — con fila del archivo, registro (link a la ficha), columna, valor en la base, valor nuevo y nota (en el Año, cómo queda
   para el filtro; en el acceso, si la planilla lo marca PRIVADO). Dos filas que cambian el mismo campo con valores distintos
   quedan las dos con error. La vista previa se recalcula cada vez que se muestra y vale 2 horas.
3. **Aplicar N correcciones** (naranja, `data-doble`): vuelve a calcular el plan; si cambió algo desde la vista previa, no aplica
   nada. Si no, aplica todo junto en un proceso `correccion` con `Historial::actualizar` (un cambio de historial por registro o
   caja, campos bloqueados para el Excel; los registros cargados a mano no se bloquean porque la importación no los toca).
4. Queda en **Importaciones** como «Corrección masiva», con **Deshacer…** completo (si algo se editó después, muestra los
   conflictos). En el historial de cada ficha figura «corrección masiva #N». La planilla aplicada se guarda como
   `var/importaciones/correccion-{proceso}-{fecha}.csv|xlsx`.

**Cambio masivo de acceso:** el mismo circuito con la columna `Acceso` (o `PRIVADO`): Público, Restringido o A revisar
(también VERDADERO = restringido, FALSO = público, VERLO = a revisar). Las filas que pasan a público un registro que la planilla
marca PRIVADO se listan aparte, en rojo, y «Aplicar» se habilita recién al tildar «Confirmo que estos N registros que la planilla
marca PRIVADO pasan a público»; sin esa casilla el servidor no las aplica y lo avisa. Ejemplo:

```
registro;columna;valor_actual;valor_nuevo
C2R2;Acceso;A revisar;Público
C5R1;Acceso;Público;Restringido
C6R5;PRIVADO;;VERLO
```

**Cerrar el circuito:** cuando el equipo corrige lo mismo en su planilla, la importación ve que el Excel trae el mismo valor que
la base, desbloquea el campo y lo informa (`CORREGIDO_EN_PLANILLA`). Así base y Excel no quedan distintos. Si solo difieren
mayúsculas o espacios, queda la versión de la base y el campo **sigue bloqueado** (el aviso lo aclara campo por campo).

**Años de registros cargados a mano:** `php bin/recalcular-anios.php --simular` muestra qué cambia (con `--todos`, también los del
Excel: sirve después de la migración 004 sin volver a importar); sin `--simular` lo aplica
como proceso `correccion` (se deshace desde Importaciones).

## 14. Vista previa: «Ver como en el sitio» (15/11/2026)

En la cabecera de la ficha del registro y de la caja, **Ver como en el sitio ↗** (botón neutro, pestaña nueva) abre
`?r=vista_previa&registro=ID` o `?r=vista_previa&caja=ID` (permiso `ver`). Muestra la página tal como la arma el sitio público,
con las **mismas plantillas** que va a usar el sitio: `vistas/publico/ficha.php` y `caja.php` (layout `_sitio.php`, tarjeta
`_tarjeta.php`, íconos `_iconos.php`).

- **Banner fijo arriba** (`vistas/publico/_vista_previa.php`, `public/sitio/vista-previa.css`): «Vista previa — así se vería en el
  sitio» y «Volver al backoffice». Debajo, si **hoy no aparece en el sitio**, el porqué: registro o caja dados de baja, registro no
  publicado, acceso «a revisar», caja no publicada. La vista se muestra igual, como quedaría una vez resuelto. En la caja se
  avisa además cuántos registros no entran en la lista (a revisar, no publicados, dados de baja).
- **Qué cuenta la vista previa:** la lista de la caja, los vecinos y las cifras incluyen solo lo que el sitio mostraría si la caja
  estuviera publicada. Las cajas vecinas son todas las que no están dadas de baja (en el sitio, solo las publicadas).
- **Navegación:** de la ficha a su caja, de la caja a una ficha y entre vecinos se navega dentro de la vista previa. Los enlaces a
  páginas del sitio que todavía no existen (inicio, explorar, consulta, sobre el archivo) y el buscador de la caja no navegan:
  `vista-previa.js` avisa en el banner.
- **Visor:** «Ver documento» abre el visor dentro de la ficha con un iframe a `archivo.php?id=` que se crea recién al pedirlo
  (los PDF pesan hasta 780 MB). Sin JS, el botón abre el archivo directo. En la vista previa entrega con la sesión del backoffice,
  así que **no suma en `evento_uso`**; la página en sí no registra nada.
- `noindex,nofollow` (meta y `X-Robots-Tag`), `Cache-Control: private, no-store`.
- Qué muestra cada estado: digitalizado y público → Ver documento + Descargar (con peso); restringido o «a revisar» → «Consulta a
  pedido» + «Pedir consulta»; sin archivo → «Solo ficha» + «Consultar por este documento». Sin título → los tipos en cursiva.
  Año con `registro_anio` (`Texto::aniosEtiqueta`): dudoso con subrayado punteado, «(sin confirmar)» y el texto de la planilla
  debajo. Tipos y descriptores: aprobados y propuestos, nunca descartados, dados de baja ni sin letras («.»).
  No se muestran observaciones de conservación ni de digitalización ni la nomenclatura del Excel.

**Para el sitio público:** los datos salen de `src/Sitio.php` (`fichaPorCodigo`, `cajaPorNumero`; en modo público devuelve `null`
si no se muestra, con las mismas reglas que `Buscador` y `Entrega`) y las URL de `src/Web/Enlaces.php` (`new Enlaces('/')` da las
URL de `docs/ux/sitio-publico.md` §2: `/registro/C14R3`, `/caja/2-la-historia-oficial`…). El controlador público solo tiene que
pedir los datos, devolver 404 si vienen `null` y requerir la plantilla con `$d` y `$enl`.
CSS en `public/sitio/` (`tokens.css` + `estilo.css`), el mismo archivo que usan las maquetas de `docs/diseno/maquetas/`;
comportamiento en `public/sitio/sitio.js` (menú, «Leer más», visor, copiar cita).

## 15. Publicación por caja y consultas del sitio (15/11/2026)

**Publicar una caja** (decisión del PM: se publica caja por caja). En la página de la caja, panel «Publicación en el sitio»:
estado, cuántos registros se van a ver (y cuántos no entran: «a revisar» o no publicados), **Publicar la caja** (verde) o
**Despublicar la caja** (rojo con doble validación) y la vista previa. Pasa por `Historial` sin bloquear campos (la importación no
toca `publicada`): se deshace desde el historial de la caja. Al despublicar, las fichas responden 410 «ya no está disponible» con
enlace a la caja si sigue publicada. El tilde «Publicada en el sitio» del formulario de datos de la caja se sacó: había dos
controles para lo mismo y lo podía cambiar el perfil de carga de datos. En **Cajas**, pestañas Todas · Publicadas en el sitio ·
Sin publicar y columna «Sitio».

**Consultas** (menú, con chip de las nuevas; admin y editor). Lista de los pedidos del formulario «Pedir consulta» del sitio,
con pestañas por estado (nueva / respondida / descartada) y buscador por nombre, correo, institución o código. La ficha muestra los
datos de quien pide (nombre, correo con enlace `mailto:`, institución, uso previsto, modalidad, mensaje), el documento y el
**seguimiento**: estado y notas internas, por `Historial` (se deshacen; al salir de «nueva» se anota quién y cuándo la atendió).
El alta queda en el historial **sin datos personales** (código, tipo, uso, modalidad), con origen «sitio público».
Todavía no se manda ningún correo: falta definir con el cliente quién responde (`docs/consultas-cliente.md` §2). Punto de
extensión: `Aida\NotificadorConsultas` (ver `docs/sitio-publico.md`).


## 16. Almacenamiento: origen de los archivos y espacio (16/11/2026)

Menú **Almacenamiento** (ver: todos; cargar el listado: permiso `procesos`, admin y editor). También desde Importaciones →
**Listado de Drive…**.

- **Origen de los archivos:** dice si se sirven del disco propio o del Drive del cliente (se cambia en la configuración), cuándo se
  cargó el último listado de Drive y cuántos archivos tienen id. «Revisar de nuevo con el último listado» (naranja) vuelve a
  escanear para poner al día las alertas de Drive, por ejemplo después de cambiar el acceso de un registro.
- **Listado de Drive:** se sube el TSV/CSV (hasta 5 MB) y se **simula** (mismo resumen que la importación del Excel: contadores y
  avisos agrupados). **Cargar el listado** (naranja, `data-doble`) escanea con ese mismo archivo y lo deja como listado vigente;
  solo con origen `drive`. Queda en la lista de Importaciones como escaneo y se puede deshacer. El archivo subido se guarda
  fuera de `public/` (`importaciones_dir`) como `drive-{proceso}-{fecha}.tsv`.
- **Espacio:** GB por caja (registros, digitalizados, archivos, promedio por registro, el mayor y cuántos pasan de 100 MB), total
  actual y **proyección a todas las cajas** por dos caminos (promedio por registro × registros; promedio por caja × cajas) para
  dimensionar el hosting. Lo mismo por consola: `php bin/espacio.php` (`--json`).
- **Ficha del registro (backoffice):** cada archivo muestra su id de Drive y si está compartido; en rojo si un restringido está
  compartido con cualquiera con el enlace, en amarillo si un público no lo está. Sin «Vista previa» para los que solo se descargan.
- **Verificaciones:** `DRIVE_RESTRINGIDO_COMPARTIDO` (pendiente de validar), `DRIVE_PUBLICO_SIN_COMPARTIR`, `DRIVE_SIN_ID` y
  `LISTADO_LINEA_INVALIDA` (informativas), con leyenda y acción sugerida en `src/Alertas.php`.

## 17. Usuarios y acceso (16/11/2026)

Pantalla **Usuarios** (solo administradores) y **Mi cuenta** (todos, desde el nombre en la barra). Migración 008.

- **Alta:** correo, nombre y perfil. No se escribe ninguna contraseña: al crear el usuario se genera un **enlace de alta**
  (vale 72 h, un solo uso) que se manda por correo y además se muestra una vez al administrador para copiarlo.
- **Edición y baja:** los usuarios no se borran; se **desactivan**. Desactivar corta en el acto las sesiones abiertas
  y anula los enlaces pendientes. Nadie puede cambiarse su propio perfil ni desactivarse, y siempre queda al menos un
  administrador activo. Los cambios de datos quedan en `auditoria` (entidad `usuario`); la contraseña nunca.
- **Olvidé mi contraseña** (en el ingreso): pide el correo y responde siempre lo mismo, exista o no. Si corresponde a un
  usuario activo manda un enlace (vale 2 h, un solo uso), como mucho uno cada 5 minutos por usuario.
- **Contraseñas:** al menos 10 caracteres, distinta del correo. Cambiarla (por enlace o en Mi cuenta) cierra las otras
  sesiones abiertas de ese usuario (`usuario.clave_cambiada_en`).
- **Enlaces:** 32 bytes al azar en la URL; en la base solo el hash SHA-256 (`usuario_token`). Generar uno nuevo anula
  los anteriores.
- **Configuración** (`config/config.php`):
  - `'backoffice' => ['url' => 'https://dominio/archivo/admin']` — base de los enlaces. Sin ella, «Olvidé mi contraseña»
    no manda nada (evita enlaces armados con un encabezado Host falso) y los enlaces que genera un administrador usan el
    dominio del pedido.
  - `'correo' => ['remitente' => 'no-responder@dominio', 'nombre' => 'Las cajas de Aída']` — sale por `mail()` de PHP.
    Sin remitente no se manda correo: el administrador copia el enlace y lo pasa por otro medio.

## 18. Turnstile en los formularios (17/11/2026)

Cloudflare Turnstile (`src/Turnstile.php`) protege los formularios que puede usar cualquiera: ingreso al backoffice,
«Olvidé mi contraseña», pedido de consulta de un documento y el formulario de la Muestra del sitio institucional.

- Se suma a las defensas que ya había (CSRF, campo trampa, tiempo mínimo y límites por conexión); no las reemplaza.
- Configuración: `'turnstile' => ['sitio' => '<site key>', 'secreto' => '<secret key>']`. Sin las dos claves queda apagado.
  En el sitio institucional (estático) la site key va en `TURNSTILE_SITIO` de `_build/generar.py`.
- Hostnames del widget en Cloudflare: lascajasdeaida.com.ar, lascajasdeaida.com y el dominio provisorio.
- Si Cloudflare no responde se deja pasar y se anota en el log (un corte ajeno no deja a nadie afuera). Un token inválido,
  vencido o de otro formulario (`action` distinta) se rechaza.
- Formulario de la Muestra: ahora se envía al archivo (`POST /archivo/muestra/consulta`, mismo dominio, JSON) y llega por
  correo a `'muestra' => ['destino' => ...]` (por defecto lascajasdeaida@gmail.com) con Responder a = quien consulta.
  Si el servidor no está (por ejemplo en la vista previa), se abre el correo como antes. Hasta 5 envíos por hora por conexión.
- Turnstile no protege el sitio entero: eso se hace al apuntar el dominio, poniéndolo detrás de Cloudflare (proxy, reglas
  de WAF y Bot Fight Mode).
