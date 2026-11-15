#!/usr/bin/env node
/* Mide los contrastes de docs/diseno/sistema.md §2 a partir de tokens.css. Uso: node docs/diseno/contraste.js */
const fs=require('fs');const t=fs.readFileSync(require('path').join(__dirname, 'tokens.css'),'utf8');
const C={};for(const m of t.matchAll(/--c-([\w-]+):\s*(#[0-9A-Fa-f]{6})/g))C[m[1]]=m[2].toUpperCase();
C['gris-visor']='#A8957E';
const lum=h=>{const c=h.match(/\w\w/g).map(x=>parseInt(x,16)/255).map(v=>v<=.03928?v/12.92:((v+.055)/1.055)**2.4);return .2126*c[0]+.7152*c[1]+.0722*c[2];};
const cr=(a,b)=>{const[x,y]=[lum(a),lum(b)].sort((p,q)=>q-p);return (x+.05)/(y+.05);};
const pares=[
['tinta','papel','Texto principal','4.5'],['tinta','papel-2','Texto en tarjetas','4.5'],['tinta','pergamino','Texto en bloque Documento / cajas','4.5'],
['tinta-2','papel','Texto secundario','4.5'],['tinta-2','papel-2','Extracto en tarjetas','4.5'],['tinta-2','sepia','Texto sobre sepia (pestaña, deshabilitado)','4.5'],
['apagado','papel','Metadatos, ayudas','4.5'],['apagado','papel-2','Datos de la tarjeta','4.5'],['apagado','pergamino','Datos de caja','4.5'],['apagado','sepia','Botón deshabilitado','—'],
['naranja-800','papel','Enlaces','4.5'],['naranja-800','papel-2','Enlaces en tarjetas','4.5'],['naranja-800','pergamino','Enlaces en bloque Documento','4.5'],
['blanco','naranja-700','Botón primario','4.5'],['blanco','naranja-900','Botón primario hover','4.5'],['tinta','naranja-suave','Chip activo, opción elegida','4.5'],['naranja-900','papel','× del chip','3'],
['crema','marron','Texto en cabecera/hero/pie','4.5'],['ambar','marron','«de Aída», cifras, nav activa','4.5'],['crema','marron-2','Visor','4.5'],['ambar','marron-2','Foco sobre oscuro (visor)','3'],
['digital','digital-suave','Badge Documento digital','4.5'],['pedido','pedido-suave','Badge Consulta a pedido','4.5'],['duda','duda-suave','Badge Año sin confirmar','4.5'],['duda','papel','Año sin confirmar en texto','4.5'],
['error','error-suave','Resumen de errores','4.5'],['error','papel-2','Error junto al campo','4.5'],
['borde','papel','Borde de controles (1.4.11)','3'],['borde','blanco','Borde de campos','3'],['borde','papel-2','Borde de controles en formulario','3'],
['foco','papel','Anillo de foco','3'],['naranja-700','papel','Borde activo, barra de avance','3'],['digital','blanco','Barra de avance (relleno)','3'],
['naranja','papel','Naranja de marca como texto (NO usar)','—'],['blanco','naranja','Texto blanco sobre naranja de marca (NO usar)','—'],['gris-visor','marron','Botón del visor deshabilitado','—'],
];
console.log('| Texto / elemento | Fondo | Uso | Contraste | Mínimo AA |\n|---|---|---|---|---|');
for(const [a,b,u,m] of pares){const v=cr(C[a],C[b]);const ok=m==='—'?'':(v>=parseFloat(m)?' ✔':' ✘');console.log(`| \`${a}\` ${C[a]} | \`${b}\` ${C[b]} | ${u} | ${v.toFixed(2).replace('.',',')}:1${ok} | ${m.replace('.',',')}${m==='—'?'':':1'} |`);}
