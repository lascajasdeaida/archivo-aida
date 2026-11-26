# Búsqueda por texto

Buscador del sitio público y del backoffice: `src/Buscador.php`. Índices en `db/schema.sql` (v0.5) y en
la migración `db/migraciones/001_busqueda_fulltext.sql`.

```php
$b = new Aida\Buscador(aida_db());                               // sitio público
$b = new Aida\Buscador(aida_db(), Aida\Buscador::BACKOFFICE);    // backoffice
$r = $b->buscar('jovenes "historia oficial"', [
    'caja' => [2, 31], 'anio_desde' => 1980, 'anio_hasta' => 1989,
    'tipo' => 12, 'descriptor' => [3, 8], 'acceso' => 'publico', 'digitalizado' => 1,
], pagina: 1, porPagina: 20, orden: null);      // orden: relevancia (con texto) | codigo | anio | anio_desc | digital
// Además: 'decada' => [1980, 1990] (O entre décadas), 'sin_anio' => true (suma los que no tienen año),
//         'etapa' => [ids de periodo] (registros de cajas de esa etapa; faceta 'etapa').
$cajas = $b->cajas('secuestro');                // cajas por nombre/resumen, para mostrar arriba
```

Devuelve `total`, `pagina`, `paginas`, `resultados` (código, título, autor, año, `anio_hasta`, `anio_sin_confirmar`,
`anios` —la lista de años— y `anio_etiqueta` —«1981–1982», «1998, 2006–2008», «1972 (sin confirmar)», para mostrar—, resumen recortado, tipos, caja, acceso, digitalizado, relevancia), `facetas` (caja, decada, tipo, descriptor, acceso, digitalizado, cada una con
`valor`, `etiqueta`, `cantidad`) y `consulta` (cómo se interpretó el texto, para mostrarlo si hace falta).

## Qué entiende el cuadro de búsqueda

| Escribe | Encuentra |
|---|---|
| `jovenes`, `JÓVENES` | lo mismo: no importan tildes ni mayúsculas |
| `secuestr` | por prefijo: secuestro, secuestros |
| `"historia oficial"` o `«historia oficial»` | la frase exacta, en ese orden |
| `1983` | el texto 1983 en cualquier campo, y los registros que tienen 1983 entre sus años («Entre 1981 y 1986»; no «1981/1986», que son dos años sueltos) |
| `C2R5`, `c10r02` | ese registro (los ceros a la izquierda no importan) |
| `C14` | los registros de la caja 14 |
| `tv`, `yo`, `the` | la palabra completa (no «mayo» ni «ensayo») |
| `carta -guion` | cartas que no mencionan guion |

Varias palabras: tienen que estar todas. Cada una puede estar en el registro, en sus tipos o descriptores, o en el
nombre de su caja (así `zsherezade guion` encuentra C14R1: «Zsherezade» está en el nombre de la caja y «guion» en el registro).

**Campos que se buscan.** Público: código, título, autor, lugar, fecha, año, resumen, ISBN, observaciones/editorial,
edición de catálogo. El backoffice suma las observaciones de conservación y de digitalización, la selección para
digitalizar, la nomenclatura y el PRIVADO del Excel. **Relevancia**: código exacto ≫ título (×3) > resto de los campos.

**Qué se muestra en modo público:** registros sin baja, `publicado = 1`, con acceso público o restringido (la ficha se
ve; «a revisar» no se publica hasta decidir), de cajas sin baja y `publicada = 1`. El backoffice ve todo salvo las
bajas (con `'bajas' => true`, también esas).

**Años (15/11/2026, ajustado el mismo día):** un registro responde **solo a sus años**, guardados en `registro_anio`
(ver «Años» en `decisiones.md`). Un rango trae todos sus años («1981/82» aparece con 1981 y con 1982; «Entre 1983 y 1986», con
1983, 1984, 1985 y 1986); una lista salteada, solo los que nombra («1998/2006/2007/2008» no aparece filtrando 2000).
`anio` = tiene ese año; `anio_desde`/`anio_hasta` = tiene algún año dentro del pedido; `decada` (1980) = tiene algún año entre
1980 y 1989. La faceta de décadas cuenta un registro una vez en cada década en la que tiene algún año («1998/2006»: años 90 y 2000,
no se cuenta en una década del medio). El orden por año usa `registro.anio`, el menor de sus años.
Los años sin confirmar filtran igual; el sitio tiene que mostrar `anio_etiqueta`.

**Facetas:** cada una se cuenta sin su propio filtro, para que la interfaz pueda ofrecer «sumar» otra caja u otro
tipo. Dentro de un filtro alcanza con un valor (caja 2 **o** 31); entre filtros, todos (caja **y** década **y** tipo).

## Cómo está resuelto (y por qué así)

**Tildes y mayúsculas.** Las tablas usan `utf8mb4_unicode_ci`, y el FULLTEXT de InnoDB compara las palabras con la
colación de la columna. Probado en MariaDB 10.11 y MySQL 8.4: `jovenes`, `JÓVENES` y `jove*` encuentran «jóvenes»;
`guion` encuentra «guión». No hace falta una columna normalizada. ⚠️ Las tablas nuevas tienen que declarar esa
colación (MySQL 8.4 usa `utf8mb4_0900_ai_ci` por defecto, que también ignora tildes, pero mejor no mezclar).

**Largo mínimo de palabra y stopwords, sin tocar la configuración del servidor.** InnoDB no indexa palabras de menos
de `innodb_ft_min_token_size` letras (3 por defecto en MySQL 8 y MariaDB; en un hosting compartido no se puede
cambiar) ni sus 36 stopwords (`the`, `with`, `de`, `la`, `en`…). El buscador:

1. lee `@@innodb_ft_min_token_size` y `@@innodb_ft_server_stopword_table` al conectarse, así que se adapta si el
   servidor tiene otro valor;
2. manda al índice FULLTEXT solo las palabras que el índice tiene;
3. busca las demás como **palabra completa con `REGEXP`** (con clases para las vocales con tilde), que se comporta
   igual en MySQL 8 (ICU) y MariaDB (PCRE).

Las frases van al índice aunque tengan palabras cortas (`"yo no hice nada"` funciona: InnoDB usa las palabras largas
para encontrar candidatos y verifica la frase completa). Una frase hecha solo de palabras cortas se busca con `LIKE`.
Años (`1983`) y códigos (`C2R5`, 4 letras) entran en el índice sin trucos.

**Sin triggers, sin procedimientos, sin tabla auxiliar.** Muchos hostings no permiten crear triggers con binlog activo,
y un `mysqldump` con triggers arrastra el `DEFINER` del usuario original. Una tabla de búsqueda desnormalizada
habría que mantenerla en sincronía desde el importador, el escaneo y el backoffice. Los índices van sobre las columnas
reales: siempre están al día, también cuando se restaura un backup.

**El texto se evalúa una sola vez por búsqueda.** Consulta 1: candidatos (visibilidad + texto) con su relevancia.
Consulta 2: tipos y descriptores de los candidatos. Filtros, facetas, orden y paginación se resuelven en PHP; la
consulta 3 trae solo los datos de la página. Con el tamaño del archivo es más rápido que repetir el texto en cada
faceta (la primera versión, con las facetas en SQL, tardaba 18–63 ms; esta, 0,5–12 ms). Si algún día el archivo
pasara de ~10.000 registros, conviene revisar este punto (ver la prueba a escala abajo).

Todas las consultas son preparadas. El texto del usuario se reduce a letras y números antes de armar la expresión
booleana, así que no puede inyectar operadores de FULLTEXT.

## Mediciones (15/11/2026)

Datos reales: 70 cajas, 1.098 registros, MariaDB 10.11. Mediana de 15 corridas, desde PHP.
«LIKE» es la búsqueda actual del backoffice (`Rutas::caja`, sin limitar a una caja): solo título, resumen, autor y
nombre de vocabulario. «Buscador» incluye relevancia **y las seis facetas**.

| Consulta | LIKE ms / resultados | Buscador ms / resultados | Nota |
|---|---|---|---|
| `jovenes` | 3,3 / 87 | 4,1 / 88 | |
| `guion` | 3,8 / 690 | 8,8 / 704 | 64 % del archivo: casi todo el tiempo son las facetas |
| `1983` | 3,3 / 15 | 3,7 / 46 | ahora también fecha, año y edición de catálogo |
| `C2R5` | 3,7 / 1 | 0,6 / 1 | índice único del código |
| `secuestro` | 3,8 / 26 | 3,5 / 31 | |
| `"historia oficial"` | 3,3 / 35 | 2,3 / 37 | LIKE no encuentra «oficial historia»; la frase tampoco (correcto) |
| `tv` | 3,7 / 70 | 9,6 / 79 | palabra corta → REGEXP |
| `yo` | 3,2 / 70 | 9 / 17 | LIKE trae «mayo», «mayoría», «York»: la mayoría son falsos positivos |
| `carta` | 3,8 / 181 | 5,9 / 184 | |

En MySQL 8.4 (contenedor local) los resultados son los mismos registros; tiempos 1–20 ms.

**EXPLAIN.** LIKE: `registro | ALL | 1100 filas | Using where; Using filesort` (recorre la tabla entera siempre).
Solo texto del registro: `registro | fulltext | ft_registro_publico`. Consulta de candidatos completa (cada palabra
puede estar en el registro, en el vocabulario o en el nombre de la caja): `caja | ALL | 70` → `registro | ref |
uq_registro_caja_numero` → subconsulta de vocabulario materializada una vez; el FULLTEXT se resuelve una sola vez
por palabra y se cruza por fila.

**A escala (prueba sintética, 21.960 registros = archivo ×20):** LIKE 56–69 ms siempre. Buscador: `C2R5` 0,6 ms,
frase 27 ms, `secuestro` 46 ms, `carta` (3.680 resultados) 114 ms, `guion` (14.080 resultados, 64 % del archivo)
172 ms. Las consultas selectivas mejoran con el índice; las que traen medio archivo pagan las facetas en PHP.

## Pendiente para conectar

- El backoffice todavía usa LIKE en la ficha de caja (`Rutas::caja`). Reemplazarlo por
  `(new Buscador($db, Buscador::BACKOFFICE))->buscar($q, ['caja' => $numero, ...])` lo hace Programación.
- El sitio público no está conectado (lo hace Programación con la maqueta v4).
