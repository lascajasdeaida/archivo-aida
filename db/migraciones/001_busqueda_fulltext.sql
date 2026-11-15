-- =====================================================================
-- 001 · Búsqueda por texto (FULLTEXT) — esquema v0.5 (15/11/2026)
--
-- Reemplaza ft_registro (titulo, autor, lugar, resumen) por tres índices:
--   ft_registro_publico  todo lo que se puede mostrar en el sitio
--   ft_registro_titulo   solo el título, para darle más peso en la relevancia
--   ft_registro_interno  observaciones internas (solo se buscan en el backoffice)
-- y agrega ft_caja_nombre para buscar cajas por nombre.
--
-- Idempotente y compatible con MySQL 8 y MariaDB 10.6+: MySQL no tiene
-- «ADD INDEX IF NOT EXISTS», así que cada paso consulta information_schema.
-- InnoDB crea un solo índice FULLTEXT por ALTER: van en sentencias separadas.
-- Tildes y mayúsculas: las ignora la colación utf8mb4_unicode_ci de las columnas.
-- =====================================================================

SET @t := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registro' AND INDEX_NAME = 'ft_registro');
SET @s := IF(@t > 0, 'ALTER TABLE registro DROP INDEX ft_registro', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;

SET @t := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registro' AND INDEX_NAME = 'ft_registro_publico');
SET @s := IF(@t = 0, 'ALTER TABLE registro ADD FULLTEXT KEY ft_registro_publico (codigo, titulo, autor, lugar, fecha_texto, anio_texto, resumen, isbn, obs_editorial, edicion_catalogo)', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;

SET @t := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registro' AND INDEX_NAME = 'ft_registro_titulo');
SET @s := IF(@t = 0, 'ALTER TABLE registro ADD FULLTEXT KEY ft_registro_titulo (titulo)', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;

SET @t := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registro' AND INDEX_NAME = 'ft_registro_interno');
SET @s := IF(@t = 0, 'ALTER TABLE registro ADD FULLTEXT KEY ft_registro_interno (obs_conservacion, obs_digitalizacion, seleccion_digit, excel_nomenclatura, excel_privado)', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;

SET @t := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'caja' AND INDEX_NAME = 'ft_caja_nombre');
SET @s := IF(@t = 0, 'ALTER TABLE caja ADD FULLTEXT KEY ft_caja_nombre (nombre)', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;
