#!/usr/bin/env bash
# Backup de la base: dump completo, comprimido, verificado y con rotación.
#
#   bin/backup.sh                 hace el backup (lo normal: desde cron, una vez por semana)
#   bin/backup.sh --simular       muestra qué haría, sin conectarse ni borrar nada
#
# Lee la conexión de config/config.php. La contraseña no aparece en `ps`: va en un archivo
# temporal con permisos 600 que se pasa con --defaults-extra-file y se borra al terminar.
#
# Opcionales en config/config.php:
#   'backup' => ['dir' => '/home/usuario/backups-aida', 'conservar' => 8],
# Por defecto: carpeta backups/ al lado del repositorio (fuera de public/) y 8 copias.
# Ver docs/backups.md (restauración, cron y archivos PDF).
set -euo pipefail
umask 077

RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
SIMULAR=0
[[ "${1:-}" == "--simular" ]] && SIMULAR=1
[[ $# -gt 0 && "${1:-}" != "--simular" ]] && { echo "Uso: bin/backup.sh [--simular]" >&2; exit 1; }

DUMP="$(command -v mariadb-dump || command -v mysqldump || true)"
[[ -n "$DUMP" ]] || { echo "No encuentro mysqldump ni mariadb-dump." >&2; exit 1; }

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

# Datos de config.php → archivo de opciones (la contraseña nunca pasa por la línea de comandos)
php -r '
  $c = require $argv[1];
  $d = $c["db"];
  $b = $c["backup"] ?? [];
  $esc = fn($v) => "\"" . addcslashes((string)$v, "\\\"") . "\"";
  $o = "[client]\nuser=" . $esc($d["user"]) . "\npassword=" . $esc($d["pass"]) . "\n";
  $o .= !empty($d["socket"]) ? "socket=" . $esc($d["socket"]) . "\n"
                              : "host=" . $esc($d["host"]) . "\nport=" . (int)($d["port"] ?? 3306) . "\n";
  file_put_contents($argv[2] . "/cliente.cnf", $o);
  echo $d["name"], "\n", $b["dir"] ?? "", "\n", (int)($b["conservar"] ?? 8), "\n";
' "$RAIZ/config/config.php" "$TMP" > "$TMP/datos"
{ read -r BASE; read -r DIR; read -r CONSERVAR; } < "$TMP/datos"
DIR="${DIR:-$(dirname "$RAIZ")/backups-aida}"
(( CONSERVAR >= 1 )) || CONSERVAR=8
[[ "$BASE" =~ ^[A-Za-z0-9_]+$ ]] || { echo "Nombre de base inválido: $BASE" >&2; exit 1; }

ARCHIVO="$DIR/aida_${BASE}_$(date +%Y-%m-%d_%H%M%S).sql.gz"

# Opciones que sirven en MySQL 8 y en MariaDB:
#  --single-transaction  copia consistente sin bloquear el sitio (todo es InnoDB)
#  --no-tablespaces      MySQL 8 pide el privilegio PROCESS sin esto; en un hosting no lo hay
#  --hex-blob, utf8mb4   textos con tildes y JSON intactos
#  sin --databases: el dump no trae CREATE DATABASE/USE y se puede restaurar en otra base
#  sin --routines/--events: no usamos y en un hosting suelen faltar los permisos
OPCIONES=(--single-transaction --quick --no-tablespaces --hex-blob --default-character-set=utf8mb4
          --triggers --add-drop-table)
# El mysqldump de MySQL agrega GTID_PURGED si el servidor usa GTID; eso rompe la restauración en otra base.
if "$DUMP" --help 2>/dev/null | grep -q -- '--set-gtid-purged'; then
  OPCIONES+=(--set-gtid-purged=OFF)
fi

if (( SIMULAR )); then
  echo "SIMULACIÓN — no se conecta ni borra nada."
  echo "Base:        $BASE"
  echo "Programa:    $DUMP"
  echo "Archivo:     $ARCHIVO"
  echo "Conservar:   $CONSERVAR copias en $DIR"
  if [[ -d "$DIR" ]]; then
    mapfile -t VIEJOS < <(ls -1t "$DIR"/aida_"${BASE}"_*.sql.gz 2>/dev/null | tail -n +"$CONSERVAR")
    echo "Se borrarían después del backup: ${#VIEJOS[@]} ${VIEJOS[*]:-}"
  fi
  exit 0
fi

mkdir -p "$DIR"
exec 9>"$DIR/.backup.lock"
flock -n 9 || { echo "Ya hay un backup corriendo." >&2; exit 1; }

PARCIAL="$ARCHIVO.parcial"
INICIO=$(date +%s)
"$DUMP" --defaults-extra-file="$TMP/cliente.cnf" "${OPCIONES[@]}" "$BASE" 2> "$TMP/errores" \
  | gzip -6 > "$PARCIAL" || {
    echo "Falló el dump:" >&2; cat "$TMP/errores" >&2; rm -f "$PARCIAL"; exit 1; }

# Verificación: el gzip está sano y el dump llegó al final
gzip -t "$PARCIAL"
if ! zcat "$PARCIAL" | tail -n 3 | grep -q 'Dump completed'; then
  echo "El dump quedó incompleto; no se rota nada." >&2; cat "$TMP/errores" >&2; rm -f "$PARCIAL"; exit 1
fi
mv "$PARCIAL" "$ARCHIVO"
sha256sum "$ARCHIVO" > "$ARCHIVO.sha256"

# Rotación: solo después de un backup bueno, se conservan los $CONSERVAR más nuevos
mapfile -t VIEJOS < <(ls -1t "$DIR"/aida_"${BASE}"_*.sql.gz | tail -n +"$((CONSERVAR + 1))")
for f in "${VIEJOS[@]}"; do rm -f -- "$f" "$f.sha256"; done

TAM=$(du -h "$ARCHIVO" | cut -f1)
echo "Backup listo: $ARCHIVO ($TAM, $(( $(date +%s) - INICIO )) s). Borrados por rotación: ${#VIEJOS[@]}."
