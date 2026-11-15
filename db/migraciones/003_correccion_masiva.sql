-- =====================================================================
-- 003 · Corrección masiva por planilla — esquema v0.7 (15/11/2026)
--
--   proceso.tipo 'correccion'   corrección masiva desde el backoffice (Importaciones → Corrección masiva…)
--                               y recálculo de años (bin/recalcular-anios.php). Se deshace completa
--                               desde Importaciones, como una importación.
--
-- Idempotente: MODIFY de un ENUM se puede repetir sin efecto.
-- =====================================================================

ALTER TABLE proceso MODIFY tipo ENUM('importacion','escaneo','acceso_masivo','curaduria','correccion') NOT NULL;
