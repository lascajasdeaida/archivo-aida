-- =====================================================================
-- 006 · Origen de archivos intercambiable: Drive como origen provisorio — esquema v1.0 (16/11/2026)
--
--   archivo_digital.drive_id          identificador del archivo en el Drive compartido del cliente (lo carga el
--                                     listado de Drive: bin/drive-listado.php o Almacenamiento en el backoffice).
--                                     Se conserva aunque el origen vuelva a ser «disco» (docs/despliegue.md).
--   archivo_digital.drive_compartido  1 = compartido «cualquiera con el enlace», 0 = no, NULL = el listado no lo dice.
--   auditoria.origen ya admite 'escaneo': los cambios de id y de compartido quedan en el historial del escaneo.
--
-- Idempotente (MySQL 8.4 y MariaDB 10.11): cada paso consulta information_schema.
-- =====================================================================

SET @t := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'archivo_digital' AND COLUMN_NAME = 'drive_id');
SET @s := IF(@t = 0, 'ALTER TABLE archivo_digital ADD COLUMN drive_id VARCHAR(200) NULL AFTER miniatura', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;

SET @t := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'archivo_digital' AND COLUMN_NAME = 'drive_compartido');
SET @s := IF(@t = 0, 'ALTER TABLE archivo_digital ADD COLUMN drive_compartido TINYINT(1) NULL AFTER drive_id', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;

SET @t := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'archivo_digital' AND INDEX_NAME = 'idx_archivo_drive');
SET @s := IF(@t = 0, 'ALTER TABLE archivo_digital ADD KEY idx_archivo_drive (drive_id)', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;
