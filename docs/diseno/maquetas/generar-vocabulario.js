#!/usr/bin/env node
/* Genera vocabulario-final.js a partir de la propuesta de Datos y catálogo
   (docs/datos/vocabulario-propuesta.csv). Las maquetas muestran los filtros con la lista final
   propuesta (38 tipos, 32 descriptores), no con los 108 términos sin curar de la base.

   Uso: node docs/diseno/maquetas/generar-vocabulario.js
   No toca la base: solo lee el CSV y escribe un .js para el navegador. */
'use strict';
const fs = require('fs');
const path = require('path');

const raiz = path.resolve(__dirname, '../../..');
const csv = fs.readFileSync(path.join(raiz, 'docs/datos/vocabulario-propuesta.csv'), 'utf8');

function parsear(texto) {
  const filas = []; let fila = [], campo = '', comillas = false;
  for (let i = 0; i < texto.length; i++) {
    const c = texto[i];
    if (comillas) {
      if (c === '"' && texto[i + 1] === '"') { campo += '"'; i++; }
      else if (c === '"') comillas = false;
      else campo += c;
    } else if (c === '"') comillas = true;
    else if (c === ',') { fila.push(campo); campo = ''; }
    else if (c === '\n') { fila.push(campo); filas.push(fila); fila = []; campo = ''; }
    else if (c !== '\r') campo += c;
  }
  if (campo || fila.length) { fila.push(campo); filas.push(fila); }
  return filas;
}
const [cab, ...filas] = parsear(csv);
const regs = filas.filter(f => f.length > 3).map(f => Object.fromEntries(cab.map((k, i) => [k, f[i]])));

// Grupos de la «Lista final propuesta» (vocabulario-propuesta.md)
const GRUPOS = {
  tipo: {
    'Escritura y obra': ['Guion', 'Obra de teatro', 'Descripción de personajes', 'Sinopsis argumental', 'Investigación de proyecto', 'Nota/Manuscrito', 'Cuento', 'Novela', 'Poesía', 'Texto en verso', 'Canción'],
    'Prensa': ['Nota de prensa', 'Clipping de prensa', 'Revista', 'Periódico', 'Reseña', 'Entrevista', 'Comunicado de prensa', 'Cable de agencia de noticias'],
    'Correspondencia': ['Carta', 'Tarjeta', 'Sobre', 'Correo electrónico'],
    'Producción y gestión': ['Asistencia a la producción', 'Programa', 'Bordereau', 'Contrato/Acuerdo', 'Registro/Certificado', 'Ticket/Comprobante', 'Currículum', 'Encuesta de público', 'Referencia de época'],
    'Soportes y objetos': ['Fotografía', 'Archivo de audio', 'Archivo digital de audio', 'Archivo digital de imagen', 'Archivo digital de texto', 'Objeto'],
  },
  descriptor: {
    'Cine': ['Cine', 'Película', 'Película histórica', 'Documental', 'Dibujo animado', 'Filmación', 'Industria cinematográfica', 'Actualidades cinematográficas', 'Historia del cine', 'Festival de cine', 'Asistencia al cine'],
    'Televisión': ['Televisión', 'Película de televisión', 'Serie de televisión'],
    'Teatro y artes escénicas': ['Teatro', 'Teatro contemporáneo', 'Teatro itinerante', 'Teatro nacional', 'Teatro popular', 'Artes escénicas', 'Producción teatral', 'Gestión teatral', 'Compañía teatral', 'Monólogo', 'Comedia musical', 'Danza'],
    'Música': ['Música', 'Himno nacional'],
    'Literatura y prensa': ['Literatura', 'Periodismo'],
    'Transversales': ['Guion', 'Premios/Nominaciones/Menciones'],
  },
};

const voc = r => (r.tipo === 'tipo_documento' ? 'tipo' : 'descriptor');
const porId = { tipo: new Map(), descriptor: new Map() };
regs.forEach(r => porId[voc(r)].set(+r.id, r));

// Términos nuevos que propone Datos y catálogo (ids propios de la maqueta, > 900)
const NUEVOS = { tipo: { 'Encuesta de público': 903 }, descriptor: { 'Periodismo': 901, 'Serie de televisión': 902 } };

// nombre final de cada término que sobrevive (aprobar o renombrar)
const final = { tipo: new Map(), descriptor: new Map() };
regs.forEach(r => {
  if (r.accion === 'aprobar') final[voc(r)].set(+r.id, r.termino_actual);
  if (r.accion === 'renombrar') final[voc(r)].set(+r.id, r.destino_o_nuevo_nombre);
});
Object.entries(NUEVOS).forEach(([v, m]) => Object.entries(m).forEach(([n, id]) => final[v].set(id, n)));

// mapa: id actual → lista de destinos [{v:'tipo'|'descriptor', id}]
const mapa = { tipo: {}, descriptor: {} };
regs.forEach(r => {
  const v = voc(r), id = +r.id; let dest = [];
  if (r.accion === 'aprobar' || r.accion === 'renombrar') dest = [{ v, id }];
  else if (r.accion === 'fusionar') {
    const m = r.destino_o_nuevo_nombre.match(/#(\d+)/);
    dest = [{ v, id: +m[1] }];
  }
  mapa[v][id] = dest;
});
// «revisar» con destino claro (movimientos entre vocabularios y términos nuevos)
mapa.tipo[61] = [{ v: 'descriptor', id: 63 }];           // Televisión → descriptor Televisión
mapa.tipo[98] = [{ v: 'descriptor', id: 902 }];          // Serie de TV → descriptor Serie de televisión
mapa.tipo[107] = [{ v: 'tipo', id: 903 }];               // testeo → Encuesta de público
mapa.descriptor[11] = [{ v: 'tipo', id: 13 }];           // Carta → tipo Carta
mapa.descriptor[27] = [{ v: 'tipo', id: 24 }];           // Comunicado de prensa → tipo
mapa.descriptor[39] = [{ v: 'tipo', id: 38 }];           // Fotografía → tipo
mapa.descriptor[53] = [{ v: 'tipo', id: 58 }];           // Referencia de época → tipo
mapa.descriptor[69] = 'novelas';                         // se divide por registro (abajo)
// El resto de «revisar» (Prensa, Adaptación, Largometraje, «g», «s», «.»…) se quita.

const NOVELAS = {
  literatura: 'C11R5 C11R16 C11R18 C15R13 C15R23 C16R6 C22R1 C22R3 C60R19 C67R1 C67R2 C60R11'.split(' '),
  periodismo: 'C11R9 C15R14 C15R16 C22R4 C22R5 C22R9 C22R10 C26R16 C46R4 C46R5 C46R9 C46R11 C46R15 C53R23 C54R1 C70R12'.split(' '),
};

function lista(v) {
  const out = [];
  Object.entries(GRUPOS[v]).forEach(([grupo, nombres]) => nombres.forEach(n => {
    const ids = [...final[v]].filter(([, nom]) => nom === n).map(([id]) => id);
    if (!ids.length) throw new Error(`No encuentro «${n}» en ${v}`);
    out.push({ id: ids[0], nombre: n, grupo });
  }));
  return out;
}
const salida = {
  origen: 'docs/datos/vocabulario-propuesta.csv',
  tipos: lista('tipo'),
  descriptores: lista('descriptor'),
  mapa,
  novelas: NOVELAS,
};
const js = `/* Vocabulario final propuesto por Datos y catálogo, para las maquetas. Generado por generar-vocabulario.js.
   No editar a mano. Es una propuesta: la base todavía tiene los 108 términos sin curar. */
window.AIDA_VOCAB = ${JSON.stringify(salida)};
`;
fs.writeFileSync(path.join(__dirname, 'vocabulario-final.js'), js);
console.log(`vocabulario-final.js: ${salida.tipos.length} tipos, ${salida.descriptores.length} descriptores`);
