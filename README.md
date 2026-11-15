# Archivo AIDA BORTNIK · Las cajas de Aída

Plataforma de acceso al archivo digitalizado de Aída Bortnik: base de datos, importación del catálogo,
asociación de los archivos digitalizados, backoffice y front público.

**Stack:** PHP 8.1+ nativo (sin framework, sin dependencias de Composer) + MySQL 8 / MariaDB 10.6+.
Extensiones de PHP necesarias: `pdo_mysql`, `zip`, `xmlreader`, `simplexml`, `mbstring`.

## Estado

| Pieza | Estado |
|---|---|
| Modelo de datos (`db/schema.sql`) | ✅ v0.4 (ABM, bajas lógicas, historial reversible) |
| Importador del Excel del catálogo (`bin/importar.php`) | ✅ probado con las 70 pestañas |
| Escaneo de archivos digitalizados (`bin/escanear.php`) | ✅ probado con la muestra de 8 cajas |
| Historial reversible (`src/Historial.php`, `bin/revertir.php`) | ✅ |
| Backoffice | especificado en `docs/backoffice.md`; falta construir |
| Front público | maqueta aprobada (v4); falta conectar |
| Versiones livianas para web de los PDF pesados | pendiente |

## Puesta en marcha

```bash
cp config/config.example.php config/config.php     # completar base y carpeta de archivos
mysql -u root -p -e "CREATE DATABASE aida CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p aida < db/schema.sql
```

## Carga de datos

El orden siempre es: **primero el Excel, después los archivos.** Las dos cosas se pueden repetir
todas las veces que haga falta (la carga es incremental).

```bash
# 1. Catálogo (Excel). --simular muestra el reporte sin guardar nada.
php bin/importar.php "Catálogo Aída Parte 1 BACKUP CONSERVACIÓN .xlsx" --simular
php bin/importar.php "Catálogo Aída Parte 1 BACKUP CONSERVACIÓN .xlsx"
php bin/importar.php catalogo.xlsx --cajas=1,2,6          # solo algunas cajas

# 2. Archivos digitalizados
php bin/escanear.php /ruta/a/los/archivos                 # carpetas C1/, C2/, … con C1R1.pdf, …
php bin/escanear.php --listado=datos/listado_drive_20261114.tsv   # si todavía están en Drive
```

## Deshacer cambios

```bash
php bin/revertir.php --ver=registro:C14R3      # historial de un registro (cada cambio con su número)
php bin/revertir.php --cambio=209              # deshace un cambio puntual
php bin/revertir.php --proceso=2               # deshace todo lo que hizo una importación
```

Si un campo se modificó de nuevo después, no se aplica nada y se listan los conflictos (`--forzar` para aplicarlo igual).

Cada corrida deja un registro en las tablas `proceso` e `incidencia` y muestra un resumen por consola
con lo que hay que revisar (nomenclaturas mal tipeadas, archivos sin registro, PDF pendientes de subir, etc.).

## Estructura

```
bin/        scripts de línea de comandos (importar, escanear)
config/     configuración (config.php no se versiona)
datos/      listados de referencia (no van los Excel ni los PDF)
db/         esquema SQL
docs/       decisiones y reglas de negocio
src/        clases PHP (namespace Aida)
```

## Documentación

- [`docs/decisiones.md`](docs/decisiones.md): reglas de importación y por qué son así. **Leer antes de tocar el importador.**
- [`docs/backoffice.md`](docs/backoffice.md): ABM, leyendas, confirmaciones, notificaciones de verificaciones pendientes e historial con deshacer.

## Equipo

P3DESIGN (Andrés Fernández Fantl, Omar Alegre) para Las Cajas de Aída.
