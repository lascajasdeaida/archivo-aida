<?php
declare(strict_types=1);

namespace Aida;

/**
 * Freno de abusos guardado en la base (tabla intento): ingresos fallidos por correo y por IP, «Olvidé mi contraseña»
 * por IP y formulario de la Muestra por IP. Solo se guarda el hash SHA-256 de la clave (correo o IP).
 *
 * Cada intento es una fila nueva (INSERT), así que dos pedidos simultáneos no se pisan la cuenta.
 */
final class Intentos
{
    public function __construct(private \PDO $db)
    {
    }

    /** IP del pedido. No se confía en X-Forwarded-For: el servidor atiende directo, sin proxy delante. */
    public static function ip(): string
    {
        return (string)($_SERVER['REMOTE_ADDR'] ?? '');
    }

    public function registrar(string $tipo, string $valor): void
    {
        $this->db->prepare('INSERT INTO intento (tipo, clave) VALUES (?, ?)')->execute([$tipo, self::hash($valor)]);
        if (random_int(1, 50) === 1) {
            $this->db->exec('DELETE FROM intento WHERE creado_en < DATE_SUB(NOW(), INTERVAL 2 DAY)');
        }
    }

    /** Intentos de este tipo y clave en los últimos $minutos. */
    public function contar(string $tipo, string $valor, int $minutos): int
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM intento WHERE tipo = ? AND clave = ? AND creado_en > DATE_SUB(NOW(), INTERVAL ? MINUTE)');
        $st->execute([$tipo, self::hash($valor), $minutos]);
        return (int)$st->fetchColumn();
    }

    public function limpiar(string $tipo, string $valor): void
    {
        $this->db->prepare('DELETE FROM intento WHERE tipo = ? AND clave = ?')->execute([$tipo, self::hash($valor)]);
    }

    /**
     * ¿El ingreso está bloqueado para este correo o esta IP? Bloqueo progresivo:
     *   por correo: 5 fallidos en 15 min → espera de 15 min; 10 en 1 h → 1 h; 20 en 24 h → 24 h.
     *   por IP:     20 fallidos en 15 min (cualquier cuenta) → espera de 15 min; 50 en 1 h → 1 h.
     */
    public function loginBloqueado(string $email): bool
    {
        $ip = self::ip();
        return $this->contar('login_email', $email, 15) >= 5
            || $this->contar('login_email', $email, 60) >= 10
            || $this->contar('login_email', $email, 1440) >= 20
            || $this->contar('login_ip', $ip, 15) >= 20
            || $this->contar('login_ip', $ip, 60) >= 50;
    }

    public function loginFallido(string $email): void
    {
        $this->registrar('login_email', $email);
        $this->registrar('login_ip', self::ip());
    }

    public function loginCorrecto(string $email): void
    {
        $this->limpiar('login_email', $email);
    }

    /**
     * Cuenta un pedido y dice si se pasó del límite ($max en $minutos) para la IP actual.
     * El pedido que excede no se cuenta, para que el bloqueo no se alargue solo.
     */
    public function excedeIp(string $tipo, int $max, int $minutos): bool
    {
        $ip = self::ip();
        if ($this->contar($tipo, $ip, $minutos) >= $max) {
            return true;
        }
        $this->registrar($tipo, $ip);
        return false;
    }

    private static function hash(string $valor): string
    {
        return hash('sha256', mb_strtolower(trim($valor)));
    }
}
