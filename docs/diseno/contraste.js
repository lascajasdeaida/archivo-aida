#!/usr/bin/env node
/* Mide los contrastes de docs/diseno/sistema.md a partir de los tokens (WCAG 2.1, luminancia relativa).
   Uso: node docs/diseno/contraste.js          → “Archivo: sistema” (public/sitio/tokens.css)
        node docs/diseno/contraste.js admin    → “Backoffice: sistema” (public/admin/tokens.css) */
const fs = require('fs');
const ADMIN = process.argv[2] === 'admin';
const t = fs.readFileSync(require('path').join(__dirname, ADMIN ? '../../public/admin/tokens.css' : '../../public/sitio/tokens.css'), 'utf8');
const C = {};
for (const m of t.matchAll(/--c-([\w-]+):\s*(#[0-9A-Fa-f]{6})/g)) C[m[1]] = m[2].toUpperCase();
C['blanco'] = '#FFFFFF'; C['visor'] = '#3A3734';
const lum = h => { const c = h.match(/\w\w/g).map(x => parseInt(x, 16) / 255).map(v => v <= .03928 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4); return .2126 * c[0] + .7152 * c[1] + .0722 * c[2]; };
const cr = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + .05) / (y + .05); };
const paresAdmin = [
  ['barra-texto', 'barra', 'Logo, ítem activo y Salir en la barra', '4.5'], ['barra-texto', 'barra-2', 'Ítem activo y hover de la barra', '4.5'],
  ['barra-texto-2', 'barra', 'Ítems de navegación, rótulo “Backoffice”, usuario; rótulo del login', '4.5'],
  ['barra-texto-2', 'barra-2', 'Ítem en hover antes de aclararse', '4.5'],
  ['texto', 'rojo', 'Contador (verificaciones, consultas, parecidos)', '4.5'],
  ['rojo', 'barra', 'Marca del ítem activo (3 px) y filete del login', '3'], ['rojo', 'barra-2', 'Marca del ítem activo sobre su fondo', '3'],
  ['barra-foco', 'barra', 'Anillo de foco en la barra y el fondo del login', '3'], ['barra-foco', 'barra-2', 'Anillo de foco en el ítem activo', '3'],
  ['texto', 'area', 'Títulos y texto sobre el área de trabajo', '4.5'], ['texto-2', 'area', 'Migas, eyebrow, pie, nota sobre el área', '4.5'],
  ['azul', 'area', 'Enlaces sobre el área (migas en hover, avisos)', '4.5'],
  ['texto', 'fondo', 'Texto en tarjetas y tablas', '4.5'], ['texto-2', 'fondo', 'Dato secundario en tarjetas', '4.5'],
  ['texto-2', 'fondo-2', 'Encabezado de columnas, fieldset', '4.5'], ['texto', 'azul-suave', 'Fila en hover, término elegido', '4.5'],
  ['azul', 'fondo', 'Enlaces, códigos en tablas', '4.5'], ['azul-osc', 'azul-suave', 'Enlace en fila con hover, chip de acción pendiente', '4.5'],
  ['blanco', 'azul', 'Botón primario (Guardar, Ingresar)', '4.5'], ['blanco', 'digital', 'Botón crear o recuperar', '4.5'], ['blanco', 'error', 'Botón de baja y doble validación armada', '4.5'],
  ['error', 'fondo', 'Botón de borde rojo, “Zona de riesgo”, aviso de doble validación', '4.5'],
  ['digital', 'digital-suave', 'Chip ok (círculo)', '4.5'], ['duda', 'duda-suave', 'Chip atención (rombo, borde discontinuo), leyenda', '4.5'],
  ['error', 'error-suave', 'Chip error/baja/sin confirmar (triángulo), aviso de error', '4.5'], ['pedido', 'pedido-suave', 'Leyenda “Restringido”', '4.5'],
  ['duda', 'fondo', 'Candado de campo editado a mano', '4.5'], ['error', 'fondo', 'Año no confirmado (subrayado punteado)', '4.5'],
  ['texto', 'digital-suave', 'Texto en aviso ok', '4.5'], ['texto', 'error-suave', 'Texto en aviso de error y leyenda de baja', '4.5'],
  ['borde', 'fondo', 'Borde de controles (1.4.11)', '3'], ['borde', 'fondo-2', 'Borde de controles en fieldset', '3'], ['borde', 'area', 'Borde de controles sobre el área', '3'],
  ['azul', 'fondo', 'Anillo de foco en el área', '3'], ['azul', 'area', 'Anillo de foco sobre el área', '3'],
  ['duda', 'fondo', 'Barra de 4 px del campo bloqueado', '3'],
];
const pares = ADMIN ? paresAdmin : [
  ['texto', 'fondo', 'Títulos, texto, filas', '4.5'], ['texto', 'fondo-2', 'Texto en paneles (facetas, acciones, cita, pie)', '4.5'],
  ['texto', 'azul-suave', 'Texto en fila con hover y opción elegida', '4.5'], ['texto', 'resaltado', 'Término buscado (<mark>)', '4.5'],
  ['texto-2', 'fondo', 'Dato secundario', '4.5'], ['texto-2', 'fondo-2', 'Dato secundario en paneles y encabezado de columnas', '4.5'],
  ['texto-2', 'azul-suave', 'Dato secundario en fila con hover', '4.5'],
  ['azul', 'fondo', 'Enlaces y títulos de fila', '4.5'], ['azul', 'fondo-2', 'Enlaces en paneles', '4.5'], ['azul', 'resaltado', 'Término buscado dentro de un título-enlace', '4.5'],
  ['azul-osc', 'azul-suave', 'Título de fila en hover', '4.5'],
  ['blanco', 'azul', 'Botón primario, chip activo en hover, conteo de faceta', '4.5'], ['blanco', 'azul-osc', 'Botón primario en hover', '4.5'],
  ['blanco', 'texto', 'Barra del visor', '4.5'], ['blanco', 'visor', 'Zona del visor', '4.5'],
  ['digital', 'digital-suave', 'Badge Documento digital', '4.5'], ['pedido', 'pedido-suave', 'Badge Consulta a pedido', '4.5'],
  ['duda', 'duda-suave', 'Badge Año sin confirmar', '4.5'], ['duda', 'fondo', 'Año sin confirmar en texto', '4.5'], ['duda', 'azul-suave', 'Año sin confirmar en fila con hover', '4.5'],
  ['error', 'error-suave', 'Resumen de errores', '4.5'], ['error', 'fondo', 'Error junto al campo', '4.5'],
  ['borde', 'fondo', 'Borde de controles (1.4.11)', '3'], ['borde', 'fondo-2', 'Borde de controles en paneles', '3'],
  ['azul', 'fondo', 'Anillo de foco, checkbox, barra de avance', '3'], ['azul', 'azul-suave', 'Anillo de foco sobre fila con hover', '3'],
  ['blanco', 'visor', 'Anillo de foco en el visor', '3'],
  ['rojo', 'fondo', 'Cursor del buscador (decorativo, NO texto)', '—'],
];
console.log('| Texto / elemento | Fondo | Uso | Contraste | Mínimo AA |\n|---|---|---|---|---|');
for (const [a, b, u, m] of pares) { const v = cr(C[a], C[b]); const ok = m === '—' ? '' : (v >= parseFloat(m) ? ' ✔' : ' ✘');
  console.log(`| \`${a}\` ${C[a]} | \`${b}\` ${C[b]} | ${u} | ${v.toFixed(2).replace('.', ',')}:1${ok} | ${m.replace('.', ',')}${m === '—' ? '' : ':1'} |`); }
