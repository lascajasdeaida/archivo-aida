-- =====================================================================
-- 008 · Usuarios del backoffice: último ingreso, cambio de contraseña y enlaces de acceso — esquema v1.2 (16/11/2026)
--
--   usuario.ultimo_ingreso      se actualiza en cada ingreso correcto.
--   usuario.clave_cambiada_en   cuándo se cambió la contraseña. Las sesiones abiertas antes de esa hora se cierran solas.
--   usuario_token               enlaces de un solo uso para crear o restablecer la contraseña («Olvidé mi contraseña»
--                               y alta de usuarios). Se guarda solo el hash SHA-256 del enlace, nunca el enlace.
--
-- Idempotente (MySQL 8.4 y MariaDB 10.11): cada paso consulta information_schema.
-- =====================================================================

SET @t := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuario' AND COLUMN_NAME = 'ultimo_ingreso');
SET @s := IF(@t = 0, 'ALTER TABLE usuario ADD COLUMN ultimo_ingreso DATETIME NULL AFTER activo', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;

SET @t := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuario' AND COLUMN_NAME = 'clave_cambiada_en');
SET @s := IF(@t = 0, 'ALTER TABLE usuario ADD COLUMN clave_cambiada_en DATETIME NULL AFTER ultimo_ingreso', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;

CREATE TABLE IF NOT EXISTS usuario_token (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id  INT UNSIGNED NOT NULL,
  hash        CHAR(64)     NOT NULL,
  tipo        ENUM('restablecer','alta') NOT NULL,
  vence_en    DATETIME     NOT NULL,
  usado_en    DATETIME     NULL,
  creado_por  INT UNSIGNED NULL,                   -- NULL: lo pidió la misma persona desde «Olvidé mi contraseña»
  creado_en   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_token_hash (hash),
  KEY idx_token_usuario (usuario_id, creado_en),
  CONSTRAINT fk_token_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
