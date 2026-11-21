#!/usr/bin/env bash
# =====================================================================
# Lanzamiento: pasar el sitio y el archivo del dominio provisorio al definitivo (docs/lanzamiento.md).
#
#   bash bin/lanzamiento.sh --simular    muestra qué cambiaría, sin tocar nada (empezar siempre por acá)
#   bash bin/lanzamiento.sh --aplicar    hace los cambios (guarda copia de todo lo que toca)
#   bash bin/lanzamiento.sh --revertir   vuelve a las copias de la última aplicación
#
# Opciones: --https (default) o --http, según si ya está el certificado; --dominio otro.dominio.
# Corre como el usuario del dominio en el VPS, desde ~/archivo-app. No necesita root.
# Qué hace:
#   1. config/config.php: sitio.url y backoffice.url al dominio definitivo.
#   2. public_html/.htaccess: saca el noindex (X-Robots-Tag); con --https, redirige http→https y www→sin www.
#   3. public_html/robots.txt: permite indexar y apunta al sitemap.
#   4. HTML del sitio institucional: la imagen para compartir (og:image) pasa al dominio definitivo.
# Qué NO hace (ver docs/lanzamiento.md): DNS, certificado, claves reales de Turnstile, filtro de GA4.
# =====================================================================
set -euo pipefail

MODO=""; ESQUEMA="https"; DOMINIO="lascajasdeaida.com.ar"
PROVISORIO="lascajasdeaida.com.ar.vcct10010.avnam.net"
while [ $# -gt 0 ]; do
  case "$1" in
    --simular|--aplicar|--revertir) MODO="${1#--}";;
    --https) ESQUEMA="https";; --http) ESQUEMA="http";;
    --dominio) DOMINIO="$2"; shift;;
    *) echo "Opción desconocida: $1" >&2; exit 2;;
  esac; shift
done
[ -n "$MODO" ] || { sed -n '4,8p' "$0"; exit 2; }

APP="$HOME/archivo-app"; WEB="$HOME/public_html"; COPIAS="$HOME/backups-aida/lanzamiento"
CONF="$APP/config/config.php"; HT="$WEB/.htaccess"; ROB="$WEB/robots.txt"
BASE="$ESQUEMA://$DOMINIO"

if [ "$MODO" = "revertir" ]; then
  ULT=$(ls -1d "$COPIAS"/2* 2>/dev/null | tail -1)
  [ -n "$ULT" ] || { echo "No hay copias en $COPIAS"; exit 1; }
  echo "Restaurando desde $ULT"
  cp -p "$ULT/config.php" "$CONF"; cp -p "$ULT/htaccess" "$HT"; cp -p "$ULT/robots.txt" "$ROB"
  tar xzf "$ULT/html.tgz" -C "$WEB"
  echo "Listo: volvió todo al estado anterior al lanzamiento."; exit 0
fi

echo "== Lanzamiento ($MODO) → $BASE"

# 1. config.php ------------------------------------------------------
echo "-- config.php"
grep -n "$PROVISORIO" "$CONF" | sed -E "s#'pass' => '[^']*'#'pass' => '***'#" || echo "   (no menciona el dominio provisorio)"
# 2. .htaccess --------------------------------------------------------
NUEVO_HT=$(mktemp)
{
  echo "# Lanzamiento $(date +%d/%m/%Y): sitio público e indexable."
  if [ "$ESQUEMA" = "https" ]; then cat <<EOF
<IfModule mod_rewrite.c>
  RewriteEngine On
  # Todo a https://$DOMINIO (sin www). El dominio provisorio sigue andando por http, sin redirigir.
  # (dominio propio) Y (sin https O no es exactamente $DOMINIO)
  RewriteCond %{HTTP_HOST} ^(www\.)?(lascajasdeaida\.com\.ar|lascajasdeaida\.com)$ [NC]
  RewriteCond %{HTTPS} off [OR]
  RewriteCond %{HTTP_HOST} !^${DOMINIO//./\\.}\$ [NC]
  RewriteRule ^ https://$DOMINIO%{REQUEST_URI} [R=301,L]
</IfModule>
EOF
  fi
  cat <<'EOF'
<IfModule mod_headers.c>
  Header always unset X-Powered-By
  Header unset X-Powered-By
  # El dominio provisorio queda sin indexar aunque siga respondiendo.
  <If "%{HTTP_HOST} =~ /avnam\.net$/">
    Header always set X-Robots-Tag "noindex, nofollow, noarchive"
  </If>
  <If "%{REQUEST_URI} !~ m#^/archivo/#">
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
  </If>
</IfModule>
EOF
} > "$NUEVO_HT"
echo "-- .htaccess nuevo:"; sed 's/^/   /' "$NUEVO_HT"
# 3. robots.txt -------------------------------------------------------
NUEVO_ROB=$(mktemp)
printf 'User-agent: *\nAllow: /\nDisallow: /archivo/admin/\n\nSitemap: %s/sitemap.xml\n' "$BASE" > "$NUEVO_ROB"
echo "-- robots.txt nuevo:"; sed 's/^/   /' "$NUEVO_ROB"
# 4. og:image ---------------------------------------------------------
HTMLS=$(grep -l "http://$PROVISORIO/" "$WEB"/*.html 2>/dev/null || true)
echo "-- HTML con la imagen para compartir en el provisorio: $(echo "$HTMLS" | grep -c . || true) archivos"

if [ "$MODO" = "simular" ]; then
  rm -f "$NUEVO_HT" "$NUEVO_ROB"
  echo "SIMULACIÓN: no se cambió nada. Para aplicar: bash bin/lanzamiento.sh --aplicar"; exit 0
fi

# Aplicar ---------------------------------------------------------------
DEST="$COPIAS/$(date +%Y%m%d-%H%M%S)"; mkdir -p "$DEST"; chmod 700 "$COPIAS" "$DEST"
cp -p "$CONF" "$DEST/config.php"; cp -p "$HT" "$DEST/htaccess"; cp -p "$ROB" "$DEST/robots.txt"
( cd "$WEB" && tar czf "$DEST/html.tgz" ./*.html )
sed -i "s#http://$PROVISORIO/archivo/admin#$BASE/archivo/admin#g; s#http://$PROVISORIO/archivo#$BASE/archivo#g" "$CONF"
php -l "$CONF" >/dev/null
cp "$NUEVO_HT" "$HT"; cp "$NUEVO_ROB" "$ROB"; chmod 644 "$HT" "$ROB"; rm -f "$NUEVO_HT" "$NUEVO_ROB"
[ -z "$HTMLS" ] || sed -i "s#http://$PROVISORIO/#$BASE/#g" $HTMLS
echo "Listo. Copias en $DEST (para volver atrás: bash bin/lanzamiento.sh --revertir)."
