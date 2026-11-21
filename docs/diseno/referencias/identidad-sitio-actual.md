# Identidad del sitio actual de «Las cajas de Aída» (referencia para Diseño web)

Relevado del sitio en Lovable (14/09/2026) y de la guía de rediseño. Fuente: docs del Project de claude.ai
(`AIDA_design_tokens_sitio_actual.md`, `AIDA_guia_rediseño.md`).

## Tokens (HSL)

| Token | Valor | Uso |
|---|---|---|
| background | 40 33% 96% | fondo crema |
| foreground / ink | 25 30% 12% / 25 35% 15% | texto |
| card | 38 40% 93% | tarjetas |
| primary | 28 72% 52% | naranja de botones y acentos |
| secondary | 35 30% 85% | |
| muted / muted-foreground | 36 20% 90% / 25 15% 45% | (el gris tostado no llega a AA: usar #6B5D49 o más oscuro) |
| accent | 20 55% 42% | |
| border | 32 25% 82% | |
| warm-brown | 25 30% 22% | banda oscura de cabecera |
| amber-glow | 32 80% 58% | «de Aída» del logo |
| parchment | 39 45% 91% | |
| radius | .5rem | |

Tipografías: **Playfair Display** (títulos, itálica en «de Aída») e **Inter** (texto). El backoffice usa Courier Prime como guiño
a la máquina de escribir; en el sitio público queda a criterio de Diseño web (la v3 lo probó).

## Esencia que se conserva

- Sepia de archivo; hero con rollo de film, manuscritos y máquina de escribir.
- Kicker «ARCHIVO DIGITAL», título serif en dos líneas con la segunda en itálica ámbar.
- Frase del sitio: «Más de 70 cajas de un corpus creativo donde las palabras importan más que las imágenes y los sueños más que las ficciones».
- Acento naranja; banda marrón cálida para cabeceras.

## Etapas históricas (ya definidas en el sitio actual)

Inicios 1963–1976 · Exilio 1976–1979 · Regreso 1979–2013 · Caos Creativo 1963–2009 · Sobre Aída 1938–2013.
Hoy no están cargadas en la base nueva (tabla `periodo`): falta asignarlas a las cajas.

## Reglas acordadas

- Nada de texto redactado por P3DESIGN en las maquetas: el contenido sale del Excel o del sitio existente.
  Las notas propias van al pie, rotuladas «Notas de la maqueta».
- Una pantalla, una tarea. La ficha del registro tiene URL propia (`/registro/C3R5`).
- Mobile first. Contraste AA: el naranja de fondo con texto blanco solo pasa en tamaños grandes; para texto chico usar un naranja más oscuro (#8F4C18 en el backoffice).
- Mockups previos: v4 https://claude.ai/artifact/5TmMb27njiyx7antBRGKqB (referencia, el cliente puede cambiar el layout).
