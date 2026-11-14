# Instrucciones para trabajar en este repositorio

Proyecto: plataforma del archivo de Aída Bortnik (Las cajas de Aída). Cliente: Las Cajas de Aída. Desarrollo: P3DESIGN.

- PHP 8.1+ nativo, sin framework y sin Composer. MySQL/MariaDB con `db/schema.sql` como fuente de verdad del esquema.
- Antes de cambiar el importador o el escaneo, leer `docs/decisiones.md`. Si una regla cambia, actualizar ese archivo en el mismo commit.
- La clave de un registro es caja + n.º de registro (`C14R3`). No usar la columna de nomenclatura del Excel como clave.
- La carga es incremental: nada se borra en una importación ni en un escaneo; se reporta como incidencia.
- Respetar `campos_bloqueados`: lo editado a mano en el backoffice no se pisa.
- Probar siempre con `--simular` antes de correr contra una base con datos.
- No subir al repositorio el Excel, los PDF ni `config/config.php`.
- Textos de interfaz y mensajes en castellano rioplatense; código e identificadores en castellano, como el resto del repo.
- Cambios por pull request para que los revise Omar (omar@p3design.com).
