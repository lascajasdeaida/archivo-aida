-- =====================================================================
-- 002 · Curaduría de vocabularios y años con rango — esquema v0.6 (15/11/2026)
--
-- Años (col. K del Excel):
--   registro.anio                = año, o primer año del rango (el de siempre: filtro y orden)
--   registro.anio_hasta          = último año del rango (= anio si es un año solo)
--   registro.anio_sin_confirmar  = 1 si la planilla lo trae con duda («1972?», «1977 o 1978»)
--   Un registro responde a todos los años entre anio y anio_hasta (docs/decisiones.md).
--
-- Vocabularios:
--   vocabulario.estado 'descartado'  término basura: no se muestra, no se asigna, la importación lo ignora
--   vocabulario.regla                por qué apunta a otro término (fusionado_en): fusion | movido
--   vocabulario.motivo               motivo del último cambio de curaduría (obligatorio al descartar)
--   vocabulario.eliminado_*          deshacer el alta de un término (no se borra; la importación lo revive)
--   vocabulario_alias                grafías anteriores de un término renombrado: la importación las resuelve
--   proceso.tipo 'curaduria'         cada movimiento, fusión, renombre o descarte es un proceso reversible
--   proceso.usuario_id               quién lo hizo (para el informe de curaduría)
--
-- Idempotente y compatible con MySQL 8.4 y MariaDB 10.11: MySQL no tiene «ADD COLUMN IF NOT EXISTS»,
-- así que cada paso consulta information_schema. MODIFY de un ENUM se puede repetir sin efecto.
-- =====================================================================

-- --- registro: rango de años y año sin confirmar ---------------------
SET @t := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registro' AND COLUMN_NAME = 'anio_hasta');
SET @s := IF(@t = 0, 'ALTER TABLE registro ADD COLUMN anio_hasta SMALLINT NULL AFTER anio', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;

SET @t := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registro' AND COLUMN_NAME = 'anio_sin_confirmar');
SET @s := IF(@t = 0, 'ALTER TABLE registro ADD COLUMN anio_sin_confirmar TINYINT(1) NOT NULL DEFAULT 0 AFTER anio_hasta', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;

SET @t := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registro' AND INDEX_NAME = 'idx_registro_anio_rango');
SET @s := IF(@t = 0, 'ALTER TABLE registro ADD KEY idx_registro_anio_rango (anio, anio_hasta)', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;

-- Los años limpios que ya estaban siguen filtrando igual (rango de un solo año).
-- Los rangos y los años con duda se completan con la próxima importación.
UPDATE registro SET anio_hasta = anio WHERE anio IS NOT NULL AND anio_hasta IS NULL;

-- --- vocabulario: descartado, regla, motivo, baja lógica -------------
ALTER TABLE vocabulario MODIFY estado ENUM('aprobado','propuesto','descartado') NOT NULL DEFAULT 'propuesto';

SET @t := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vocabulario' AND COLUMN_NAME = 'regla');
SET @s := IF(@t = 0, 'ALTER TABLE vocabulario ADD COLUMN regla ENUM(''fusion'',''movido'') NULL AFTER fusionado_en', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;

SET @t := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vocabulario' AND COLUMN_NAME = 'motivo');
SET @s := IF(@t = 0, 'ALTER TABLE vocabulario ADD COLUMN motivo VARCHAR(500) NULL AFTER regla', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;

SET @t := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vocabulario' AND COLUMN_NAME = 'eliminado_en');
SET @s := IF(@t = 0, 'ALTER TABLE vocabulario ADD COLUMN eliminado_en DATETIME NULL AFTER motivo, ADD COLUMN eliminado_por INT UNSIGNED NULL AFTER eliminado_en', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;

-- Las fusiones hechas antes de esta versión
UPDATE vocabulario SET regla = 'fusion' WHERE fusionado_en IS NOT NULL AND regla IS NULL;

-- --- grafías anteriores de términos renombrados ------------------------
CREATE TABLE IF NOT EXISTS vocabulario_alias (
  id             SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo           ENUM('tipo_documento','descriptor') NOT NULL,  -- columna del Excel donde se reconoce
  clave          VARCHAR(120) NOT NULL,                         -- grafía anterior normalizada (Texto::clave)
  nombre         VARCHAR(120) NOT NULL,                         -- grafía anterior, tal cual
  vocabulario_id SMALLINT UNSIGNED NOT NULL,                    -- término vigente
  eliminado_en   DATETIME NULL,                                 -- al deshacer el renombre
  eliminado_por  INT UNSIGNED NULL,
  creado_en      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_alias (tipo, clave),
  KEY idx_alias_vocab (vocabulario_id),
  CONSTRAINT fk_alias_vocab FOREIGN KEY (vocabulario_id) REFERENCES vocabulario(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- procesos de curaduría y auditoría de alias -------------------------
ALTER TABLE proceso MODIFY tipo ENUM('importacion','escaneo','acceso_masivo','curaduria') NOT NULL;

SET @t := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proceso' AND COLUMN_NAME = 'usuario_id');
SET @s := IF(@t = 0, 'ALTER TABLE proceso ADD COLUMN usuario_id INT UNSIGNED NULL AFTER simulacion', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;

ALTER TABLE auditoria MODIFY entidad ENUM('caja','registro','archivo_digital','vocabulario','vocabulario_alias','periodo','usuario') NOT NULL;
