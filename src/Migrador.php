<?php
declare(strict_types=1);

namespace Aida;

/**
 * Migraciones de db/migraciones/ (NNN_descripcion.sql). Lo usan bin/migrar.php y la pantalla
 * «Base de datos» del backoffice (para hostings sin consola). Cada migración es idempotente.
 */
final class Migrador
{
    private const CREAR_TABLA = "CREATE TABLE IF NOT EXISTS migracion (
  version      CHAR(3)      NOT NULL,
  nombre       VARCHAR(120) NOT NULL,
  checksum     CHAR(40)     NULL,
  duracion_ms  INT UNSIGNED NULL,
  aplicada_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    /**
     * Pasos en PHP que acompañan a una migración (lo que SQL solo no puede calcular). Se corren después
     * de sus sentencias y antes de registrarla; tienen que ser idempotentes como el SQL.
     */
    private const POSTERIORES = [
        '004' => 'reconstruirAnios',   // registro_anio: listas salteadas con Texto::anio()
    ];

    /** @var array<string,string> version => ruta */
    public array $archivos = [];
    /** @var list<string> */
    public array $ignorados = [];

    public function __construct(private \PDO $db, ?string $dir = null)
    {
        $dir ??= dirname(__DIR__) . '/db/migraciones';
        foreach (glob("$dir/*.sql") ?: [] as $f) {
            if (preg_match('/^(\d{3})_[a-z0-9_]+\.sql$/', basename($f), $m)) {
                $this->archivos[$m[1]] = $f;
            } else {
                $this->ignorados[] = basename($f);
            }
        }
        ksort($this->archivos, SORT_STRING);
    }

    public function hayTabla(): bool
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'migracion'")->fetchColumn() > 0;
    }

    /** @return array<string,array{version:string,nombre:string,checksum:?string,aplicada_en:string}> */
    public function aplicadas(): array
    {
        if (!$this->hayTabla()) {
            return [];
        }
        $out = [];
        foreach ($this->db->query('SELECT version, nombre, checksum, aplicada_en FROM migracion') as $r) {
            $out[$r['version']] = $r;
        }
        return $out;
    }

    /** @return array<string,string> */
    public function pendientes(): array
    {
        return array_diff_key($this->archivos, $this->aplicadas());
    }

    /** Migraciones aplicadas cuyo archivo cambió después (no deberían editarse). @return list<string> */
    public function modificadas(): array
    {
        $out = [];
        foreach ($this->aplicadas() as $v => $r) {
            if (isset($this->archivos[$v]) && $r['checksum'] !== null && $r['checksum'] !== sha1_file($this->archivos[$v])) {
                $out[] = basename($this->archivos[$v]);
            }
        }
        return $out;
    }

    /**
     * Aplica una migración. Lanza \RuntimeException con la sentencia que falló.
     * @return int duración en ms
     */
    public function aplicar(string $version): int
    {
        $f = $this->archivos[$version] ?? throw new \RuntimeException("No existe la migración $version");
        $this->db->exec(self::CREAR_TABLA);
        $t0 = hrtime(true);
        foreach (self::sentencias((string)file_get_contents($f)) as $i => $s) {
            try {
                $st = $this->db->query($s);
                while ($st->columnCount() && $st->fetch()) {
                }
                $st->closeCursor();
            } catch (\PDOException $e) {
                throw new \RuntimeException('Falló la sentencia ' . ($i + 1) . ' de ' . basename($f) . ': ' . $e->getMessage()
                    . "\n" . mb_strimwidth($s, 0, 300, '…'), 0, $e);
            }
        }
        if (isset(self::POSTERIORES[$version])) {
            try {
                $this->{self::POSTERIORES[$version]}();
            } catch (\Throwable $e) {
                throw new \RuntimeException('Falló el paso posterior de ' . basename($f) . ': ' . $e->getMessage(), 0, $e);
            }
        }
        $ms = (int)((hrtime(true) - $t0) / 1e6);
        $this->db->prepare('INSERT INTO migracion (version, nombre, checksum, duracion_ms) VALUES (?,?,?,?)')
           ->execute([$version, basename($f), sha1_file($f), $ms]);
        return $ms;
    }

    private function reconstruirAnios(): void
    {
        (new Anios($this->db))->reconstruir();
    }

    /**
     * Parte un archivo SQL en sentencias. Respeta comillas y comentarios (-- y /* *\/),
     * así un «;» dentro de un texto no corta la sentencia. No admite DELIMITER (no usamos
     * triggers ni procedimientos: muchos hostings no los permiten).
     * @return list<string>
     */
    public static function sentencias(string $sql): array
    {
        $out = [];
        $buf = '';
        $n = strlen($sql);
        for ($i = 0; $i < $n; $i++) {
            $c = $sql[$i];
            $sig = $sql[$i + 1] ?? '';
            if ($c === '-' && $sig === '-' && (($sql[$i + 2] ?? ' ') === ' ' || ($sql[$i + 2] ?? '') === "\n")) {
                $fin = strpos($sql, "\n", $i);
                $i = $fin === false ? $n : $fin;
                $buf .= "\n";
                continue;
            }
            if ($c === '/' && $sig === '*') {
                $fin = strpos($sql, '*/', $i + 2);
                $i = $fin === false ? $n : $fin + 1;
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $j = $i + 1;
                while ($j < $n) {
                    if ($sql[$j] === '\\' && $c !== '`') {
                        $j += 2;
                        continue;
                    }
                    if ($sql[$j] === $c) {
                        if (($sql[$j + 1] ?? '') === $c) { // comilla duplicada
                            $j += 2;
                            continue;
                        }
                        break;
                    }
                    $j++;
                }
                $buf .= substr($sql, $i, $j - $i + 1);
                $i = $j;
                continue;
            }
            if ($c === ';') {
                if (trim($buf) !== '') {
                    $out[] = trim($buf);
                }
                $buf = '';
                continue;
            }
            $buf .= $c;
        }
        if (trim($buf) !== '') {
            $out[] = trim($buf);
        }
        return $out;
    }
}
