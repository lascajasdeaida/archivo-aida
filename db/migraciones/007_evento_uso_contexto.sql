-- =====================================================================
-- 007 · evento_uso: de dónde vino el pedido y sesión anónima del día — esquema v1.1 (16/11/2026)
--
--   evento_uso.referer      página que pidió el archivo: ruta sin consulta si es del mismo sitio (/archivo/registro/C2R5),
--                           solo el dominio si es de afuera, NULL si el navegador no lo manda. Nunca la URL completa externa.
--   evento_uso.sesion_hash  16 caracteres de HMAC-SHA256(secreto del sitio, fecha | IP | navegador). Sirve para contar
--                           personas distintas por día y archivo sin guardar la IP: cambia cada día y no se puede revertir.
--   Plan de medición §10 (docs/medicion.md). Lo llena src/Entrega.php::registrarUso().
--
-- Idempotente (MySQL 8.4 y MariaDB 10.11): cada paso consulta information_schema.
-- =====================================================================

SET @t := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evento_uso' AND COLUMN_NAME = 'referer');
SET @s := IF(@t = 0, 'ALTER TABLE evento_uso ADD COLUMN referer VARCHAR(255) NULL AFTER termino', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;

SET @t := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evento_uso' AND COLUMN_NAME = 'sesion_hash');
SET @s := IF(@t = 0, 'ALTER TABLE evento_uso ADD COLUMN sesion_hash CHAR(16) NULL AFTER referer', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;

SET @t := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evento_uso' AND INDEX_NAME = 'idx_ev_archivo_fecha');
SET @s := IF(@t = 0, 'ALTER TABLE evento_uso ADD KEY idx_ev_archivo_fecha (archivo_id, creado_en)', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;
