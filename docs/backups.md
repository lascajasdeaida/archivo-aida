# Backups

La propuesta incluye **backups semanales** de la base y de los archivos digitalizados. La base pesa poco
(≈ 470 KB comprimida con los 1.098 registros de hoy); los PDF pesan cientos de GB y van por otro camino.

## Base de datos

```bash
bin/backup.sh --simular     # qué haría: base, archivo, copias que borraría la rotación
bin/backup.sh               # backup
```

- Lee la conexión de `config/config.php`. **La contraseña no aparece en `ps`** ni en el historial: va en un archivo
  temporal con permisos 600 (`--defaults-extra-file`) que se borra al terminar. MySQL ignora ese archivo si tiene
  permisos abiertos; el script fija `umask 077`.
- `mysqldump`/`mariadb-dump` con `--single-transaction` (copia consistente sin frenar el sitio), `--no-tablespaces`
  (si no, MySQL 8 pide el privilegio PROCESS, que un hosting no da), `utf8mb4`, y `--set-gtid-purged=OFF` cuando el
  cliente es de MySQL. Sin `CREATE DATABASE`/`USE`: se puede restaurar en cualquier base.
- Comprime con gzip, **verifica** (gzip íntegro y el dump terminó con «Dump completed») y guarda un `.sha256`.
  Si algo falla, no rota nada y sale con error (cron lo manda por mail).
- **Rotación:** después de un backup bueno conserva los **8 más nuevos** (dos meses de semanales).
- Nombre: `aida_<base>_AAAA-MM-DD_HHMMSS.sql.gz`. No corren dos a la vez (`flock`).

Configuración opcional en `config/config.php`:

```php
'backup' => ['dir' => '/home/usuario/backups-aida', 'conservar' => 8],
```

Por defecto: carpeta `backups-aida/` al lado del repositorio. **Nunca dentro de `public/`.**

### Programarlo (cron)

```cron
# Domingos 03:17 (hora del servidor). MAILTO recibe la salida solo si hay errores.
MAILTO=<mail del responsable técnico>
17 3 * * 0  /ruta/al/repo/bin/backup.sh > /dev/null
```

En cPanel: «Cron Jobs», mismo comando. Si el hosting no permite `flock`/`bash`, avisar: se puede hacer una versión PHP.
Una copia semanal en el mismo servidor no alcanza: bajar o sincronizar `backups-aida/` a otro lado
(el mismo `rclone` de los PDF, abajo).

### Restaurar

```bash
bin/restaurar.sh backups-aida/aida_aida_2026-11-15_031700.sql.gz aida_restore            # en una base vacía o nueva
bin/restaurar.sh backups-aida/aida_aida_2026-11-15_031700.sql.gz aida_restore --forzar   # si ya tiene tablas
php bin/migrar.php --estado                                                              # con config apuntando a esa base
```

`restaurar.sh` verifica el `.sha256` y que el dump esté completo **antes** de tocar nada, crea la base si no existe,
**se niega a pisar una base con tablas** salvo `--forzar`, y al final muestra cuántas cajas, registros, archivos y
cambios del historial quedaron. Si el backup es anterior a alguna migración, correr `php bin/migrar.php`.

A mano (por ejemplo en phpMyAdmin): descomprimir y en la base nueva importar el `.sql` con juego de caracteres
**utf8mb4**. Con el cliente de consola: `zcat archivo.sql.gz | mysql --default-character-set=utf8mb4 base`.
Sin `utf8mb4`, el cliente de MariaDB corta los textos con tildes («Data too long»).

**Para volver atrás la base del sitio:** restaurar primero en `aida_restore`, revisar, y recién ahí restaurar sobre la
base real con `--forzar` (o cambiar `config.php` para que apunte a la restaurada). Antes, hacer un `bin/backup.sh`
del estado actual. Para deshacer cambios puntuales no hace falta un backup: está el historial (`bin/revertir.php`).

### Prueba de restauración (15/11/2026)

| Prueba | Resultado |
|---|---|
| Backup de `aida_bd` (MariaDB 10.11, 1.098 registros) | 468 KB, < 1 s; en `ps` solo `--defaults-extra-file=…`, ninguna contraseña |
| Rotación con 9 copias viejas + la nueva | quedaron 8, se borraron las 2 más viejas |
| Restaurar en `aida_bd_restore` (MariaDB) | `CHECKSUM TABLE` de las 10 tablas idéntico al original; búsqueda FULLTEXT funcionando |
| Restaurar de nuevo sin `--forzar` | rechazado: «ya tiene 13 tablas. No se toca nada» |
| Backup truncado | rechazado en la verificación, sin tocar la base |
| Backup de MariaDB restaurado en MySQL 8.4 | 1.098 registros, búsqueda igual |
| Backup hecho con `mysqldump` 8.4 restaurado en MariaDB | 1.098 registros |

## Archivos digitalizados (PDF) — estrategia

Hoy están en Drive («Selección de Cajas») y todavía no está definido dónde quedan los definitivos
(`docs/decisiones.md`, Pendientes). Con cualquiera de las dos opciones:

- **No van en el dump de la base** (solo sus rutas en `archivo_digital`). Se copian aparte, archivo por archivo,
  de forma **incremental**: cada semana se transfiere solo lo nuevo o cambiado.
- **Si quedan en el servidor:** `rclone sync` (o `rsync -a` a otro servidor por SSH) hacia un almacenamiento externo
  (Backblaze B2, S3, Drive de la organización), con `--backup-dir` fechado para que un archivo borrado o pisado por
  error no se pierda en la copia:

  ```bash
  rclone sync /var/www/aida-archivos remoto:aida-archivos \
    --backup-dir remoto:aida-archivos-anteriores/$(date +%F) --checksum --transfers 4
  ```

  En cron, una hora después del backup de la base. `rclone` guarda sus credenciales en su propio archivo de
  configuración (permisos 600), no en la línea de comandos.
- **Si quedan en Drive:** Drive es la copia de trabajo del equipo de digitalización, pero no es un backup
  (un borrado se sincroniza). Copia semanal con `rclone copy drive:"Selección de Cajas" destino:` a otro lugar.
- **Disco físico** (incluido en la propuesta): copia completa al entregar y luego periódica (mensual o por lote
  de cajas), guardada fuera de la oficina.
- **Verificar:** `rclone check` mensual y comparar la cantidad de archivos con `SELECT COUNT(*) FROM archivo_digital
  WHERE presente = 1 AND eliminado_en IS NULL`.
- Tamaño: hay PDF de 500–780 MB. La primera copia tarda; las siguientes son chicas.
