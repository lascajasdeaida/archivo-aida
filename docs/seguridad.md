# Revisión de seguridad (27/11/2026)

Informe de Omar (26/11/2026) sobre la rama `backoffice`. Todos los puntos se corrigieron en la rama `seguridad`.
Controles que el informe ya encontró bien resueltos: SQL parametrizado, escape XSS, CSRF, acceso a disco solo por id,
inyección de fórmulas en CSV, Host Header en recuperación, límites de las planillas .xlsx.

| Punto | Qué pasaba | Corrección |
|---|---|---|
| **A1** | `archivo.php` y el botón «Editar» del sitio usaban los permisos guardados en la sesión sin revalidar | `App::sesionInterna()`: abre la sesión solo si llega la cookie, la revalida contra la base y, si no es válida, no deja sesión ni `Set-Cookie` |
| **A2** | Cambiar correo o perfil dejaba vigentes los enlaces de alta / restablecer | `Usuarios::actualizar()` anula enlaces y cierra sesiones si cambia correo, perfil o estado |
| **A3** | «Deshacer» solo miraba propio / de otros | `Rutas::motivoSinDeshacer()` valida entidad, acción y campos en el servidor (docs/backoffice.md §5) |
| **A4** | La revocación comparaba el reloj de PHP con un `DATETIME` de la base | `usuario.sesion_version` (migración 010), comparado por igualdad exacta |
| **M1** | Carga de datos podía desbloquear «acceso» (y cualquier nombre de campo) | Solo campos que estén bloqueados; «acceso» exige permiso de acceso |
| **M2** | Carga de datos podía publicar o despublicar fichas sueltas; sus altas nacían publicadas | Exige permiso de publicar; las altas de carga de datos nacen sin publicar (decisión del PM) |
| **M3** | Un error de base al revalidar dejaba seguir con los permisos viejos | Falla cerrado: la sesión se cierra |
| **M4** | Sin límite de intentos de ingreso | Tabla `intento`: bloqueo progresivo por correo y por IP (docs/backoffice.md §17) |
| **M5** | Explorar repetía la búsqueda hasta 10 veces (lista acumulada) y una vez por chip sin resultados | Lista acumulada en una sola búsqueda (`Buscador::buscar(..., $antes)`); sugerencias para 6 chips como mucho |
| **M6** | El secreto HMAC podía quedar vacío o leerse a medio escribir | Archivo temporal + `link()` atómico, mínimo 32 caracteres; si falla, error (nunca firma con clave vacía) |
| Privacidad 1 | Un texto desconocido en PRIVADO se importaba como público | Queda `a_revisar` (docs/decisiones.md, Acceso) |
| Privacidad 2 | La confirmación PRIVADO → público solo miraba `VERDADERO` | `Texto::marcaPrivada()` en la ficha, la corrección masiva y deshacer |
| Privacidad 3 | Una ficha nunca publicada respondía 410 y revelaba que el código existe | 404; 410 solo si estuvo publicada y se retiró (con registro en el historial) |
| Endurecimiento 4 | Límite de la Muestra en archivo, no atómico y con falla abierta | Tabla `intento` (5 por hora por IP) |
| Endurecimiento 5 | `SitioPublico::equipo()` sin revalidar y creando sesiones con cookies inventadas | Usa `App::sesionInterna()` (A1) |
| Endurecimiento 6 | «Olvidé mi contraseña» sin límite por IP | 5 por hora por IP |
| Endurecimiento 7 | `bin/crear-usuario.php` no anulaba enlaces ni cerraba sesiones | Usa `Aida\Usuarios` (normaliza el correo, valida la clave, anula y cierra) |

## Al desplegar

1. `php bin/migrar.php --simular` y después `php bin/migrar.php` (aplica la 010).
2. Las sesiones abiertas antes del despliegue se cierran una vez (no tienen versión): hay que volver a ingresar.

## Cómo se probó

Contra la base local con el servidor embebido: cambio de perfil, de correo y de contraseña (en el mismo segundo)
cortan la sesión; `archivo.php` con una sesión dada de baja responde 403 «a pedido»; cookie inventada sin `Set-Cookie`;
el 6.º ingreso fallido queda bloqueado aun con la clave correcta; data_entry recibe 403 al deshacer su alta, al
desbloquear «acceso» y no cambia `publicado`; un admin degradado no puede revertir su cambio de acceso; deshacer que
vuelve público algo con marca en PRIVADO se rechaza; 404 para fichas de cajas sin publicar y a revisar, 410 para una
retirada; la lista acumulada de Explorar da el mismo resultado que antes; 8 procesos simultáneos crean un único secreto;
la simulación de importación del Excel completo no cambia ningún acceso.
