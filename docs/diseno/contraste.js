#!/usr/bin/env node
/* Mide los contrastes de docs/diseno/sistema.md §2 a partir de tokens.css (WCAG 2.1, luminancia relativa).
   Uso: node docs/diseno/contraste.js */
const fs = require('fs');
const t = fs.readFileSync(require('path').join(__dirname, '../../public/sitio/tokens.css'), 'utf8');
const C = {};
for (const m of t.matchAll(/--c-([\w-]+):\s*(#[0-9A-Fa-f]{6})/g)) C[m[1]] = m[2].toUpperCase();
C['gris-visor'] = '#A8957E';
// Grano sobre la pared azul: ruido negro al 7 % como máximo (peor caso para texto oscuro; para el blanco solo suma contraste)
const mezcla = (a, b, k) => '#' + [1, 3, 5].map(i => Math.round(parseInt(a.substr(i, 2), 16) * (1 - k) + parseInt(b.substr(i, 2), 16) * k).toString(16).padStart(2, '0')).join('').toUpperCase();
C['azul-con-grano'] = mezcla(C['azul'], '#000000', .07);
const lum = h => { const c = h.match(/\w\w/g).map(x => parseInt(x, 16) / 255).map(v => v <= .03928 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4); return .2126 * c[0] + .7152 * c[1] + .0722 * c[2]; };
const cr = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + .05) / (y + .05); };
const pares = [
  ['tinta', 'crema', 'Títulos y texto de interfaz', '4.5'], ['texto', 'crema', 'Lectura larga (descripción, resumen, «Sobre»)', '4.5'],
  ['texto', 'blanco', 'Lectura en tarjetas', '4.5'], ['texto-2', 'crema', 'Texto secundario', '4.5'], ['texto-2', 'blanco', 'Extracto en tarjetas', '4.5'],
  ['apagado', 'crema', 'Metadatos, ayudas', '4.5'], ['apagado', 'blanco', 'Datos a máquina en tarjetas', '4.5'], ['apagado', 'crema-2', 'Metadatos sobre crema-2', '4.5'],
  ['enlace', 'crema', 'Enlaces', '4.5'], ['enlace', 'blanco', 'Enlaces en tarjetas y formulario', '4.5'], ['enlace', 'crema-2', 'Enlaces sobre crema-2', '4.5'],
  ['enlace-hover', 'crema', 'Enlace hover', '4.5'], ['rotulo', 'crema', 'Rótulos a máquina (kicker, dt de la ficha)', '4.5'], ['rotulo', 'blanco', 'Rótulo «Caja» en tarjeta', '4.5'],
  ['blanco', 'azul', 'Texto sobre la pared azul; botón primario', '4.5'], ['blanco', 'azul-con-grano', 'Texto sobre azul con grano (peor caso)', '4.5'],
  ['rotulo-claro', 'azul', 'Rótulos y «de Aída» sobre azul', '4.5'], ['blanco', 'tinta', 'Hover del botón primario', '4.5'],
  ['tinta', 'rojo', 'Botón sobre azul (Buscar del hero); etapas rojas', '4.5'], ['crema', 'tinta', 'Pie, etapa tinta, barra del visor', '4.5'],
  ['blanco', 'azul-osc', 'Visor', '4.5'], ['digital', 'digital-suave', 'Badge Documento digital', '4.5'], ['pedido', 'pedido-suave', 'Badge Consulta a pedido', '4.5'],
  ['duda', 'duda-suave', 'Badge Año sin confirmar', '4.5'], ['duda', 'crema', 'Año sin confirmar en texto', '4.5'], ['duda', 'blanco', 'Año sin confirmar en tarjeta', '4.5'],
  ['error', 'error-suave', 'Resumen de errores', '4.5'], ['error', 'blanco', 'Error junto al campo', '4.5'],
  ['borde', 'crema', 'Borde de controles (1.4.11)', '3'], ['borde', 'blanco', 'Borde de campos', '3'], ['tinta', 'crema', 'Anillo de foco y bordes de tarjeta', '3'],
  ['blanco', 'azul', 'Anillo de foco sobre azul', '3'], ['azul', 'blanco', 'Barra de avance y checkbox', '3'],
  ['rojo', 'crema', 'Rojo de marca como texto (NO usar)', '—'], ['rojo', 'azul', 'Cursor y encastre rojo sobre azul (decorativo)', '—'],
];
console.log('| Texto / elemento | Fondo | Uso | Contraste | Mínimo AA |\n|---|---|---|---|---|');
for (const [a, b, u, m] of pares) { const v = cr(C[a], C[b]); const ok = m === '—' ? '' : (v >= parseFloat(m) ? ' ✔' : ' ✘');
  console.log(`| \`${a}\` ${C[a]} | \`${b}\` ${C[b]} | ${u} | ${v.toFixed(2).replace('.', ',')}:1${ok} | ${m.replace('.', ',')}${m === '—' ? '' : ':1'} |`); }
