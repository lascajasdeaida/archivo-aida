-- =====================================================================
-- 010 · Seguridad de sesiones y freno de intentos — esquema v1.4 (27/11/2026)
--
--   usuario.sesion_version  sube con cada cambio de contraseña, correo, perfil o baja. La sesión guarda el valor con
--                           que se abrió y se cierra si ya no coincide (comparación exacta, sin relojes).
--   intento                 registro de intentos para frenar abusos: ingreso fallido (por correo y por IP),
--                           «Olvidé mi contraseña» (por IP) y formulario de la Muestra (por IP). Solo se guarda el
--                           hash SHA-256 de la clave (correo o IP), nunca el dato en claro.
--
-- Idempotente (MySQL 8.4 y MariaDB 10.11).
-- =====================================================================

SET @t := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuario' AND COLUMN_NAME = 'sesion_version');
SET @s := IF(@t = 0, 'ALTER TABLE usuario ADD COLUMN sesion_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER clave_cambiada_en', 'DO 0');
PREPARE p FROM @s;
EXECUTE p;
DEALLOCATE PREPARE p;

CREATE TABLE IF NOT EXISTS intento (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo       ENUM('login_email','login_ip','olvide_ip','muestra_ip') NOT NULL,
  clave      CHAR(64)  NOT NULL,                     -- SHA-256 del correo o de la IP
  creado_en  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_intento (tipo, clave, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
