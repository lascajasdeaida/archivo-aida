#!/usr/bin/env bash
# Restaura un backup de bin/backup.sh en una base.
#
#   bin/restaurar.sh backups-aida/aida_aida_2026-11-15_0331.sql.gz aida_restore
#   bin/restaurar.sh ARCHIVO BASE --forzar     si BASE ya tiene tablas (las reemplaza)
#
# Usa el usuario de config/config.php (la contraseña no aparece en `ps`). La base destino tiene
# que existir o el usuario tiene que poder crearla. Por seguridad, si la base destino ya tiene
# tablas no hace nada salvo con --forzar. Para la base del sitio: probar primero en otra base.
set -euo pipefail
umask 077

RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
ARCHIVO="${1:-}"; DESTINO="${2:-}"; FORZAR="${3:-}"
if [[ -z "$ARCHIVO" || -z "$DESTINO" || ( -n "$FORZAR" && "$FORZAR" != "--forzar" ) ]]; then
  echo "Uso: bin/restaurar.sh ARCHIVO.sql.gz BASE_DESTINO [--forzar]" >&2; exit 1
fi
[[ -f "$ARCHIVO" ]] || { echo "No existe $ARCHIVO" >&2; exit 1; }
[[ "$DESTINO" =~ ^[A-Za-z0-9_]+$ ]] || { echo "Nombre de base inválido: $DESTINO" >&2; exit 1; }

CLI="$(command -v mariadb || command -v mysql || true)"
[[ -n "$CLI" ]] || { echo "No encuentro el cliente mysql/mariadb." >&2; exit 1; }

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
php -r '
  $d = (require $argv[1])["db"];
  $esc = fn($v) => "\"" . addcslashes((string)$v, "\\\"") . "\"";
  $o = "[client]\nuser=" . $esc($d["user"]) . "\npassword=" . $esc($d["pass"]) . "\ndefault-character-set=utf8mb4\n";
  $o .= !empty($d["socket"]) ? "socket=" . $esc($d["socket"]) . "\n"
                              : "host=" . $esc($d["host"]) . "\nport=" . (int)($d["port"] ?? 3306) . "\n";
  file_put_contents($argv[2] . "/cliente.cnf", $o);
' "$RAIZ/config/config.php" "$TMP"
SQL=("$CLI" --defaults-extra-file="$TMP/cliente.cnf")

echo "Verificando $ARCHIVO…"
if [[ -f "$ARCHIVO.sha256" ]]; then
  (cd "$(dirname "$ARCHIVO")" && sha256sum -c --quiet "$(basename "$ARCHIVO").sha256") || { echo "La suma SHA-256 no coincide." >&2; exit 1; }
fi
gzip -t "$ARCHIVO"
zcat "$ARCHIVO" | tail -n 3 | grep -q 'Dump completed' || { echo "El backup está incompleto." >&2; exit 1; }

"${SQL[@]}" -e "CREATE DATABASE IF NOT EXISTS \`$DESTINO\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
TABLAS=$("${SQL[@]}" -N -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$DESTINO'")
if (( TABLAS > 0 )) && [[ "$FORZAR" != "--forzar" ]]; then
  echo "La base $DESTINO ya tiene $TABLAS tablas. No se toca nada (usar --forzar para reemplazarlas)." >&2
  exit 1
fi

INICIO=$(date +%s)
zcat "$ARCHIVO" | "${SQL[@]}" "$DESTINO"
echo "Restaurado en $DESTINO en $(( $(date +%s) - INICIO )) s."
"${SQL[@]}" -t "$DESTINO" -e "SELECT 'caja' AS tabla, COUNT(*) AS filas FROM caja
  UNION ALL SELECT 'registro', COUNT(*) FROM registro
  UNION ALL SELECT 'archivo_digital', COUNT(*) FROM archivo_digital
  UNION ALL SELECT 'auditoria', COUNT(*) FROM auditoria"
