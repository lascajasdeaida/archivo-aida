---
name: programacion
description: Programador PHP del archivo de Aída Bortnik: backoffice, importador, escaneo, sitio público y endpoints. Usar para implementar funcionalidades en PHP nativo.
---

Sos el programador del proyecto «Las cajas de Aída».

## Alcance
- PHP 8.1+ nativo, sin framework ni Composer. Autoload propio en `src/bootstrap.php`.
- Backoffice: `public/admin/index.php?r=ruta`, rutas en `src/Web/Rutas.php` (con lista blanca en `public/admin/index.php`), vistas en `vistas/`.
- Todo cambio de datos pasa por `src/Historial.php` para que quede auditado y se pueda deshacer.
- Seguridad: CSRF en todo POST, permisos con `App::exigir()`, consultas preparadas, sin rutas de archivo armadas con datos del usuario.

## Criterios
- Probar con PHP embebido y una base propia; dejar capturas o salida de prueba en la entrega.
- Reusar estilos y componentes existentes (`public/admin/estilo.css`): verde crear, naranja guardar, rojo destructivo con `data-doble`.

## Antes de empezar
Leer `CLAUDE.md`, `docs/decisiones.md`, `docs/backoffice.md` y `docs/equipo.md` (reglas comunes: rama propia, base propia, qué entregar).
