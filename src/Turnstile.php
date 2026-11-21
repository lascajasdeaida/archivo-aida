<?php
declare(strict_types=1);

namespace Aida;

/**
 * Cloudflare Turnstile: verificación anti-robots en los formularios (sin rompecabezas: casi siempre pasa solo).
 *
 * config.php:
 *   'turnstile' => ['sitio' => '<site key>', 'secreto' => '<secret key>'],
 * Sin las dos claves queda apagado: los formularios funcionan como antes (con campo trampa, tiempo mínimo y límites).
 * Claves de prueba de Cloudflare (siempre aprueban): sitio 1x00000000000000000000AA, secreto 1x0000000000000000000000000000000AA.
 *
 * Si Cloudflare no responde (caída o red), se deja pasar y se anota en el log: un corte de Cloudflare no puede dejar
 * a nadie afuera del backoffice ni perder una consulta. Un token inválido o vencido, en cambio, se rechaza.
 */
final class Turnstile
{
    private const API = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    private const VERIFICAR = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    private static bool $scriptPuesto = false;

    public static function activo(): bool
    {
        $c = aida_config()['turnstile'] ?? [];
        return (string)($c['sitio'] ?? '') !== '' && (string)($c['secreto'] ?? '') !== '';
    }

    public static function claveSitio(): string
    {
        return (string)(aida_config()['turnstile']['sitio'] ?? '');
    }

    /** HTML del widget (y del script, una sola vez por página). Vacío si está apagado. */
    public static function widget(string $accion, string $tema = 'light'): string
    {
        if (!self::activo()) {
            return '';
        }
        $html = '<div class="cf-turnstile" data-sitekey="' . htmlspecialchars(self::claveSitio(), ENT_QUOTES) . '"'
              . ' data-action="' . htmlspecialchars($accion, ENT_QUOTES) . '" data-language="es" data-theme="' . htmlspecialchars($tema, ENT_QUOTES) . '"'
              . ' data-size="flexible"></div>';
        if (!self::$scriptPuesto) {
            self::$scriptPuesto = true;
            $html .= '<script src="' . self::API . '" async defer></script>';
        }
        return $html;
    }

    /** true si el envío pasó la verificación (o si Turnstile está apagado). */
    public static function verificar(string $accion): bool
    {
        if (!self::activo()) {
            return true;
        }
        $token = (string)($_POST['cf-turnstile-response'] ?? '');
        if ($token === '' || strlen($token) > 2048) {
            return false;
        }
        $datos = http_build_query([
            'secret' => (string)aida_config()['turnstile']['secreto'],
            'response' => $token,
            'remoteip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);
        $r = self::post($datos);
        if ($r === null) {
            error_log('[aida-turnstile] sin respuesta de Cloudflare: se deja pasar');
            return true;
        }
        $j = json_decode($r, true);
        if (!is_array($j) || empty($j['success'])) {
            return false;
        }
        // La acción tiene que coincidir (evita reusar un token de otro formulario). Las claves de prueba no la devuelven.
        return !isset($j['action']) || $j['action'] === '' || $j['action'] === $accion;
    }

    private static function post(string $datos): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init(self::VERIFICAR);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $datos, CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 6]);
            $r = curl_exec($ch);
            $ok = $r !== false && curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200;
            return $ok ? (string)$r : null;
        }
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 6,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $datos]]);
        $r = @file_get_contents(self::VERIFICAR, false, $ctx);
        return $r === false ? null : $r;
    }
}
