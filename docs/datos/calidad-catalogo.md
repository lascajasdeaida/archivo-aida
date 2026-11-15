# Calidad del catálogo: otras inconsistencias

Base `aida`, 15/11/2026: 70 cajas, 1098 registros. Primero van las conclusiones, revisadas a mano.
Después va el informe automático completo, que genera `php bin/auditar-catalogo.php` (solo lectura).
Para actualizarlo después de una importación, correr `php bin/auditar-catalogo.php --salida=informe.md`.

## Conclusiones

### Nombres de cajas: cuáles son la misma obra

| Cajas | Veredicto | Evidencia | Propuesta |
|---|---|---|---|
| 3 «Noticia de un secuestro» / 5 «Noticias de un secuetro» | **Misma obra**, con error de tipeo | El resumen de la caja 3 empieza «Cajas 3 y 5 Noticia de un Secuestro (2008)». Las dos pestañas tienen la sigla NDUS. Los registros de las dos son de 2008–2009 y se titulan «Noticia de un secuestro». | Caja 5 → «Noticia de un secuestro». Corregir también el título de C3R1 («Noticias de…»). |
| 23 «Cenizas del Paraíso» / 24 «Cenizas del Paraíso II» | **Misma obra en dos cajas**: no unificar | El resumen de la 24 dice «Cenizas del Paraíso (Cajas 23 y 24)». Las pestañas son «…Paraíso I» y «…Paraíso II». | Caja 23 → «Cenizas del Paraíso I», para que se lea el par. El resumen de la caja 23 dice solo «1997»: copiar el de la 24. |
| 8 «Why Me?» / 12 «La Vìctima» | **Misma obra** con dos títulos | El resumen de la caja 8 dice «Cajas 8 y 12 "La Víctima o Why me?"». La pestaña 8 es «La Víctima (Why Me)». Las dos cajas son de 1974 y de Bortnik y Soriano. | Caja 8 → «La víctima (Why Me?)». Caja 12 → «La víctima», con la tilde bien: hoy tiene «ì». |
| 9 «Azucena Villaflor» / 13 «Azucena» | **Misma obra** | Las dos cajas tienen el mismo resumen: «Las Cajas 9 y 13 dedicadas a "Azucena Villaflor"». | Caja 13 → «Azucena Villaflor». |
| 30, 31, 34 «Éramos tan jóvenes (España)» / 35 «"Éramos tan jóvenes" (Argentina)» | **Versiones distintas** de la serie: no unificar | Las cajas de España son de 1978–1982 y la de Argentina, de 1986. El resumen de la 35 dice «RESUMEN EN CAJA 30». | Mantener la aclaración del país. Sacar las comillas de la 35. |
| 45, 48, 49, 51, 59 «Fade Out …» | **Misma obra**, cinco cajas por etapa | El resumen dice «Fade Out (abarca cinco cajas…)». | Mantener los nombres: la etapa distingue cada caja. Unificar la mayúscula de «Fade out 91/92/93». |
| 17, 19, 29, 32 «Vientos de Agua» | Misma obra, mismo nombre | — | Nada que hacer. |
| 11 «Proyectos» / 15 «Proyectos» | **No son la misma obra**: el nombre es genérico | La pestaña 11 es «Projects» y su resumen habla de proyectos para el exterior: «Muerte en Madagascar», «Open Eyes», «Hearts». La 15 son otros proyectos, de 1962 a 2009. | Diferenciarlas, por ejemplo «Proyectos (Projects)» para la 11. Decide el PM con el equipo. |
| 58 «Forty Present» / 68 «Forty Presents (Notas)» | Probablemente el mismo proyecto | El resumen de la 58 se titula «"Forty Presents", Buenos Aires 1989». La 68 tiene notas sueltas de 1974–1988. | Caja 58 → «Forty Presents». Que el equipo confirme el vínculo de la 68. |
| 52 «Revistas y Diarios 1947/1948 Pobre Mariposa…» / 55 «Pobre Mariposa II» | Relacionadas | La 52 es material de época (revistas de 1947–1949) para «Pobre mariposa». No hay ninguna caja «Pobre Mariposa I». | Preguntar si la 52 es la «I». |
| 39 «Crecer do Golpe» | Error de tipeo | La pestaña y los títulos dicen «Crecer de Golpe». | → «Crecer de golpe». |

Otras grafías: «TANGO FEROZ», «LA ISLA», «VOZ Y CUERPO» y «ZSHEREZADE» están en mayúsculas. El resumen de la caja 14 escribe «Zscherezade»: la grafía hay que confirmarla.
La caja 21 dice «Te acordás?»: falta el «¿».
La caja 22 dice «Beattle»: el registro C22R5 dice «Beatles».
La caja 50 dice «Felíz»: va «Feliz».
La caja 46 dice «sin nombre»: según su resumen es la única caja sin rótulo.

**Ojo con la pantalla «Unificar nombres parecidos» del backoffice** (`gruposParecidos` en `src/Web/Rutas.php`).
Con estos datos propone dos cosas que están mal:
- unificar la 23 y la 24 como «Cenizas del Paraíso», con lo que se pierde el «II»;
- unificar las cuatro «Éramos tan jóvenes» como «(España)», con lo que la caja argentina pasa a decir España.

Tampoco detecta las 8 y 12 ni las 9 y 13. No hay que aplicar esos grupos sin mirar. Para Programación: tratar el numeral final y el paréntesis como diferencias, igual que hace el auditor.

### Registros sin título ni tipo

Hay **5 sin título ni tipo**. En estos la ficha queda sin encabezado:
- C2R30, C2R31 y C2R32 son las traducciones de los diálogos de «La Historia Oficial». Tipo sugerido: Guion.
- C10R11 es un recorte de Página 12. Tipo sugerido: Clipping de prensa.
- C25R8 son unas 200 hojas sueltas. Tipo sugerido: Nota/Manuscrito.

Otros **3 tienen título pero no tipo**:
- C4R5, solicitud de depósito. Tipo sugerido: Registro/Certificado.
- C10R12, guion de «Wild Tango». Tipo sugerido: Guion.
- C15R9, «Mujeres» a máquina. Tipo sugerido: Guion.

Además, 124 registros no tienen descriptores y 183 no tienen título. Los que no tienen título muestran el tipo en el encabezado.

### Años y fechas

- **14 fechas leídas al revés** en las cajas 3, 29 y 32. El día del documento coincide con el año: «05/03/1930» en C32R5, cuyo año es 2005.
  La fecha se tipeó como aa/mm/dd («05/03/30») y Excel la tomó como dd/mm/aa. La fecha real sería 30/03/2005.
  Hay que corregirlas en la planilla, o tipearlas como texto. La lista está en el apartado 3.a del informe.
- Otros **25 registros** tienen un año que no coincide con la fecha del documento.
  En algunos es correcto, porque es material de época guardado para un proyecto posterior (C3R7 y C3R8 son de 1990 y el proyecto es de 2008).
  En otros parece un error: C10R1 tiene 1993 y su fecha es «30 marzo 1991»; C16R3 tiene 1997 y su fecha es «1981».
- **102 registros** no tienen un año que se pueda filtrar:
  - 64 traen rangos («1981/82», «2004/ 2005»);
  - 8 traen un año con duda («1972?», «1989???»);
  - 7 traen una fecha completa en la columna Año;
  - 20 dicen «sin fecha» o solo signos;
  - 3 traen texto.

  Para el PM: ¿el importador debería tomar el año en los casos de un solo año con duda, marcándolo como aproximado?
  ¿Y el primer año de los rangos? Hoy esos registros no aparecen en el filtro por año.
- La caja 52 tiene años de 1941 a 1949 (C52R2 es de 1941). Es material de época y parece correcto.

### Autores

- Aída aparece de **9 formas**:
  - «Aída Bortnik» (448 registros);
  - «AB» (93);
  - «Aida Bortnik» sin tilde (65);
  - «Aída Bortink» (21, sobre todo en las cajas 5, 6 y 21);
  - «Aída Bornik» (13, en la caja 41);
  - «Aida Bortnik Ferreira» (8, en la caja 14: hay que confirmar el apellido);
  - «Aída» (3);
  - «Bortnik Aída» (1);
  - «Bortnik» (1).

  Forma de autoridad propuesta: **Aída Bortnik**. Se puede normalizar al mostrar o al buscar sin tocar el dato.
  Si se corrige en la base, el campo queda bloqueado para el Excel: conviene que lo corrija el equipo en la planilla.
- **55 registros** tienen en Autor una oración en lugar de nombres. Por ejemplo, en 34 registros de la caja 17:
  «El equipo de guionistas de la serie estuvo conformado por…». Otro caso, en la caja 20: «Los guionistas de La Isla fueron…».
  Propuesta: dejar en Autor solo los nombres, separados por «; », y pasar la oración al resumen.
- En cartas, el autor está escrito «De X A: Aída Bortnik» (C49R7–R9). El autor es el remitente.

### Para el equipo de digitalización

Corregir en la planilla las fechas aa/mm/dd, los nombres de las cajas 5, 12 y 39, la columna Autor de las cajas 17 y 20, y la letra suelta de C6R10.
Así la próxima importación ya llega limpia y no hace falta bloquear campos en la base.

---

## Informe automático

Generado el 15/11/2026 18:26 con `bin/auditar-catalogo.php` sobre la base `aida`. Solo lectura: no se modificó nada.

Cajas: 70 · Registros: 1098 · Términos de vocabulario: 108

### Resumen

| Indicador | Cantidad |
|---|---|
| Pares de cajas con nombre parecido | 6 |
| Cajas con nombre distinto de la pestaña | 7 |
| Cajas sin resumen | 29 |
| Registros sin título ni tipo | 5 |
| Registros sin tipo | 8 |
| Registros sin descriptores | 124 |
| Títulos con grafías dispares en la misma caja | 23 |
| Registros con año no filtrable | 102 |
| Año distinto de la fecha del documento | 39 |
| Fechas leídas al revés (aa/mm/dd) | 14 |
| Formas distintas de «Aída Bortnik» | 9 |
| Registros con autor descriptivo | 55 |
| Términos propuestos sin curar | 108 |
| Grupos de variantes en vocabularios | 7 |
| Términos sospechosos | 14 |

### 1. Cajas


#### 1.a Nombres de cajas parecidos

Pares de cajas con nombres iguales o parecidos, agrupados por lo que conviene hacer.
| Caja | Caja | Diagnóstico | Evidencia en el resumen |
|---|---|---|---|
| Caja 3 «Noticia de un secuestro» | Caja 5 «Noticias de un secuetro» | Difieren en una o dos letras: parece error de tipeo de la misma obra. Unificar la grafía. | el resumen de la caja 3 las presenta juntas |
| Caja 23 «Cenizas del Paraíso» | Caja 24 «Cenizas del Paraíso II» | Misma obra en varias cajas numeradas (I, II…): **no unificar**, el numeral distingue las cajas. | el resumen de la caja 24 las presenta juntas |
| Caja 49 «Fade out 91/92/93» | Caja 59 «Fade Out 95/96/97» | Misma obra en varias cajas numeradas (I, II…): **no unificar**, el numeral distingue las cajas. | — |
| Caja 30 «Éramos tan jóvenes (España)» | Caja 35 «"Éramos tan jóvenes" (Argentina)» | Mismo título con aclaración distinta entre paréntesis: probablemente **versiones distintas**. No unificar sin revisar. | — |
| Caja 31 «Éramos tan jóvenes (España)» | Caja 35 «"Éramos tan jóvenes" (Argentina)» | Mismo título con aclaración distinta entre paréntesis: probablemente **versiones distintas**. No unificar sin revisar. | — |
| Caja 34 «Éramos tan jóvenes (España)» | Caja 35 «"Éramos tan jóvenes" (Argentina)» | Mismo título con aclaración distinta entre paréntesis: probablemente **versiones distintas**. No unificar sin revisar. | — |

Cajas con el mismo nombre exacto (varias cajas de una misma obra; no hay nada que unificar): «Proyectos» (cajas 11, 15); «Vientos de Agua» (cajas 17, 19, 29, 32); «Éramos tan jóvenes (España)» (cajas 30, 31, 34).

#### 1.b Cajas que el resumen presenta juntas con nombres distintos

| Cajas | Nombres | Dónde lo dice |
|---|---|---|
| Cajas 3 y 5 | «Noticia de un secuestro» / «Noticias de un secuetro» | resumen de la caja 3 |
| Cajas 8 y 12 | «Why Me?» / «La Vìctima» | resumen de la caja 8 |
| Cajas 9 y 13 | «Azucena Villaflor» / «Azucena» | resumen de la caja 13 |
| Cajas 23 y 24 | «Cenizas del Paraíso» / «Cenizas del Paraíso II» | resumen de la caja 24 |

#### 1.c Nombre de la caja distinto del título de su pestaña

La pestaña del Excel se llama «Caja N Nombre SIGLA». Si el nombre de la columna B no coincide con el de la pestaña, uno de los dos tiene un error o la obra tiene dos títulos.
| Caja | Nombre (col. B) | Pestaña |
|---|---|---|
| Caja 8 | «Why Me?» | «Caja 8 La Víctima (Why Me) PM» |
| Caja 11 | «Proyectos» | «Caja 11 Projects GTF» |
| Caja 26 | «Cavar un foso, Solo pienso en..., Nosotras» | «Caja 26 Cavar un, Solo pienso e» |
| Caja 33 | «Celos. El jardín de los cerezos. Drácula. La dama de las ca…» | «Caja 33 Celos EJDLC Drácula LDD» |
| Caja 52 | «Revistas y Diarios 1947/ 1948 Pobre Mariposa - Diario Clari…» | «Caja 52 Rev. y Diar. 4748 PM, C» |
| Caja 61 | «Sebastián y su amigo. Ellos dos y Arcadia. Julia y Flia» | «Caja 61 S y su a. Ellos dos y A» |
| Caja 64 | «Pero estoy vivo, Los dos, No quiero resignarme» | «Caja 64 PEV Los Dos NQR MTITP» |

#### 1.d Grafía de los nombres de caja

| Caja | Nombre | Observación |
|---|---|---|
| Caja 8 | «Why Me?» | signo de pregunta sin abrir (¿) |
| Caja 10 | «TANGO FEROZ» | todo en mayúsculas |
| Caja 12 | «La Vìctima» | tilde invertida (ì, è…) |
| Caja 14 | «ZSHEREZADE» | todo en mayúsculas |
| Caja 20 | «LA ISLA» | todo en mayúsculas |
| Caja 21 | «Te acordás? Cuentitos y Ensayos» | signo de pregunta sin abrir (¿) |
| Caja 35 | «"Éramos tan jóvenes" (Argentina)» | comillas en el nombre |
| Caja 46 | «sin nombre» | sin nombre |
| Caja 50 | «La mujer del Cholo, Felíz cumpleaños, Como en los Cuentos, …» | «Felíz» lleva «Feliz» (sin tilde) |
| Caja 63 | «VOZ Y CUERPO» | todo en mayúsculas |

#### 1.e Resumen de caja vacío o que no es un resumen

| Caja | Resumen |
|---|---|
| Caja 23 | «1997» |
| Caja 35 | «RESUMEN EN CAJA 30» |

Cajas sin resumen (29): 5, 6, 12, 15, 16, 18, 19, 22, 29, 31, 32, 34, 38, 39, 41, 43, 47, 51, 52, 54, 55, 56, 57, 63, 66, 67, 68, 69, 70.

### 2. Registros sin datos básicos

| Situación | Registros | Ejemplos |
|---|---|---|
| **Sin título ni tipo de documento** (la ficha queda sin encabezado) | 5 | C2R30, C2R31, C2R32, C10R11, C25R8 |
| Sin tipo de documento | 8 | C2R30, C2R31, C2R32, C4R5, C10R11, C10R12, C15R9, C25R8 |
| Sin título (la ficha muestra el tipo) | 183 | C1R3, C1R5, C1R6, C1R12, C1R13, C1R14, C1R15, C1R16, C1R17, C1R18, C1R30, C1R31 … (+171) |
| Sin descriptores | 124 | C1R4, C1R7, C1R9, C1R15, C1R19, C1R21, C1R26, C1R27, C1R30, C1R31, C1R32, C1R33 … (+112) |
| Sin autor | 243 | C1R7, C1R10, C1R12, C1R13, C1R14, C1R15, C1R16, C1R17, C1R18, C1R26, C1R27, C1R28 … (+231) |

#### 2.a Títulos con marcas de duda

| Registro | Título |
|---|---|
| C25R7 | «"Estar Domesticada"???» |

#### 2.b Mismo título escrito de formas distintas dentro de una caja

Mismo título salvo mayúsculas, tildes, comillas o signos. Conviene unificar para que la búsqueda y el listado los agrupen.
| Caja | Variantes |
|---|---|
| Caja 1 | «Soldados y Soldaditos» (C1R1, C1R2, C1R4, C1R7, C1R9, C1R10, C1R11, C1R19, C1R21, C1R22, C1R23, C1R24 … (+3)) / «Soldados y Soldaditos.» (C1R20) |
| Caja 5 | «Noticia de un Secuestro» (C5R5, C5R6, C5R7) / «Noticia de un secuestro» (C5R8, C5R10, C5R12) |
| Caja 6 | «La tregua» (C6R1, C6R2, C6R4, C6R5, C6R7, C6R8, C6R9, C6R10) / «La Tregua» (C6R3) |
| Caja 12 | «La Víctima» (C12R1) / «La Vìctima» (C12R2) |
| Caja 15 | «MUJERES» (C15R8) / «Mujeres» (C15R9) |
| Caja 17 | «"Vientos de Agua", Capítulo sin identif…» (C17R13) / «"Vientos de Agua", Capitulo sin identif…» (C17R31, C17R32, C17R33, C17R35, C17R39, C17R40) |
| Caja 20 | «La Isla» (C20R1, C20R2, C20R17, C20R18, C20R21) / «"La Isla"» (C20R5, C20R6, C20R7, C20R8, C20R9, C20R10, C20R12, C20R13, C20R14, C20R15, C20R16, C20R19 … (+2)) |
| Caja 32 | «Vientos de Agua. Capitulo 12» (C32R8) / «Vientos de Agua Capitulo 12» (C32R9) |
| Caja 35 | «Éramos tan jóvenes, Capítulo VIII» (C35R10) / «Éramos tan jóvenes, Capitulo VIII» (C35R12) |
| Caja 35 | «Éramos tan jóvenes, Capitulo VII» (C35R11) / «Éramos tan jóvenes, Capítulo VII» (C35R13) |
| Caja 36 | «"Dos, por ejemplo"» (C36R3, C36R5) / «"Dos por ejemplo"» (C36R4, C36R6, C36R7) |
| Caja 38 | «OPEN EYES» (C38R1) / «Open Eyes» (C38R2, C38R3, C38R4, C38R5) |
| Caja 40 | «Por la familia o Retrato incompleto» (C40R1) / «Por la Familia o Retrato incompleto» (C40R6, C40R7) |
| Caja 42 | «Las viudas» (C42R3, C42R4, C42R5, C42R7, C42R8, C42R9) / «"Las Viudas"» (C42R19) |
| Caja 44 | «"Ruggiero" - Ruggero» (C44R1, C44R2, C44R3, C44R4, C44R5, C44R6, C44R7) / «"ruggiero" - Ruggero» (C44R8) |
| Caja 47 | «Dale nomás» (C47R1) / «"Dale Nomás"» (C47R3) |
| Caja 47 | «Tres por Chéjov» (C47R2) / «"Tres por Chejov"» (C47R4) |
| Caja 49 | «Fade Out» (C49R7, C49R9, C49R10, C49R11, C49R18, C49R19, C49R20, C49R21) / «Fade Out.» (C49R8) / «Fade out» (C49R14) |
| Caja 50 | «"Felíz cumpleaños"» (C50R3) / «"Felíz cumpleaños "» (C50R11) |
| Caja 53 | «Caballos salvajes» (C53R4, C53R5, C53R6, C53R7, C53R8, C53R12, C53R13, C53R15, C53R25) / «(Caballos salvajes)» (C53R20) |
| Caja 56 | «Viejo Gringo» (C56R1) / «VIEJO GRINGO» (C56R15) |
| Caja 56 | «OLD GRINGO» (C56R3, C56R4, C56R5, C56R6, C56R8, C56R9, C56R10, C56R11, C56R12, C56R13, C56R14) / «Old Gringo» (C56R16) |
| Caja 57 | «CABALLEROS» (C57R1) / «Caballeros» (C57R13, C57R14) |

### 3. Años y fechas

`anio` solo se completa cuando la columna Año trae un año limpio (es el que filtra el sitio). El resto queda en `anio_texto`.
| Año sin valor filtrable | Registros | Ejemplos | Valores encontrados |
|---|---|---|---|
| Año único con duda («1972?», «¿1984?», «1989???») | 8 | C5R1, C5R11, C5R12, C6R1, C18R22, C26R18, C40R7, C58R2 | 2008? · 2009? · 1972? · ¿1984? · 1977(¿?) · 2004?? |
| Rango o varios años («1981/82», «2004/ 2005», «Entre 1983 y 1986») | 64 | C4R7, C4R8, C4R9, C7R2, C11R20, C14R1, C14R2, C14R3, C14R4, C14R5, C14R6, C14R7 … (+52) | Posterior a 1990 porque el programa y la funda de CDs tienen · 2001 a 2006 · 1960 a 2009 … |
| Fecha completa en la columna Año («03/02/2006») | 7 | C14R9, C16R7, C33R6, C55R22, C55R23, C55R24, C66R18 | 03/02/2006 · 01/05/1985 · 02/02/1982 (sobre) · 28/08/1944 · 03/05/1945 · 25/06/1944 |
| Solo signos o «sin fecha» | 20 | C13R15, C16R11, C21R9, C28R7, C33R4, C40R1, C40R2, C40R3, C40R4, C40R5, C40R6, C42R1 … (+8) | Sin dato · sin fecha · No tiene fecha · ? · ?? · sin precisar |
| Texto que no es un año («Año XXII de la publicación») | 3 | C4R21, C4R22, C23R1 | Año XXII de la publicación · Año XXVIII · Hace referencia a estrenos en agosto/95 y agost… |

#### 3.a Año distinto del que dice la fecha del documento

De estos, 14 tienen el patrón «el día coincide con el año»: la fecha se escribió como aa/mm/dd (05/03/30 = 30/03/2005) y Excel la leyó como dd/mm/aa. Hay que corregirla en la planilla (o escribirla como texto).

| Registro | Año (col. K) | Fecha como dice el documento | Diagnóstico |
|---|---|---|---|
| C32R4 | 2005 | «05/04/2004» | parece cargada como aa/mm/dd y leída como dd/mm/aa: sería 04/04/2005 |
| C32R2 | 2004 | «04/12/2014» | parece cargada como aa/mm/dd y leída como dd/mm/aa: sería 14/12/2004 |
| C29R7 | 2005 | «05/03/2017» | parece cargada como aa/mm/dd y leída como dd/mm/aa: sería 17/03/2005 |
| C3R1 | 2008 | «08/12/2017» | parece cargada como aa/mm/dd y leída como dd/mm/aa: sería 17/12/2008 |
| C32R10 | 2005 | «05/04/2018» | parece cargada como aa/mm/dd y leída como dd/mm/aa: sería 18/04/2005 |
| C32R3 | 2005 | «05/04/2018» | parece cargada como aa/mm/dd y leída como dd/mm/aa: sería 18/04/2005 |
| C32R6 | 2005 | «05/03/2019» | parece cargada como aa/mm/dd y leída como dd/mm/aa: sería 19/03/2005 |
| C32R11 | 2005 | «05/04/2019» | parece cargada como aa/mm/dd y leída como dd/mm/aa: sería 19/04/2005 |
| C32R12 | 2005 | «05/04/2019» | parece cargada como aa/mm/dd y leída como dd/mm/aa: sería 19/04/2005 |
| C29R1 | 2005 | «05/02/2021» | parece cargada como aa/mm/dd y leída como dd/mm/aa: sería 21/02/2005 |
| C29R2 | 2005 | «05/02/2025» | parece cargada como aa/mm/dd y leída como dd/mm/aa: sería 25/02/2005 |
| C32R7 | 2005 | «05/04/2029» | parece cargada como aa/mm/dd y leída como dd/mm/aa: sería 29/04/2005 |
| C32R5 | 2005 | «05/03/1930» | parece cargada como aa/mm/dd y leída como dd/mm/aa: sería 30/03/2005 |
| C32R17 | 2004 | «04/12/1931» | parece cargada como aa/mm/dd y leída como dd/mm/aa: sería 31/12/2004 |
| C10R1 | 1993 | «sabado 30 marzo 1991» | revisar cuál de los dos vale |
| C16R3 | 1997 | «1981» | revisar cuál de los dos vale |
| C17R23 | 2004 | «03/10/2003» | revisar cuál de los dos vale |
| C17R36 | 2004 | «29/12/2003» | revisar cuál de los dos vale |
| C20R2 | 1979 | «01/06/1976» | revisar cuál de los dos vale |
| C20R7 | 1979 | «01/10/1978» | revisar cuál de los dos vale |
| C20R8 | 1979 | «01/10/1978» | revisar cuál de los dos vale |
| C20R9 | 1979 | «01/10/1978» | revisar cuál de los dos vale |
| C27R4 | 1981 | «01/06/1979» | revisar cuál de los dos vale |
| C27R5 | 1981 | «01/08/1979» | revisar cuál de los dos vale |
| C28R1 | 1975 | «1974/75» | revisar cuál de los dos vale |
| C35R16 | 1986 | «España 1978» | revisar cuál de los dos vale |
| C35R17 | 1986 | «España 1978» | revisar cuál de los dos vale |
| C3R7 | 2008 | «14/09/1990» | revisar cuál de los dos vale |
| C3R8 | 2008 | «20/10/1990» | revisar cuál de los dos vale |
| C41R16 | 2002 | «1989» | revisar cuál de los dos vale |
| C41R17 | 2002 | «1989» | revisar cuál de los dos vale |
| C41R4 | 2002 | «05/01/2001» | revisar cuál de los dos vale |
| C42R14 | 1987 | «02/10/1986» | revisar cuál de los dos vale |
| C43R27 | 1999 | «9 july 1998» | revisar cuál de los dos vale |
| C46R18 | 1954 | «23/07/1958» | revisar cuál de los dos vale |
| C49R5 | 1991 | «July 17th 1992» | revisar cuál de los dos vale |
| C52R2 | 1947 | «15/10/1941» | revisar cuál de los dos vale |
| C60R19 | 2000 | «1994» | revisar cuál de los dos vale |
| C64R1 | 1983 | «IX-1982» | revisar cuál de los dos vale |

#### 3.b Años fuera de 1950–hoy

Pueden ser correctos (material de época), pero conviene mirarlos.
| Registro | Año | Fecha como dice el documento |
|---|---|---|
| C52R1 | 1948 | 12/05/1948 |
| C52R2 | 1947 | 15/10/1941 |
| C52R3 | 1948 | — |
| C52R4 | 1947 | 17/03/1947 |
| C52R5 | 1949 | 04/02/1949 |
| C52R6 | 1947 | 13/11/1947 |
| C52R7 | 1948 | 21 de septiembee 1948 |
| C52R10 | 1948 | 11/03/1948 |
| C52R11 | 1948 | 08/04/1948 |

### 4. Autores


#### 4.a Formas de escribir «Aída Bortnik»

Cada forma distinta con que aparece Aída en el campo Autor (sola o con coautores). La forma de autoridad propuesta es **Aída Bortnik**.
| Forma | Registros | Ejemplos |
|---|---|---|
| «Aída Bortnik» ✓ | 448 | C1R1, C1R4, C1R8, C1R9, C1R11, C1R21, C1R22, C1R23, C1R24, C1R25, C1R31, C2R11 … (+436) |
| «AB» | 93 | C1R2, C1R19, C1R20, C9R1, C9R5, C9R6, C9R7, C9R8, C9R9, C9R10, C9R11, C9R12 … (+81) |
| «Aida Bortnik» | 65 | C2R1, C2R3, C2R5, C2R6, C2R7, C2R29, C10R18, C10R19, C10R20, C10R21, C10R22, C10R23 … (+53) |
| «Aída Bortink» | 21 | C5R3, C5R4, C5R5, C5R6, C5R7, C5R8, C6R4, C6R5, C21R4, C21R5, C21R6, C21R7 … (+9) |
| «Aída Bornik» | 13 | C41R2, C41R3, C41R4, C41R5, C41R6, C41R7, C41R8, C41R9, C41R10, C41R13, C41R14, C41R15 … (+1) |
| «Aida Bortnik Ferreira» | 8 | C14R13, C14R14, C14R15, C14R16, C14R17, C14R18, C14R19, C14R20 |
| «Aída» | 3 | C4R18, C4R19, C17R23 |
| «Bortnik Aída» | 1 | C15R11 |
| «Bortnik» | 1 | C44R8 |

#### 4.b Autor con texto descriptivo o demasiado largo

| Autor | Registros | Ejemplos |
|---|---|---|
| «Alfredo E. Allende/ Luis Palalro y Señora/ International association of Jewish …» | 1 | C2R9 |
| «Eduardo Mignona, Adolfo Perez Esquivel, Graciela y Enrique Fernández Meijide, R…» | 2 | C2R17, C2R18 |
| «Entre paréntesis dice Patricia» | 1 | C13R29 |
| «El equipo de guionistas de la serie estuvo conformado por Juan Campanella, Alej…» | 32 | C17R1, C17R2, C17R3, C17R4, C17R5, C17R6, C17R7, C17R8, C17R9, C17R10, C17R11, C17R12 … (+20) |
| «El equipo de guionistas de la serie estuvo conformado por Juan Campanella, Alej…» | 2 | C17R33, C17R35 |
| «Teatro Municipal Gral. San Martín donde hacen entrevistas a diferentes referent…» | 1 | C18R2 |
| «Los guionistas de La Isla fueron Alejandro Doria y Aída Bortnik» | 8 | C20R1, C20R2, C20R17, C20R18, C20R19, C20R20, C20R21, C20R22 |
| «Los guionistas de La Isla fueron Alejandro Doria y Aída Bortnik. En la tapa de …» | 3 | C20R7, C20R8, C20R9 |
| «Los guionistas de La Isla fueron Alejandro Doria y Aída Bortnik.» | 1 | C20R10 |
| «The Films Society of Lincoln Center and the Department of Film of the Museum of…» | 3 | C53R5, C53R6, C53R11 |
| «Guillermo Correa entrevista a Francisco Negro Juárez, quien le envió fax a AB r…» | 1 | C53R23 |

#### 4.c Autor con remitente y destinatario («De X A: Y»)

Son cartas: el autor es el remitente. El destinatario podría ir en el resumen o en un campo propio.
| Autor | Registros |
|---|---|
| «De Valerie Kemp A: Aída Bortnik» | C49R7 |
| «De: Jane Wittekind A: Aída Bortnik» | C49R8 |
| «De Piedad Bonilla A: Aída Bortnik» | C49R9 |

#### 4.d Autor con signos de duda

| Autor | Registros |
|---|---|
| «Aída Bortnik???» | C33R4 |

### 5. Vocabularios

Términos activos: 108 (57 tipos, 51 descriptores). Propuestos sin curar: 108. Fusionados: 0.

#### 5.a Variantes del mismo término (puntuación, plural, «?»)

| Vocabulario | Variantes (id, usos) |
|---|---|
| Tipo | «Asistencia a la producción» (#82, 44) / «Asistencia a la producción.» (#73, 68) |
| Tipo | «Cuento» (#68, 25) / «Cuento?» (#99, 1) |
| Tipo | «Novela» (#76, 8) / «Novela.» (#75, 1) |
| Tipo | «Referencia de época» (#59, 2) / «Referencias de época» (#58, 9) |
| Tipo | «Reseña/Revista» (#48, 2) / «ReseñaRevista» (#96, 1) |
| Descriptor | «Cuentos» (#66, 24) / «Cuentos?» (#100, 1) |
| Descriptor | «Película de televisión» (#62, 131) / «Película de televisión?????» (#91, 1) |

#### 5.b Términos que no parecen vocabulario

| Vocabulario | Término | Motivo | Usos | Registros |
|---|---|---|---|---|
| Tipo | «Aída Bortnik/ Sergio Renán (sobre "Alrededor de la jaula" d…» (#101) | frase o texto pegado | 1 | C39R7 |
| Tipo | «Asistencia a la producción.» (#73) | punto final | 68 | C10R13, C10R15, C15R26, C17R3, C17R6, C17R9, C17R10, C17R11, C17R12, C17R13, C17R14, C17R16 … (+56) |
| Tipo | «Cuento?» (#99) | signos de duda | 1 | C37R8 |
| Tipo | «Novela.» (#75) | punto final | 1 | C11R5 |
| Tipo | «ReseñaRevista» (#96) | palabras pegadas | 1 | C33R3 |
| Tipo | «testeo» (#107) | empieza en minúscula | 1 | C53R13 |
| Descriptor | «.» (#97) | letra suelta o solo signos | 2 | C33R4, C38R1 |
| Descriptor | «Cuentos?» (#100) | signos de duda | 1 | C37R8 |
| Descriptor | «g» (#64) | letra suelta o solo signos | 1 | C6R10 |
| Descriptor | «guio» (#93) | empieza en minúscula | 1 | C26R20 |
| Descriptor | «Novelas. Noticias.» (#69) | punto final | 34 | C7R12, C11R1, C11R5, C11R9, C11R16, C11R18, C11R20, C15R13, C15R14, C15R16, C15R23, C16R6 … (+22) |
| Descriptor | «Película de televisión?????» (#91) | signos de duda | 1 | C25R7 |
| Descriptor | «propuesta» (#94) | empieza en minúscula | 1 | C26R41 |
| Descriptor | «s» (#65) | letra suelta o solo signos | 1 | C6R10 |

#### 5.c El mismo término como tipo y como descriptor

Suele indicar que se cargó en la columna equivocada. «Ambos» = registros que ya lo tienen en los dos vocabularios.
| Término | Como tipo | Como descriptor | Ambos | Solo como descriptor |
|---|---|---|---|---|
| «Carta» / «Carta» | 99 | 65 | 46 | C1R3, C8R4, C11R6, C11R17, C15R4, C15R13, C18R1, C18R16, C20R7, C26R19, C26R21, C26R25 … (+7) |
| «Comunicado de prensa» / «Comunicado de prensa» | 9 | 26 | 6 | C1R22, C1R24, C13R2, C13R3, C20R14, C20R15, C20R19, C22R11, C38R2, C38R3, C46R4, C46R5 … (+8) |
| «Cuento?» / «Cuentos?» | 1 | 1 | 1 |  |
| «Fotografía» / «Fotografía» | 15 | 15 | 12 | C66R2, C66R6, C66R8 |
| «Guion» / «Guion» | 545 | 485 | 406 | C2R11, C4R11, C4R12, C4R19, C4R20, C6R8, C8R4, C9R2, C9R4, C10R1, C10R7, C10R10 … (+67) |
| «Obra de teatro» / «Obra de teatro» | 96 | 90 | 77 | C1R17, C2R16, C18R11, C18R14, C25R1, C25R4, C25R5, C25R6, C25R8, C27R2, C66R13, C69R1 … (+1) |
| «Referencias de época» / «Referencia de época» | 9 | 2 | 0 | C3R7, C3R8 |
| «Televisión» / «Televisión» | 146 | 4 | 4 |  |

#### 5.d Fusiones con destino en el otro vocabulario

Una fusión de un descriptor en un tipo (o al revés) hace que el importador cargue el término en el vocabulario equivocado y puede fallar por clave duplicada.
_Sin hallazgos._

#### 5.e Términos sin registros

_Sin hallazgos._
