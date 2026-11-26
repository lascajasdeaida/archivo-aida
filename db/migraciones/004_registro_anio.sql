-- =====================================================================
-- 004 · Años de cada registro (registro_anio) — esquema v0.8 (15/11/2026)
--
-- Un registro responde solo a los años de su columna Año (docs/decisiones.md, «Años»):
--   «1981/82», «Entre 1983 y 1986»   → todos los años del rango
--   «1998/2006/2007/2008»            → 1998, 2006, 2007 y 2008 (no 1999–2005)
--   registro_anio (registro_id, anio, sin_confirmar)   una fila por año; la usan el filtro por año, rango y década
--   registro.anio / anio_hasta / anio_sin_confirmar    quedan como resumen (menor, mayor, alguno dudoso) para ordenar y mostrar
--
-- Esta migración crea la tabla y la completa con lo que ya había (todos los años entre anio y anio_hasta,
-- como filtraba hasta ahora). Al aplicarla con bin/migrar.php o desde «Base de datos», Migrador corre además
-- Anios::reconstruir(), que pasa a la lista real los registros cuyo texto es una lista salteada.
-- Los textos que antes no se leían («1980? 1996/97/98/2006») se completan con la próxima importación o con
-- php bin/recalcular-anios.php (cambian el resumen del registro: va por el historial).
--
-- Idempotente (MySQL 8.4 y MariaDB 10.11): CREATE TABLE IF NOT EXISTS, y solo se completan los registros
-- que todavía no tienen ninguna fila (así no se pisa la lista real si se corre de nuevo).
-- =====================================================================

CREATE TABLE IF NOT EXISTS registro_anio (
  registro_id    INT UNSIGNED NOT NULL,
  anio           SMALLINT     NOT NULL,
  sin_confirmar  TINYINT(1)   NOT NULL DEFAULT 0,       -- ese año está con duda en la planilla («1980?»)
  PRIMARY KEY (registro_id, anio),                      -- los años de un registro (ficha, etiqueta, décadas)
  KEY idx_registro_anio_anio (anio, registro_id),       -- filtro por año o rango: registros de esos años
  CONSTRAINT fk_ra_registro FOREIGN KEY (registro_id) REFERENCES registro(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Completar con los rangos existentes. Números 0..999 sin CTE recursivo (igual en las dos bases).
INSERT IGNORE INTO registro_anio (registro_id, anio, sin_confirmar)
SELECT r.id, r.anio + n.k, r.anio_sin_confirmar
FROM registro r
JOIN (SELECT a.d + 10 * b.d + 100 * c.d AS k
      FROM (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
            UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a
      CROSS JOIN (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
            UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b
      CROSS JOIN (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
            UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) c) n
  ON n.k <= COALESCE(r.anio_hasta, r.anio) - r.anio
WHERE r.anio IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM registro_anio x WHERE x.registro_id = r.id);
