# Componentes del sitio público

Diseño web · 15/11/2026, actualizado el 16/11/2026 a la **dirección B · Museografía con el cursor de C** ([`sistema.md`](sistema.md)).
HTML de referencia para que Programación lo integre en las vistas PHP. Los fragmentos de abajo siguen valiendo: las clases no cambiaron, cambió la hoja.
Cambios de marcado de la dirección B (ya aplicados en `vistas/publico/`):

- **Cabecera y pie:** la marca es el logo de cajas (`public/sitio/img/logo-tinta.svg` en la cabecera, `logo-calado-blanco.svg` + `.pie__logo` en el pie).
  `.marca__sello` y `.marca__texto` quedan solo para las maquetas.
- **Hero del inicio:** la ilustración SVG se reemplaza por `<figure class="hero__foto" aria-hidden="true"><img … alt=""></figure>` (foto en blanco y negro).
- **Banda de título** (`.banda`, caja y ficha): `<div class="banda"><div class="contenedor">` migas + `.cabeza-pagina` `</div></div>`, y el resto de la página
  en otro `.contenedor`. Pared azul de borde a borde con filete rojo; adentro, texto y foco en blanco. En la caja, el resumen y el buscador quedan fuera de la banda (se leen sobre crema).
- **Título que se tipea:** `<h1 data-tipeo>` (inicio, caja, ficha). En la ficha, `class="largo"` si el título pasa de 60 caracteres. Comportamiento en `sitio.js`; reglas en `sistema.md` §5.
- **Lectura larga:** envolver en `.lectura`, `.prosa` o `.ficha__texto` (Inter 17/18 px, interlínea 1,65, 66 caracteres).
- **Visor:** `.visor__zona` lleva `tabindex="0" role="region" aria-label="…"` (se desplaza con el teclado).
Estilos en [`public/sitio/estilo.css`](../../public/sitio/estilo.css) (usa solo [`tokens.css`](../../public/sitio/tokens.css); el sitio y las maquetas usan el mismo archivo). Catálogo vivo: [`maquetas/componentes.html`](maquetas/componentes.html).
Las plantillas JS equivalentes están en [`maquetas/maqueta.js`](maquetas/maqueta.js) (`tarjeta`, `tarjetaCaja`, `etiquetas`, `anioHTML`…).

Convenciones:
- Clases en castellano, BEM liviano (`bloque`, `bloque__parte`, `bloque--variante`).
- Íconos: `<svg class="icono" aria-hidden="true" focusable="false"><use href="#i-NOMBRE"/></svg>`; el sprite (`<symbol id="i-…">`) va una vez al principio del `<body>`. Lista y trazos en `ICONOS` de `maqueta.js`.
- Textos de interfaz: los de UX (`docs/ux/sitio-publico.md`). Contenido: de la base.
- Todo lo que cambia de estado se marca con atributos ARIA (`aria-expanded`, `aria-current`, `aria-invalid`), y el CSS los usa.

## Índice

1. [Cabecera y pie](#1-cabecera-y-pie)
2. [Botones](#2-botones)
3. [Buscador](#3-buscador)
4. [Badge de estado y año sin confirmar](#4-badge-de-estado-y-año-sin-confirmar)
5. [Tarjeta / fila de registro](#5-tarjeta--fila-de-registro)
6. [Tarjeta de caja](#6-tarjeta-de-caja)
7. [Tarjeta de etapa](#7-tarjeta-de-etapa)
8. [Chip de acceso con conteo](#8-chip-de-acceso-con-conteo)
9. [Chip de filtro (opción con conteo) y chip activo](#9-chip-de-filtro-opción-con-conteo-y-chip-activo)
10. [Faceta colapsable y panel de filtros](#10-faceta-colapsable-y-panel-de-filtros)
11. [Aviso de restringido / solo ficha](#11-aviso-de-restringido--solo-ficha)
12. [Bloque Documento y visor](#12-bloque-documento-y-visor)
13. [Paginación «Mostrar más»](#13-paginación-mostrar-más)
14. [Migas y vecinos](#14-migas-y-vecinos)
15. [Formulario](#15-formulario)
16. [Estados vacío, error y cargando](#16-estados-vacío-error-y-cargando)

---

## 1. Cabecera y pie

Banda marrón. En celular: marca + «Buscar» (ícono) + «Menú» que despliega la navegación; desde 900 px, navegación horizontal y «Buscar» con texto.

```html
<a class="saltar" href="#contenido">Saltar al contenido</a>
<header class="cabecera"><div class="contenedor">
  <a class="marca" href="/" aria-label="Las cajas de Aída, inicio">
    <svg class="marca__sello" viewBox="0 0 40 40" aria-hidden="true" focusable="false">…</svg>
    <span class="marca__texto">Las cajas <em>de Aída</em></span></a>
  <a class="cabecera__accion cabecera__buscar" href="/explorar#q"><svg class="icono" …><use href="#i-buscar"/></svg><span class="texto-largo">Buscar</span><span class="solo-lector">Buscar en el archivo</span></a>
  <button class="cabecera__accion menu-boton" type="button" aria-expanded="false" aria-controls="nav-principal"><svg class="icono" …><use href="#i-menu"/></svg>Menú</button>
  <nav class="nav-principal" id="nav-principal" aria-label="Principal"><ul>
    <li><a href="/" aria-current="page">Inicio</a></li>
    <li><a href="/explorar">Explorar el archivo</a></li>
    <li><a href="/cajas">Las 70 cajas</a></li>
    <li><a href="/sobre-el-archivo">Sobre el archivo</a></li>
  </ul></nav>
</div></header>
```
JS: el botón «Menú» alterna `aria-expanded` y la clase `abierta` de `.nav-principal`. El pie (`footer.pie`) repite la marca y los enlaces institucionales.

## 2. Botones

| Variante | Clase | Uso |
|---|---|---|
| Primario | `boton boton--primario` | Una acción principal por bloque: Buscar, Ver documento, Pedir consulta, Enviar pedido, «Ver N resultados». |
| Secundario | `boton boton--secundario` | Descargar, Copiar cita, Mostrar más, Limpiar, Consultar por este documento. |
| Fantasma | `boton boton--fantasma` | Acciones de texto: Limpiar todo, Leer más, Ver N más, Quitar «…». |
| Modificadores | `boton--chico` · `boton--icono` (cuadrado 44 px, con `aria-label`) · `boton--ancho` | |

```html
<a class="boton boton--primario" href="#visor" aria-controls="visor" aria-expanded="false">
  <svg class="icono" aria-hidden="true" focusable="false"><use href="#i-ver"/></svg>Ver documento <span class="peso">(25 págs.)</span></a>
<a class="boton boton--secundario" href="/registro/C14R3/descargar">
  <svg class="icono" aria-hidden="true" focusable="false"><use href="#i-descargar"/></svg>Descargar PDF <span class="peso">(105 MB)</span></a>
<button class="boton boton--fantasma" type="button" data-limpiar>Limpiar todo</button>
<button class="boton boton--primario" type="button" disabled>…</button>
```
Estados: hover, foco (anillo 3 px), activo y deshabilitado definidos en `estilo.css`. El peso del PDF va siempre en la descarga (UX §5.4).

## 3. Buscador

```html
<form class="buscador" action="/explorar" role="search" aria-label="Buscar en el archivo">
  <div class="buscador__campo">
    <svg class="icono" aria-hidden="true" focusable="false"><use href="#i-buscar"/></svg>
    <label class="solo-lector" for="q">Buscar en el archivo</label>
    <input type="search" id="q" name="q" placeholder="Título, persona, obra, tipo, año…" autocomplete="off">
  </div>
  <button class="boton boton--primario">Buscar</button>
</form>
```
En la caja se agrega `<input type="hidden" name="caja" value="2">`.

## 4. Badge de estado y año sin confirmar

```html
<div class="etqs">
  <span class="etq etq--digital"><svg class="icono" …><use href="#i-documento"/></svg>Documento digital</span>
  <span class="etq etq--ficha"><svg class="icono" …><use href="#i-ficha"/></svg>Solo ficha</span>
  <span class="etq etq--pedido"><svg class="icono" …><use href="#i-candado"/></svg>Consulta a pedido</span>
  <span class="etq etq--duda"><svg class="icono" …><use href="#i-duda"/></svg>Año sin confirmar</span>
  <span class="etq etq--tipo">Guion</span>
</div>

<!-- año dentro de un texto -->
<span class="anio-dudoso">1972 (sin confirmar)<svg class="icono" aria-hidden="true" focusable="false"><use href="#i-duda"/></svg></span>
```
Regla de año (`anioInfo` en `maqueta.js`): año limpio → número; texto con «?» o «¿» → se quitan los signos y paréntesis y se agrega «(sin confirmar)»; rango («1981/82», «2004/ 2005») → tal cual; vacío o «Sin dato» → «Sin fecha».
Orden de las etiquetas: «Documento digital» o «Solo ficha», «Consulta a pedido» si corresponde y hasta 2 tipos en tarjetas (5 en la ficha).

## 5. Tarjeta / fila de registro

Toda la tarjeta es **un solo enlace** (el `::after` del enlace del título la cubre); el foco rodea la tarjeta entera.

```html
<li class="registro" data-codigo="C2R11">
  <div class="mini mini--pedido" aria-hidden="true"><svg class="icono" …><use href="#i-candado"/></svg></div>
  <div class="registro__cuerpo">
    <h3><a href="/registro/C2R11"><span class="sin-titulo">Contrato/Acuerdo</span><span class="solo-lector"> (sin título)</span></a></h3>
    <p class="registro__datos">Sin título · <span class="codigo">C2R11</span> · Caja 2: La Historia Oficial · 1983</p>
    <p class="registro__extracto">Recibo de Aída para Luis Puenzo, por cobro a cuenta de la…</p>
    <div class="etqs">…badges…</div>
  </div>
</li>
```
Miniatura: `mini--hoja` (digitalizado: en producción, `<img>` de la primera página **sin filtro sepia**, `alt=""`), `mini--vacia` (solo ficha, ícono `ficha`), `mini--pedido` (restringido, ícono `candado`).
Título: corte a 3 líneas; extracto a 2. Nivel de encabezado según la página (`h2` en consulta, `h3` en listas). Contenedor: `<ul class="lista-registros">` (en la caja, `<ol>`: orden físico). En el inicio, `<ul class="carrusel">` (desliza en celular, 3 columnas en escritorio).

## 6. Tarjeta de caja

```html
<li><article class="caja">
  <p class="caja__num">Caja <b>14</b></p>
  <h3><a href="/caja/14-zsherezade">ZSHEREZADE</a></h3>
  <p class="caja__datos">22 documentos · 1999–2005</p>
  <div class="caja__pie"><div class="avance">
    <div class="avance__barra" role="img" aria-label="73 % digitalizado"><i style="width:73%"></i></div>
    <span>16 digitalizados</span></div></div>
</article></li>
```
Contenedor `<ul class="grilla-cajas">` (1/2/3 columnas). Sin digitalizados: «Sin digitalizar todavía». Con restringidos: «· 2 a pedido» en los datos.

## 7. Tarjeta de etapa

Índice de etapas del inicio y base de la faceta «Etapa». La barra ubica la etapa dentro de 1938–2013.

```html
<ul class="etapas">
  <li><article class="etapa">
    <h3><a href="/explorar?etapa=exilio">Exilio</a></h3>
    <p class="etapa__anios">1976–1979</p>
    <div class="tiempo" role="img" aria-label="De 1976 a 1979, dentro de 1938–2013"><i style="left:50.7%;width:4%"></i></div>
    <p class="etapa__n">116 documentos</p>  <!-- conteo simulado en la maqueta -->
  </article></li>
  <!-- sin cajas asignadas: sin enlace, borde punteado -->
  <li><article class="etapa etapa--sin-asignar"><h3>Caos Creativo</h3>…<p class="etapa__n">Sin cajas asignadas</p></article></li>
</ul>
```
`left = (desde − 1938) / 75 × 100 %`, `width = (hasta − desde) / 75 × 100 %`.

## 8. Chip de acceso con conteo

Accesos rápidos (inicio: por tipo y por época; caja: por tipo). Son enlaces a Explorar con el filtro aplicado.

```html
<ul class="accesos">
  <li><a class="acceso" href="/explorar?tipo=guion">Guion <span class="n">545</span></a></li>
  <li><a class="acceso acceso--todos" href="/explorar#faceta-tipo">Todos los tipos (38)</a></li>
</ul>
```

## 9. Chip de filtro (opción con conteo) y chip activo

Opción de faceta: checkbox nativo con etiqueta entera clicable; el conteo se lee («99 resultados»).

```html
<label class="opcion" for="f-tipo-2">
  <input type="checkbox" id="f-tipo-2" data-f="tipo" value="carta" checked>
  <span>Carta</span><span class="n">119<span class="solo-lector"> resultados</span></span></label>
<!-- etapa sin asignar -->
<label class="opcion" for="f-etapa-3"><input type="checkbox" id="f-etapa-3" disabled><span>Caos Creativo (1963–2009)</span><span class="n">—<span class="solo-lector"> resultados</span></span></label>
```
Cuando se ven todas las opciones de Tipo o Descriptores, se agrupan con `<p class="grupo-opciones" aria-hidden="true">Correspondencia</p>` (grupos de `docs/datos/vocabulario-propuesta.md`).

Chip activo (quitable):

```html
<ul class="chips" aria-label="Filtros activos">
  <li><button type="button" class="chip" data-quitar="0">Tipo de documento: <b>Carta</b>
    <span class="chip__x"><svg class="icono" aria-hidden="true" focusable="false"><use href="#i-cerrar"/></svg></span><span class="solo-lector">, quitar filtro</span></button></li>
  <li><button type="button" class="boton boton--fantasma boton--chico" data-limpiar>Limpiar todo</button></li>
</ul>
```

## 10. Faceta colapsable y panel de filtros

```html
<details class="faceta" id="faceta-tipo" open>
  <summary><span>Tipo de documento</span><span class="faceta__n">1<span class="solo-lector"> elegido</span></span>
    <svg class="icono" aria-hidden="true" focusable="false"><use href="#i-abajo"/></svg></summary>
  <fieldset><legend>Tipo de documento</legend>
    <label class="solo-lector" for="buscar-tipo">Buscar en Tipo de documento</label>
    <input class="buscar-en-faceta" id="buscar-tipo" type="search" placeholder="Buscar tipo de documento…">
    …opciones…
    <button type="button" class="boton boton--fantasma boton--chico" data-mas="tipo">Ver 30 más</button>
  </fieldset>
</details>
```
Año: `<div class="histo" aria-hidden="true">` con un `<span style="height:%">` por década (`.elegida` en naranja) + `<div class="rango">` con Desde / Hasta / Aplicar.
Etapa sin asignar: `<p class="faceta__aviso">Todavía no hay cajas asignadas a las etapas.</p>` y opciones deshabilitadas.

Panel (misma pieza en celular y escritorio):

```html
<button type="button" class="boton boton--secundario boton-filtros" id="boton-filtros" aria-expanded="false" aria-controls="panel-filtros">
  <span>…ícono filtro… Filtros</span><span class="boton-filtros__n">2</span></button>
<div class="panel-filtros" id="panel-filtros" role="region" aria-labelledby="h-filtros">
  <div class="panel-filtros__cabeza"><h2 id="h-filtros-movil">Filtrar</h2><button type="button" class="boton boton--secundario boton--chico cerrar-panel">…Cerrar</button></div>
  <div class="panel-filtros__cuerpo"><h2 class="panel-filtros__titulo" id="h-filtros">Filtrar</h2>…facetas…</div>
  <div class="panel-filtros__pie"><button class="boton boton--secundario" data-limpiar>Limpiar</button><button class="boton boton--primario" id="ver-resultados">Ver 99 resultados</button></div>
</div>
```
Celular (< 900 px): al abrir se agrega `.abierto`, `role="dialog"` y `aria-modal="true"`, `body.panel-abierto`; foco atrapado, Esc cierra y el foco vuelve al botón. Escritorio: columna fija de 300 px con scroll propio; cabeza y pie del diálogo ocultos.

## 11. Aviso de restringido / solo ficha

```html
<div class="aviso aviso--pedido" role="note">
  <svg class="icono" aria-hidden="true" focusable="false"><use href="#i-candado"/></svg>
  <p><b>Consulta a pedido</b>La ficha es pública, pero el documento se consulta pidiéndolo al archivo. Está digitalizado.</p></div>
<div class="aviso aviso--ficha" role="note">
  <svg class="icono" …><use href="#i-ficha"/></svg>
  <p><b>Todavía no está digitalizado</b>El archivo se digitaliza por etapas; por ahora podés ver la ficha del catálogo.</p></div>
```
Variante `aviso--duda` disponible para avisos de fecha.

## 12. Bloque Documento y visor

En celular va arriba de la ficha; en escritorio, columna derecha fija (`position: sticky`). Tres estados:

```html
<aside class="ficha__documento documento" aria-labelledby="h-doc">
  <h2 id="h-doc">Documento</h2>
  <!-- digitalizado -->
  <div class="vista vista--hoja" aria-hidden="true"><!-- producción: <img src="…/miniatura.jpg" alt=""> --></div>
  <div class="acciones">…Ver documento (primario) + Descargar PDF (secundario, con peso)…</div>
  <!-- restringido -->
  <div class="vista vista--pedido" aria-hidden="true">…candado…</div>
  <div class="aviso aviso--pedido" role="note">…</div>
  <div class="acciones"><a class="boton boton--primario" href="/consulta/C2R11">…Pedir consulta de este documento</a></div>
  <!-- solo ficha -->
  <div class="vista vista--vacia" aria-hidden="true">…ficha…</div>
  <div class="aviso aviso--ficha" role="note">…</div>
  <div class="acciones"><a class="boton boton--secundario" href="/consulta/C14R12?motivo=original">…Consultar por este documento</a></div>
</aside>
```

Visor:

```html
<section id="visor" class="visor" hidden aria-label="Visor del documento C14R3">
  <div class="visor__barra" role="toolbar" aria-label="Controles del visor">
    <div class="grupo">
      <button class="visor__boton" type="button" data-pag="-1" aria-label="Página anterior" disabled>…izq…</button>
      <label class="visor__pagina"><span>Página</span> <input type="number" id="pag-actual" value="1" min="1" max="25"></label><span class="visor__pagina">de 25</span>
      <button class="visor__boton" type="button" data-pag="1" aria-label="Página siguiente">…der…</button>
    </div>
    <div class="grupo">…Alejar · Acercar · Ajustar al ancho · Pantalla completa (todos con aria-label)…</div>
    <span class="separador"></span>
    <div class="grupo"><a class="visor__boton" href="…/descargar" aria-label="Descargar PDF (105 MB)">…</a>
      <button class="visor__boton visor__boton--texto" type="button" id="cerrar-visor">…Cerrar</button></div>
  </div>
  <div class="visor__zona" tabindex="0" aria-label="Página del documento; con las flechas se cambia de página">
    <!-- producción: la versión web liviana del PDF -->
    <iframe class="visor__marco" src="/registro/C14R3/ver.pdf#page=1" title="C14R3, página 1"></iframe>
  </div>
  <p class="visor__pie"><kbd>←</kbd> <kbd>→</kbd> página · <kbd>+</kbd> <kbd>−</kbd> zoom · <kbd>Esc</kbd> cerrar</p>
</section>
```
- En la maqueta la página es un dibujo (`.visor__hoja`); en producción se reemplaza por `.visor__marco` (iframe al PDF web o a un visor PDF.js con la misma barra). Mantener la barra propia aunque el navegador traiga la suya: es la que respeta foco, teclado y la URL `#p=5`.
- Fondo oscuro (`marron-2`) para que la hoja resalte; foco ámbar.
- Al cerrar, el foco vuelve a «Ver documento». Pantalla completa con `requestFullscreen()` sobre `.visor`.

## 13. Paginación «Mostrar más»

```html
<div class="paginacion">
  <p class="meta" id="mostrando">Mostrando 40 de 99</p>
  <progress max="99" value="40" aria-labelledby="mostrando"></progress>
  <a class="boton boton--secundario" id="mas" href="/explorar?…&pagina=3">Mostrar 20 más</a>
</div>
```
Enlace real (funciona sin JS). Al final: `<p class="meta">Llegaste al final de la lista.</p>`.

## 14. Migas y vecinos

```html
<nav class="migas" aria-label="Estás en"><ol>
  <li><a href="/">Inicio</a></li><li><a href="/caja/14-zsherezade">Caja 14: ZSHEREZADE</a></li><li aria-current="page">C14R3</li>
</ol></nav>
<a class="volver" href="/explorar?…">…flechaIzq… Volver a los resultados</a>

<nav class="vecinos" aria-label="Documentos vecinos en la caja">
  <a href="/registro/C14R2">…flechaIzq…<span><small>Anterior · C14R2</small>"No te da verguenza"</span></a>
  <a class="sig" href="/registro/C14R4"><span><small>Siguiente · C14R4</small>"No te da verguenza"/ El Juego/ Zscherd zade/ Sobrevivientes</span>…flechaDer…</a>
</nav>
```
El separador de migas es un chevrón dibujado con CSS (no se lee).

## 15. Formulario

```html
<div class="resumen-errores" id="resumen-errores" hidden></div>
<form class="form" novalidate>
  <div class="campo"><label for="correo">Correo electrónico</label>
    <span class="ayuda" id="correo-ayuda">Te respondemos a este correo.</span>
    <input id="correo" type="email" autocomplete="email" aria-describedby="correo-ayuda correo-error">
    <p class="error" id="correo-error" hidden></p></div>
  <fieldset class="campo"><legend>¿Cómo preferís consultarlo?</legend>
    <label class="check"><input type="radio" name="modo" value="digital" checked> Copia digital por correo</label></fieldset>
</form>
```
Error: `aria-invalid="true"` en el campo (borde y barra izquierda roja), mensaje con ícono `alerta` en `.error`, y resumen arriba con enlaces a cada campo y foco en su título. Confirmación: `.confirmacion` con el número de consulta en `.numero-consulta`.

## 16. Estados vacío, error y cargando

```html
<div class="estado"><h2>No encontramos documentos con esa combinación</h2>…sugerencias con boton--fantasma…</div>
<div class="estado estado--error" role="alert"><h2>…alerta… No pudimos cargar los resultados</h2>…<button class="boton boton--primario">Reintentar</button></div>
<ul class="lista-registros" aria-busy="true"><li class="esqueleto"></li>…</ul>
```
