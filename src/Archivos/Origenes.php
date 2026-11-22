<?php
declare(strict_types=1);

namespace Aida\Archivos;

/**
 * Arma el origen de archivos según config/config.php:
 *
 *   'archivos' => ['origen' => 'disco', 'raiz' => '/var/www/aida-archivos']
 *   'archivos' => ['origen' => 'drive', 'drive' => ['listado' => '…/listado.tsv', 'visor_max_mb' => 100]]
 *
 * Sin 'archivos' vale la forma anterior: disco con 'archivos_raiz'.
 */
final class Origenes
{
    public const DISCO = 'disco';
    public const DRIVE = 'drive';

    public static function desdeConfig(array $cfg): Origen
    {
        $a = is_array($cfg['archivos'] ?? null) ? $cfg['archivos'] : [];
        $origen = (string)($a['origen'] ?? self::DISCO);
        $base = match ($origen) {
            self::DISCO => new Disco((string)($a['raiz'] ?? $cfg['archivos_raiz'] ?? '')),
            self::DRIVE => self::drive($cfg),
            default => throw new \RuntimeException("Origen de archivos desconocido en la configuración: “{$origen}” (disco o drive)"),
        };
        // Versiones livianas (bin/livianos.php): si están configuradas, el visor y la descarga usan esas.
        $liv = (string)($a['livianos']['raiz'] ?? '');
        if ($liv !== '' && is_dir($liv)) {
            $max = (int)round(((float)($a['drive']['visor_max_mb'] ?? 100)) * 1000 * 1000);
            return new ConLivianos($base, $liv, $max);
        }
        return $base;
    }

    /** El origen Drive aunque la configuración diga disco (para cargar los id antes de cambiar de origen). */
    public static function drive(array $cfg): Drive
    {
        $d = is_array($cfg['archivos']['drive'] ?? null) ? $cfg['archivos']['drive'] : [];
        return new Drive(
            (string)($d['listado'] ?? dirname(__DIR__, 2) . '/var/drive/listado.tsv'),
            (int)round(((float)($d['visor_max_mb'] ?? 100)) * 1000 * 1000),
        );
    }

    /** Cajas físicas del archivo, para proyectar el espacio (informe de Almacenamiento). */
    public static function cajasTotal(array $cfg): int
    {
        return max(1, (int)($cfg['archivos']['cajas_total'] ?? 70));
    }
}
