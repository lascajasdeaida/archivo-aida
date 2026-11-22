-- =====================================================================
-- 009 · Versión liviana de cada archivo digital — esquema v1.3 (21/11/2026)
--
--   archivo_digital.liviano_bytes  peso de la copia para leer en pantalla (PDF a 150 dpi, bin/livianos.php).
--   archivo_digital.liviano_en     cuándo se generó.
-- El original no se toca: sigue en su origen (Drive o disco) y se ofrece como «Descargar original».
-- Idempotente (MySQL 8.4 y MariaDB 10.11).
-- =====================================================================

SET @t := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'archivo_digital' AND COLUMN_NAME = 'liviano_bytes');
SET @s := IF(@t = 0, 'ALTER TABLE archivo_digital ADD COLUMN liviano_bytes BIGINT UNSIGNED NULL AFTER peso_bytes, ADD COLUMN liviano_en DATETIME NULL AFTER liviano_bytes', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;
