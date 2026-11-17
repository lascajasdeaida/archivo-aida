<?php
declare(strict_types=1);

namespace Aida;

/**
 * Envío de correos del sistema (por ahora, solo los enlaces de acceso al backoffice).
 * Usa mail() de PHP, que en el VPS sale por el Postfix de Virtualmin.
 *
 * config.php:
 *   'correo' => ['remitente' => 'no-responder@lascajasdeaida.com.ar', 'nombre' => 'Las cajas de Aída'],
 * Sin remitente configurado no se manda nada (enviar() devuelve false) y el backoffice muestra el enlace para copiarlo.
 */
final class Correo
{
    public static function configurado(): bool
    {
        return (string)(aida_config()['correo']['remitente'] ?? '') !== '';
    }

    public static function enviar(string $para, string $asunto, string $texto): bool
    {
        $cfg = aida_config()['correo'] ?? [];
        $de = (string)($cfg['remitente'] ?? '');
        if ($de === '' || !filter_var($para, FILTER_VALIDATE_EMAIL) || !filter_var($de, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $nombre = (string)($cfg['nombre'] ?? 'Las cajas de Aída');
        $cab = [
            'From' => mb_encode_mimeheader($nombre, 'UTF-8') . " <$de>",
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
            'Auto-Submitted' => 'auto-generated',
        ];
        try {
            return mail($para, mb_encode_mimeheader($asunto, 'UTF-8'), $texto, $cab, '-f' . $de);
        } catch (\Throwable $e) {
            error_log('[aida-correo] ' . $e->getMessage());
            return false;
        }
    }
}
