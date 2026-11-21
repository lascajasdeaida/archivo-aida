# Propuesta de curaduría de vocabularios

Base `aida`, 15/11/2026. 108 términos, todos en estado `propuesto`: 57 tipos de documento y 51 descriptores.
El detalle término por término, con id, cantidad de registros y motivo, está en
[`vocabulario-propuesta.csv`](vocabulario-propuesta.csv). Es una propuesta: no se modificó la base.

## Resumen

| Acción | Tipos | Descriptores | Total |
|---|---|---|---|
| aprobar | 32 | 25 | 57 |
| renombrar | 5 | 5 | 10 |
| fusionar | 13 | 10 | 23 |
| revisar | 7 | 11 | 18 |
| **Total** | **57** | **51** | **108** |

Resultado: **70 términos**, 38 tipos y 32 descriptores (lista abajo). Hay 3 términos nuevos:
los descriptores «Periodismo» y «Serie de televisión» y el tipo «Encuesta de público».

## Criterios

- **Variantes del mismo término** (tildes, mayúsculas, punto final, plural, «?»): se fusionan en la forma correcta.
  El destino es el término con más usos, salvo que tenga un error. En ese caso se renombra el destino antes de fusionar.
- **Grafía**: RAE. «guion» sin tilde, «currículum» con tilde, «correo electrónico» en lugar de «mail».
  Los tipos van en singular («Contrato/Acuerdo», «Referencia de época», «Bordereau»).
- **Tipo de documento** = qué es la pieza (carta, guion, recorte). **Descriptor** = de qué área o tema trata (cine, teatro, televisión).
  Un término cargado en el vocabulario equivocado se mueve, no se duplica.
- **Basura** (letras sueltas, texto pegado): se buscó en qué registro está y qué querían poner. Queda como «revisar» con una sugerencia.
- **Granularidad**: los descriptores de tesauro con 1 o 2 usos que no sirven como filtro se fusionan con su término general
  (Estilo musical, Forma musical, Orquesta… → Música).

## Hallazgos principales

1. **Términos en el vocabulario equivocado.** Son los cambios con más registros afectados:
   - Tipo **Televisión** (146 registros) → es un medio: pasa al descriptor Televisión. 142 registros suman el descriptor.
     Tres quedan sin tipo y se sugiere uno: C15R8 Guion, C26R39 Sinopsis argumental, C26R43 Nota/Manuscrito.
   - Tipo **Serie de TV** (33, cajas 34 y 35) → formato: pasa a un descriptor nuevo, «Serie de televisión». Todos tienen el tipo Guion.
   - Descriptores **Carta** (65), **Comunicado de prensa** (26), **Fotografía** (15) y **Referencia de época** (2) → son tipos.
     Se suma el tipo donde falta (19, 20, 3 y 2 registros) y se quita el descriptor.
   - Descriptor **Obra de teatro** (90) → duplica el tipo «Obra de teatro» (77 registros tienen los dos).
     Se fusiona en el descriptor Teatro (43 registros lo suman).
   - Tipo **Prensa** (64) → es genérico. Se quita en los 40 registros que ya tienen un tipo de prensa específico.
     Los otros 24 necesitan uno, que se elige según el resumen.
2. **«Novelas. Noticias.»** (34 registros) junta dos temas. Se divide en dos descriptores, Literatura y Periodismo (ver abajo).
3. **Basura:** «.» (C33R4, C38R1) desaparece sola en la próxima importación, porque el importador ya ignora las celdas que solo tienen signos.
   «g» y «s» (C6R10, Descriptor 3 y 4) no tienen una intención que se pueda deducir: se quitan.
   La frase «Aída Bortnik/ Sergio Renán (sobre "Alrededor de la jaula"…)» (C39R7) es el texto del Autor pegado en Tipo 2: se quita.
   «guio» (C26R20) es «Guion» cortado.
   «propuesta» (C26R41) es una propuesta de notas para prensa: se sugiere Periodismo.
   «testeo» (C53R13) son 30 encuestas de público sobre «Caballos salvajes»: se propone el tipo «Encuesta de público».
4. **Variantes:** «Asistencia a la producción.» con punto (68 registros) y sin punto (44).
   También Novela/Novela., Cuento/Cuento?, Bordereaux/Borderaux, Cable de Agencia/Cable de agencia de noticias,
   Reseña/Reseña/Revista/ReseñaRevista, Referencia(s) de época, Película de televisión/Película de televisión?????,
   Guion/Guión del cine/guio y Ticket/Ticket/Comprobante.
5. **Guion / Guión:** la base ya los toma como un solo término, porque compara sin tildes («Guion», 545 como tipo y 485 como descriptor).
   Queda «Guión del cine» (6), que se fusiona en el descriptor Guion.

### División de «Novelas. Noticias.»

| Destino | Registros |
|---|---|
| Literatura (tienen tipo Novela, Cuento o Poesía) | C11R5, C11R16, C11R18, C15R13, C15R23, C16R6, C22R1, C22R3, C60R19, C67R1, C67R2 |
| Periodismo (tienen un tipo de prensa) | C11R9, C15R14, C15R16, C22R4, C22R5, C22R9, C22R10, C26R16, C46R4, C46R5, C46R9, C46R11, C46R15, C53R23, C54R1, C70R12 |
| Revisar a mano | C7R12, C11R1, C11R20, C19R3, C26R27, C60R11 (relato de Millás: Literatura), C68R2 (boletín de una veterinaria) |

## Lista final propuesta

### Tipos de documento (38)

| Grupo | Términos |
|---|---|
| Escritura y obra | Guion · Obra de teatro · Descripción de personajes · Sinopsis argumental · Investigación de proyecto · Nota/Manuscrito · Cuento · Novela · Poesía · Texto en verso · Canción |
| Prensa | Nota de prensa · Clipping de prensa · Revista · Periódico · Reseña · Entrevista · Comunicado de prensa · Cable de agencia de noticias |
| Correspondencia | Carta · Tarjeta · Sobre · Correo electrónico |
| Producción y gestión | Asistencia a la producción · Programa · Bordereau · Contrato/Acuerdo · Registro/Certificado · Ticket/Comprobante · Currículum · Encuesta de público · Referencia de época |
| Soportes y objetos | Fotografía · Archivo de audio · Archivo digital de audio · Archivo digital de imagen · Archivo digital de texto · Objeto |

### Descriptores (32)

| Grupo | Términos |
|---|---|
| Cine | Cine · Película · Película histórica · Documental · Dibujo animado · Filmación · Industria cinematográfica · Actualidades cinematográficas · Historia del cine · Festival de cine · Asistencia al cine |
| Televisión | Televisión · Película de televisión · Serie de televisión |
| Teatro y artes escénicas | Teatro · Teatro contemporáneo · Teatro itinerante · Teatro nacional · Teatro popular · Artes escénicas · Producción teatral · Gestión teatral · Compañía teatral · Monólogo · Comedia musical · Danza |
| Música | Música · Himno nacional |
| Literatura y prensa | Literatura · Periodismo |
| Transversales | Guion · Premios/Nominaciones/Menciones |

Los grupos sirven para ordenar los filtros del front. Hoy no existen en la base: hace falta una columna `grupo` en `vocabulario`, o una tabla aparte.
Esa decisión le toca a Base de datos.

## Cómo aplicarla y qué no resuelve el backoffice de hoy

Orden sugerido: 1) renombrar los destinos, 2) fusionar, 3) aprobar, 4) resolver los «revisar».

Revisé `vocab_accion` (`src/Web/Rutas.php`) y el importador (`src/Importador.php`). Hay cuatro límites:

1. **No se puede mover un término al otro vocabulario.** La fusión no controla que origen y destino sean del mismo vocabulario.
   Si se fusiona un descriptor en un tipo, el importador lo carga como descriptor con el id del tipo.
   Cuando el registro ya tiene ese tipo, falla por clave duplicada en `registro_vocabulario`.
   Los movimientos de la sección 1 de hallazgos (Televisión, Carta, Comunicado de prensa, etc.) necesitan una acción nueva, «mover a…»,
   y una regla en el importador para que el Excel siga mandando «Televisión» en Tipo sin volver a crearlo.
2. **«Quitar» un término de un registro bloquea todo el conjunto.** Hoy la única forma es editar los tipos o descriptores del registro.
   Eso bloquea `tipos` o `descriptores` completos y el Excel deja de actualizarlos.
   Para la basura («g», «s», la frase de C39R7) convendría un estado `descartado`: el importador lo ignoraría sin bloquear el registro.
3. **Renombrar no cambia la `clave`.** Si después de renombrar «Bordereaux» a «Bordereau» el equipo escribe «Bordereau» en el Excel, entra como término propuesto nuevo.
   Para cambios de grafía conviene crear el término nuevo y fusionar en él el viejo.
   Otra opción es que renombrar agregue la clave vieja como alias.
4. **La fusión y el renombre no quedan en `auditoria`** y no se pueden deshacer. `decisiones.md` dice que todo cambio queda en el historial.
Los cuatro son para Programación. Quedan anotados como preguntas al PM: no cambié reglas de `decisiones.md`.
