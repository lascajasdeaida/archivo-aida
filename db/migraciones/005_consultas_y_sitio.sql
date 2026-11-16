-- =====================================================================
-- 005 · Consultas a pedido del sitio público — esquema v0.9 (15/11/2026)
--
--   consulta                     pedidos de consulta que llegan desde /consulta/C2R5 (sitio público).
--                                Los datos personales (nombre, correo, institución, mensaje) viven solo acá:
--                                se ven en el backoffice (Consultas) y no van al historial ni a las estadísticas.
--   auditoria.entidad 'consulta' los cambios de estado y notas del backoffice pasan por Historial (se deshacen).
--   auditoria.origen  'sitio'    el alta de una consulta la hace un visitante del sitio, sin usuario.
--
-- Idempotente (MySQL 8.4 y MariaDB 10.11): CREATE TABLE IF NOT EXISTS y MODIFY de ENUM (repetirlo no cambia nada).
-- =====================================================================

CREATE TABLE IF NOT EXISTS consulta (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  registro_id   INT UNSIGNED NULL,                    -- documento pedido (NULL si después se borró el registro)
  codigo        VARCHAR(20)  NOT NULL,                -- código del documento al momento del pedido (C2R5)
  tipo          ENUM('a_pedido','original') NOT NULL DEFAULT 'a_pedido', -- a_pedido: restringido; original: sin digitalizar u otro
  nombre        VARCHAR(120) NOT NULL,
  correo        VARCHAR(190) NOT NULL,
  institucion   VARCHAR(160) NULL,
  uso           ENUM('investigacion','prensa','produccion','personal','otro') NOT NULL, -- uso previsto
  modalidad     ENUM('digital','presencial') NOT NULL DEFAULT 'digital',
  motivo        TEXT         NOT NULL,                -- qué busca y por qué (mensaje del formulario)
  estado        ENUM('nueva','respondida','descartada') NOT NULL DEFAULT 'nueva',
  notas         TEXT         NULL,                    -- notas internas del equipo (no se le muestran a quien pidió)
  atendida_por  INT UNSIGNED NULL,
  atendida_en   DATETIME     NULL,
  ip_hash       CHAR(64)     NULL,                    -- HMAC de la IP: solo para limitar envíos seguidos; no se muestra
  eliminado_en  DATETIME     NULL,
  eliminado_por INT UNSIGNED NULL,
  creado_en     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_consulta_estado (estado, creado_en),
  KEY idx_consulta_registro (registro_id),
  KEY idx_consulta_ip (ip_hash, creado_en),
  CONSTRAINT fk_consulta_registro FOREIGN KEY (registro_id) REFERENCES registro(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE auditoria MODIFY entidad ENUM('caja','registro','archivo_digital','vocabulario','vocabulario_alias','periodo','usuario','consulta') NOT NULL;
ALTER TABLE auditoria MODIFY origen ENUM('backoffice','importacion','escaneo','reversion','sitio') NOT NULL DEFAULT 'backoffice';
