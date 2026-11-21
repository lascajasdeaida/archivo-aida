# Equipo de agentes

El proyecto se reparte entre agentes con roles fijos. Las fichas de cada rol están en `.claude/agents/`.
Sirven para Claude Code: cualquiera del equipo (Andy, Omar) puede invocarlos desde el repo.

## Quién decide

- **Project managers (Andy + Claude):** reparten tareas, deciden reglas de negocio, integran las entregas.
- **Omar (P3DESIGN):** revisa cada pull request antes de pasar a `main`.
- Los agentes **proponen y ejecutan**; no cambian reglas de `docs/decisiones.md` por su cuenta.
  Si algo choca con una regla, lo dejan anotado como pregunta en su entrega.

## Roles

| Rol | Ficha | Alcance |
|---|---|---|
| Base de datos | `base-de-datos` | Esquema, índices, búsqueda, migraciones, backups |
| Programación | `programacion` | PHP del backoffice, importador, escaneo, sitio público, endpoints |
| UX/UI | `ux-ui` | Arquitectura de información y flujos (backoffice y sitio público) |
| Diseño web | `diseno-web` | Diseño visual del sitio público según la identidad de «Las cajas de Aída» |
| Datos y catálogo | `datos-catalogo` | Calidad del Excel, vocabularios, fusiones, normalización |
| Estadísticas | `estadisticas` | GA4, eventos de uso, panel de métricas |
| QA | `qa` | Revisión de cada entrega: funcional, accesibilidad, móvil, regresiones |

## Reglas comunes

1. Antes de empezar: leer `CLAUDE.md`, `docs/decisiones.md`, `docs/backoffice.md` y este archivo.
2. Trabajar en una rama propia: `<rol>/<tarea-corta>` (ej. `bd/busqueda-texto`), partiendo de la rama que indique el PM.
3. Commits chicos y en castellano. Nunca subir el Excel, los PDF, `config/config.php` ni `datos/demo.sql`.
4. Probar contra una base propia (`aida_<rol>`), nunca contra la base compartida del entorno de prueba.
5. Entregar con un resumen: qué se hizo, cómo se probó, qué quedó pendiente y qué decisiones necesita del PM.
6. Textos de interfaz en castellano rioplatense; código e identificadores en castellano.
