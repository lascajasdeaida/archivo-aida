-- =====================================================================
-- Archivo AIDA BORTNIK · Las cajas de Aída
-- Esquema MySQL / MariaDB · InnoDB · utf8mb4
-- Versión 1.0 (16/11/2026) — origen de archivos intercambiable: id de Drive y si está compartido (archivo_digital)
-- Versión 0.9 (15/11/2026) — consultas a pedido del sitio público (consulta) y publicación por caja
-- Versión 0.8 (15/11/2026) — años de cada registro (registro_anio): las listas salteadas filtran solo sus años
-- Versión 0.7 (15/11/2026) — corrección masiva por planilla (proceso 'correccion')
-- Versión 0.6 (15/11/2026) — curaduría de vocabularios (mover, descartar, alias) y años con rango
--
-- Los cambios posteriores a una instalación van además como migración en
-- db/migraciones/NNN_*.sql (se aplican con php bin/migrar.php).
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Períodos (ejes cronológicos). Se dan de alta desde el backoffice.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS periodo (
  id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(60)  NOT NULL,
  orden       TINYINT      NOT NULL DEFAULT 0,
  anio_desde  SMALLINT     NULL,
  anio_hasta  SMALLINT     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_periodo_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Cajas. `numero` es el número físico del depósito (se puede forzar).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS caja (
  id               SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  numero           SMALLINT UNSIGNED NOT NULL,
  nombre           VARCHAR(255) NOT NULL,          -- "Nombre de Caja" (col. B)
  nombre_pestania  VARCHAR(64)  NULL,              -- título de la pestaña del Excel, para trazabilidad
  resumen          TEXT         NULL,              -- "Edición de Catálogo" del registro que la presenta
  anio_desde       SMALLINT     NULL,
  anio_hasta       SMALLINT     NULL,
  publicada        TINYINT(1)   NOT NULL DEFAULT 0,
  campos_bloqueados JSON        NULL,              -- campos editados a mano: el importador no los pisa
  eliminado_en     DATETIME     NULL,              -- baja desde el backoffice: el importador no la vuelve a crear
  eliminado_por    INT UNSIGNED NULL,
  creado_en        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_caja_numero (numero),
  FULLTEXT KEY ft_caja (nombre, resumen),
  FULLTEXT KEY ft_caja_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Una caja puede pertenecer a más de un período (decisión sep 2026).
CREATE TABLE IF NOT EXISTS caja_periodo (
  caja_id     SMALLINT UNSIGNED NOT NULL,
  periodo_id  TINYINT UNSIGNED  NOT NULL,
  PRIMARY KEY (caja_id, periodo_id),
  CONSTRAINT fk_cp_caja    FOREIGN KEY (caja_id)    REFERENCES caja(id)    ON DELETE CASCADE,
  CONSTRAINT fk_cp_periodo FOREIGN KEY (periodo_id) REFERENCES periodo(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Registros (una fila del Excel = un registro = una pieza).
-- La clave pública es `codigo` = C{caja}R{registro}, sin ceros.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS registro (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  caja_id              SMALLINT UNSIGNED NOT NULL,
  numero               INT UNSIGNED NOT NULL,          -- "Registro Nº" (col. C)
  codigo               VARCHAR(16)  NOT NULL,          -- C14R3

  titulo               VARCHAR(500) NULL,              -- col. H
  autor                VARCHAR(500) NULL,              -- col. G
  lugar                VARCHAR(500) NULL,              -- col. I
  fecha_texto          VARCHAR(500) NULL,              -- col. J "Fecha como dice el doc" (solo se muestra)
  anio                 SMALLINT     NULL,              -- col. K: resumen, el menor de sus años (orden; el filtro usa registro_anio)
  anio_hasta           SMALLINT     NULL,              -- resumen, el mayor de sus años (= anio si es un año solo)
  anio_sin_confirmar   TINYINT(1)   NOT NULL DEFAULT 0, -- 1 = alguno de sus años viene con duda («1972?»)
  anio_texto           VARCHAR(60)  NULL,              -- col. K tal cual ("1972?", "1982 /1986")
  paginas              INT UNSIGNED NULL,              -- col. L
  resumen              TEXT         NULL,              -- col. M
  isbn                 VARCHAR(60)  NULL,              -- col. R
  obs_editorial        TEXT         NULL,              -- col. S "Observaciones/Editorial"
  edicion_catalogo     TEXT         NULL,              -- "Edición de Catálogo"
  obs_conservacion     TEXT         NULL,
  obs_digitalizacion   TEXT         NULL,
  seleccion_digit      VARCHAR(500) NULL,              -- "Selección para digitalización" (nueva, nov 2026)

  -- Lo que dice el Excel, tal cual, para control cruzado
  excel_digitalizado   TINYINT(1)   NULL,              -- col. "Digitalización" (VERDADERO/FALSO)
  excel_nomenclatura   VARCHAR(40)  NULL,              -- col. "Nombre de archivo digital"
  excel_privado        VARCHAR(255) NULL,              -- col. "PRIVADO" (VERDADERO / "VERLO" / texto)

  -- Estado efectivo
  acceso               ENUM('publico','restringido','a_revisar') NOT NULL DEFAULT 'publico',
  digitalizado         TINYINT(1)   NOT NULL DEFAULT 0, -- 1 si hay al menos un archivo_digital (lo fija el escaneo)
  publicado            TINYINT(1)   NOT NULL DEFAULT 1,

  campos_bloqueados    JSON         NULL,              -- campos editados en el backoffice
  origen               ENUM('excel','backoffice') NOT NULL DEFAULT 'excel', -- 'backoffice': el importador no lo toca
  eliminado_en         DATETIME     NULL,              -- baja desde el backoffice: el importador no lo vuelve a crear
  eliminado_por        INT UNSIGNED NULL,
  creado_por           INT UNSIGNED NULL,
  actualizado_por      INT UNSIGNED NULL,
  importado_en         DATETIME     NULL,
  creado_en            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_registro_caja_numero (caja_id, numero),
  UNIQUE KEY uq_registro_codigo (codigo),
  KEY idx_registro_anio (anio),
  KEY idx_registro_anio_rango (anio, anio_hasta),
  KEY idx_registro_acceso (acceso, publicado),
  -- Búsqueda (src/Buscador.php, docs/busqueda.md). Tildes y mayúsculas: las ignora la colación.
  FULLTEXT KEY ft_registro_publico (codigo, titulo, autor, lugar, fecha_texto, anio_texto, resumen, isbn, obs_editorial, edicion_catalogo),
  FULLTEXT KEY ft_registro_titulo (titulo),
  FULLTEXT KEY ft_registro_interno (obs_conservacion, obs_digitalizacion, seleccion_digit, excel_nomenclatura, excel_privado),
  CONSTRAINT fk_registro_caja FOREIGN KEY (caja_id) REFERENCES caja(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Vocabularios curados: Tipo de documento (cols. D–F) y Descriptor (cols. N–Q).
-- El data entry solo elige de la lista. Si el Excel trae un valor nuevo,
-- el importador lo agrega como 'propuesto' y lo reporta para curaduría.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vocabulario (
  id         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo       ENUM('tipo_documento','descriptor') NOT NULL,
  nombre     VARCHAR(120) NOT NULL,
  clave      VARCHAR(120) NOT NULL,                 -- nombre normalizado (sin tildes, minúscula) para deduplicar
  estado     ENUM('aprobado','propuesto','descartado') NOT NULL DEFAULT 'propuesto', -- descartado: basura, la importación lo ignora
  fusionado_en SMALLINT UNSIGNED NULL,              -- si se fusionó con otro término o se movió al otro vocabulario
  regla      ENUM('fusion','movido') NULL,          -- por qué apunta a fusionado_en
  motivo     VARCHAR(500) NULL,                     -- motivo del último cambio de curaduría
  eliminado_en  DATETIME NULL,                      -- se deshizo su alta (la importación lo revive si vuelve a venir)
  eliminado_por INT UNSIGNED NULL,
  creado_en  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vocab (tipo, clave),
  CONSTRAINT fk_vocab_fusion FOREIGN KEY (fusionado_en) REFERENCES vocabulario(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grafías anteriores de términos renombrados: la importación las resuelve al término vigente.
CREATE TABLE IF NOT EXISTS vocabulario_alias (
  id             SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo           ENUM('tipo_documento','descriptor') NOT NULL,
  clave          VARCHAR(120) NOT NULL,
  nombre         VARCHAR(120) NOT NULL,
  vocabulario_id SMALLINT UNSIGNED NOT NULL,
  eliminado_en   DATETIME NULL,
  eliminado_por  INT UNSIGNED NULL,
  creado_en      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_alias (tipo, clave),
  KEY idx_alias_vocab (vocabulario_id),
  CONSTRAINT fk_alias_vocab FOREIGN KEY (vocabulario_id) REFERENCES vocabulario(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Años a los que responde cada registro (col. K, Texto::anio; ver src/Anios.php y docs/decisiones.md).
-- «1998/2006/2007/2008» = 4 filas; «1981/82» o «Entre 1983 y 1986» = todos los años del rango.
-- Filtro por año, rango y década y faceta de décadas (src/Buscador.php). Derivada: la mantienen Historial e Importador.
CREATE TABLE IF NOT EXISTS registro_anio (
  registro_id    INT UNSIGNED NOT NULL,
  anio           SMALLINT     NOT NULL,
  sin_confirmar  TINYINT(1)   NOT NULL DEFAULT 0,       -- ese año está con duda en la planilla («1980?»)
  PRIMARY KEY (registro_id, anio),
  KEY idx_registro_anio_anio (anio, registro_id),
  CONSTRAINT fk_ra_registro FOREIGN KEY (registro_id) REFERENCES registro(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registro_vocabulario (
  registro_id    INT UNSIGNED      NOT NULL,
  vocabulario_id SMALLINT UNSIGNED NOT NULL,
  orden          TINYINT UNSIGNED  NOT NULL DEFAULT 1,  -- Tipo 1/2/3, Descriptor 1..4
  PRIMARY KEY (registro_id, vocabulario_id),
  KEY idx_rv_vocab (vocabulario_id),
  CONSTRAINT fk_rv_registro FOREIGN KEY (registro_id)    REFERENCES registro(id)    ON DELETE CASCADE,
  CONSTRAINT fk_rv_vocab    FOREIGN KEY (vocabulario_id) REFERENCES vocabulario(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Archivos digitales (1..N por registro). Los binarios NO van en la base.
-- Hoy: un PDF por registro, suelto en la carpeta de la caja (C14/C14R3.pdf).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS archivo_digital (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  registro_id    INT UNSIGNED NOT NULL,
  ruta           VARCHAR(512) NOT NULL,             -- relativa a la raíz de archivos
  nombre_archivo VARCHAR(255) NOT NULL,
  parte          SMALLINT UNSIGNED NOT NULL DEFAULT 0, -- 0 = archivo único; 1..n si viene C14R3_001…
  formato        VARCHAR(10)  NOT NULL,             -- pdf, jpg, tif, mp3, mp4…
  tipo_medio     ENUM('documento','imagen','audio','video','otro') NOT NULL DEFAULT 'documento',
  peso_bytes     BIGINT UNSIGNED NULL,
  modificado_en  DATETIME     NULL,                 -- mtime del archivo
  version_web    VARCHAR(512) NULL,                 -- copia liviana para visualizar (pendiente)
  miniatura      VARCHAR(512) NULL,
  drive_id       VARCHAR(200) NULL,                 -- id en el Drive del cliente (listado de Drive); se conserva con origen «disco»
  drive_compartido TINYINT(1) NULL,                 -- 1 = «cualquiera con el enlace», 0 = no, NULL = sin dato
  presente       TINYINT(1)   NOT NULL DEFAULT 1,   -- 0 si desapareció en el último escaneo
  origen         ENUM('escaneo','backoffice') NOT NULL DEFAULT 'escaneo',
  eliminado_en   DATETIME     NULL,                 -- quitado desde el backoffice: el escaneo no lo vuelve a asociar
  eliminado_por  INT UNSIGNED NULL,
  visto_en       DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_archivo_ruta (ruta),
  KEY idx_archivo_registro (registro_id),
  KEY idx_archivo_drive (drive_id),
  CONSTRAINT fk_archivo_registro FOREIGN KEY (registro_id) REFERENCES registro(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Bitácora de importaciones y escaneos, con incidencias para revisar.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS proceso (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo        ENUM('importacion','escaneo','acceso_masivo','curaduria','correccion') NOT NULL, -- correccion: corrección masiva (backoffice)
  fuente      VARCHAR(512) NULL,
  simulacion  TINYINT(1)   NOT NULL DEFAULT 0,
  usuario_id  INT UNSIGNED NULL,                    -- quién lo hizo (curaduría desde el backoffice)
  resumen     JSON         NULL,
  iniciado_en DATETIME     NOT NULL,
  terminado_en DATETIME    NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS incidencia (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  proceso_id  INT UNSIGNED NOT NULL,
  nivel       ENUM('info','aviso','error') NOT NULL DEFAULT 'aviso',
  codigo      VARCHAR(40)  NOT NULL,                -- p. ej. NOMENCLATURA_DISTINTA, PDF_SIN_REGISTRO
  caja        SMALLINT UNSIGNED NULL,
  registro    INT UNSIGNED NULL,
  detalle     VARCHAR(1000) NOT NULL,
  requiere_validacion TINYINT(1) NOT NULL DEFAULT 0, -- 1 = el operador tiene que revisarla y confirmarla
  resuelta    TINYINT(1)   NOT NULL DEFAULT 0,
  resuelta_por INT UNSIGNED NULL,
  resuelta_en DATETIME     NULL,
  resolucion  VARCHAR(500) NULL,                   -- qué decidió el operador
  PRIMARY KEY (id),
  KEY idx_inc_proceso (proceso_id),
  KEY idx_inc_codigo (codigo, resuelta),
  CONSTRAINT fk_inc_proceso FOREIGN KEY (proceso_id) REFERENCES proceso(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Usuarios del backoffice y, si se define, del front.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuario (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email         VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  nombre        VARCHAR(120) NULL,
  rol           ENUM('admin','editor','data_entry','qa','visitante') NOT NULL DEFAULT 'visitante',
  activo        TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuario_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial de cambios con valor anterior y nuevo de cada campo. Permite volver atrás
-- un cambio puntual o todo lo que hizo una importación (ver src/Historial.php).
CREATE TABLE IF NOT EXISTS auditoria (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id   INT UNSIGNED NULL,
  origen       ENUM('backoffice','importacion','escaneo','reversion','sitio') NOT NULL DEFAULT 'backoffice', -- sitio: alta de una consulta
  proceso_id   INT UNSIGNED NULL,                   -- si lo hizo una importación o un escaneo
  entidad      ENUM('caja','registro','archivo_digital','vocabulario','vocabulario_alias','periodo','usuario','consulta') NOT NULL,
  entidad_id   INT UNSIGNED NOT NULL,
  accion       ENUM('alta','modificacion','baja','restauracion','reversion') NOT NULL,
  cambios      JSON NULL,                           -- {campo: [antes, después]}
  revierte_id  BIGINT UNSIGNED NULL,                -- si este cambio deshace otro
  revertido_en DATETIME NULL,                       -- si este cambio fue deshecho
  creado_en    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_aud_entidad (entidad, entidad_id),
  KEY idx_aud_proceso (proceso_id),
  KEY idx_aud_fecha (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Consultas a pedido del sitio público (/consulta/C2R5). Datos personales: solo acá y en el backoffice.
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

-- Métricas propias (complementan GA4)
CREATE TABLE IF NOT EXISTS evento_uso (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  registro_id INT UNSIGNED NULL,
  archivo_id  INT UNSIGNED NULL,
  tipo        ENUM('vista','descarga','busqueda') NOT NULL,
  termino     VARCHAR(255) NULL,
  referer     VARCHAR(255) NULL,                 -- ruta del mismo sitio o solo el dominio de afuera (007)
  sesion_hash CHAR(16) NULL,                     -- HMAC del día (fecha|IP|navegador): personas distintas sin guardar IP (007)
  creado_en   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ev_registro (registro_id),
  KEY idx_ev_fecha (creado_en),
  KEY idx_ev_archivo_fecha (archivo_id, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migraciones aplicadas (bin/migrar.php). Este esquema ya incluye las que se registran abajo.
CREATE TABLE IF NOT EXISTS migracion (
  version      CHAR(3)      NOT NULL,
  nombre       VARCHAR(120) NOT NULL,
  checksum     CHAR(40)     NULL,                   -- NULL: incluida en schema.sql
  duracion_ms  INT UNSIGNED NULL,
  aplicada_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO migracion (version, nombre) VALUES
  ('001', '001_busqueda_fulltext.sql'),
  ('002', '002_curaduria_y_anios.sql'),
  ('003', '003_correccion_masiva.sql'),
  ('004', '004_registro_anio.sql'),
  ('005', '005_consultas_y_sitio.sql'),
  ('006', '006_archivo_drive.sql'),
  ('007', '007_evento_uso_contexto.sql');

SET FOREIGN_KEY_CHECKS = 1;
