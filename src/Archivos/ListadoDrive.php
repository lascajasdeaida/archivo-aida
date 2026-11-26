<?php
declare(strict_types=1);

namespace Aida\Archivos;

/**
 * Listado de los archivos del Drive del cliente (TSV o CSV), mientras no haya acceso por API.
 *
 * Una línea por archivo: ruta, id de Drive, bytes y, opcional, si está compartido “cualquiera con el enlace”.
 *
 *   ruta          C1/C1R31.pdf (relativa a la carpeta “Selección de Cajas”, como en el disco)
 *   drive_id      el id del archivo (o su enlace: https://drive.google.com/file/d/ID/view)
 *   bytes         tamaño en bytes
 *   compartido    sí/no (también VERDADERO/FALSO, 1/0, “cualquiera con el enlace”, “restringido”); vacío = sin dato
 *
 * Con encabezado, las columnas se ubican por nombre (ruta, id|drive_id, bytes|peso|tamaño, compartido|publico) y en
 * cualquier orden. Sin encabezado, van en ese orden. Las líneas que empiezan con # se ignoran.
 * El formato viejo de dos columnas (ruta, bytes) también se lee: una línea sin id no borra el id guardado; si el archivo
 * no tenía, queda sin id (DRIVE_SIN_ID).
 */
final class ListadoDrive
{
    private const ID = '/^[A-Za-z0-9_-]{10,200}$/';

    private const NOMBRES = [
        'ruta' => ['ruta', 'path', 'archivo', 'nombre'],
        'drive_id' => ['drive_id', 'id', 'id_drive', 'id de drive', 'file_id', 'fileid', 'enlace', 'link', 'url'],
        'bytes' => ['bytes', 'peso', 'peso_bytes', 'tamaño', 'tamano', 'size'],
        'compartido' => ['compartido', 'publico', 'público', 'cualquiera con el enlace', 'shared', 'compartido_enlace'],
    ];

    /**
     * @return array{filas: list<array{ruta:string, bytes:?int, mtime:null, drive_id:?string, compartido:?bool, linea:int}>, errores: list<string>}
     */
    public static function leer(string $archivo): array
    {
        $texto = @file_get_contents($archivo);
        if ($texto === false) {
            throw new \RuntimeException('No se pudo leer el listado de Drive.');
        }
        return self::interpretar($texto);
    }

    /** @return array{filas: list<array>, errores: list<string>} */
    public static function interpretar(string $texto): array
    {
        $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto) ?? $texto;
        if (!mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
        }
        $lineas = preg_split('/\r\n|\r|\n/', $texto) ?: [];
        $sep = null;
        $cols = null;
        $filas = [];
        $errores = [];
        $vistas = [];
        foreach ($lineas as $i => $linea) {
            $n = $i + 1;
            if (trim($linea) === '' || str_starts_with(ltrim($linea), '#')) {
                continue;
            }
            $sep ??= str_contains($linea, "\t") ? "\t" : (substr_count($linea, ';') > substr_count($linea, ',') ? ';' : ',');
            $p = array_map('trim', $sep === "\t" ? explode("\t", $linea) : str_getcsv($linea, $sep, '"', ''));
            if ($cols === null) {
                $cols = self::encabezado($p);
                if ($cols !== false) {
                    if (!isset($cols['ruta'])) {
                        throw new \RuntimeException('El encabezado del listado no tiene la columna “ruta”.');
                    }
                    continue;
                }
            }
            $campo = function (string $k, int $pos) use ($cols, $p): string {
                $idx = $cols ? ($cols[$k] ?? null) : $pos;
                return $idx === null ? '' : (string)($p[$idx] ?? '');
            };
            $ruta = self::ruta($campo('ruta', 0));
            if ($ruta === null) {
                $errores[] = "línea $n: la ruta “" . mb_strimwidth($campo('ruta', 0), 0, 80, '…') . '” no es válida';
                continue;
            }
            $id = $campo('drive_id', 1);
            $bytes = $campo('bytes', 2);
            $comp = $campo('compartido', 3);
            if (!$cols && preg_match('/^\d+$/', $id) && ($bytes === '' || !preg_match('/^[\d.,\s]+$/', $bytes))) {
                [$id, $bytes] = [$bytes, $id]; // formato viejo: ruta, bytes[, id]
            }
            $driveId = self::id($id);
            if ($id !== '' && $driveId === null) {
                $errores[] = "línea $n ($ruta): el id de Drive “" . mb_strimwidth($id, 0, 60, '…') . '” no es válido';
                continue;
            }
            $peso = null;
            if ($bytes !== '') {
                if (!preg_match('/^[\d.,\s]+$/', $bytes)) {
                    $errores[] = "línea $n ($ruta): el peso “" . mb_strimwidth($bytes, 0, 30, '…') . '” no es un número de bytes';
                    continue;
                }
                $peso = (int)preg_replace('/\D/', '', $bytes);
            }
            $compartido = self::siNo($comp);
            if ($comp !== '' && $compartido === null) {
                $errores[] = "línea $n ($ruta): “" . mb_strimwidth($comp, 0, 30, '…') . '” en “compartido” no es sí ni no; se toma como sin dato';
            }
            $clave = mb_strtolower($ruta);
            if (isset($vistas[$clave])) {
                $errores[] = "línea $n: $ruta está repetida (vale la línea {$vistas[$clave]})";
                continue;
            }
            $vistas[$clave] = $n;
            $filas[] = ['ruta' => $ruta, 'bytes' => $peso, 'mtime' => null, 'drive_id' => $driveId, 'compartido' => $compartido, 'linea' => $n];
        }
        return ['filas' => $filas, 'errores' => $errores];
    }

    /** Guarda el listado normalizado (TSV con encabezado) en $destino, sin dejarlo a medias. */
    public static function guardar(array $filas, string $destino, string $origen = ''): void
    {
        $dir = dirname($destino);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException("No se pudo crear la carpeta del listado de Drive ($dir).");
        }
        $out = '# Listado de Drive normalizado el ' . date('d/m/Y H:i') . ($origen !== '' ? ' desde “' . str_replace(["\n", "\r"], ' ', $origen) . '”' : '') . "\n"
             . "ruta\tdrive_id\tbytes\tcompartido\n";
        foreach ($filas as $f) {
            $out .= implode("\t", [$f['ruta'], $f['drive_id'] ?? '', $f['bytes'] ?? '',
                $f['compartido'] === null ? '' : ($f['compartido'] ? 'si' : 'no')]) . "\n";
        }
        $tmp = $destino . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $out) === false || !@rename($tmp, $destino)) {
            @unlink($tmp);
            throw new \RuntimeException("No se pudo guardar el listado de Drive en $destino.");
        }
        @chmod($destino, 0640);
    }

    /** Id de Drive válido, a partir del id o de un enlace al archivo. */
    public static function id(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        if (preg_match('~/d/([A-Za-z0-9_-]+)~', $v, $m) || preg_match('~[?&]id=([A-Za-z0-9_-]+)~', $v, $m)) {
            $v = $m[1];
        }
        return preg_match(self::ID, $v) ? $v : null;
    }

    public static function idValido(?string $v): bool
    {
        return $v !== null && preg_match(self::ID, $v) === 1;
    }

    /** Ruta relativa segura (C1/C1R31.pdf): sin “..”, sin raíz, sin caracteres de control. */
    public static function ruta(string $v): ?string
    {
        $v = trim(str_replace('\\', '/', $v));
        $v = preg_replace('~^(\./)+~', '', $v) ?? $v;
        $v = ltrim($v, '/');
        if ($v === '' || mb_strlen($v) > 500 || preg_match('/[\x00-\x1F\x7F]/', $v)) {
            return null;
        }
        foreach (explode('/', $v) as $parte) {
            if ($parte === '' || $parte === '.' || $parte === '..') {
                return null;
            }
        }
        return $v;
    }

    private static function siNo(string $v): ?bool
    {
        $v = mb_strtolower(trim($v));
        return match (true) {
            $v === '' => null,
            in_array($v, ['si', 'sí', 's', '1', 'true', 'verdadero', 'yes', 'cualquiera con el enlace', 'anyone', 'anyonewithlink', 'publico', 'público', 'compartido'], true) => true,
            in_array($v, ['no', 'n', '0', 'false', 'falso', 'restringido', 'restricted', 'privado', 'private'], true) => false,
            default => null,
        };
    }

    /** @return array<string,int>|false  columnas por nombre, o false si la línea no es un encabezado */
    private static function encabezado(array $p): array|false
    {
        $norm = array_map(fn($x) => mb_strtolower(trim((string)$x, " \t\"'")), $p);
        if (!in_array($norm[0] ?? '', self::NOMBRES['ruta'], true) && !array_intersect($norm, ['ruta', 'drive_id'])) {
            return false;
        }
        $cols = [];
        foreach (self::NOMBRES as $clave => $nombres) {
            foreach ($norm as $idx => $nombre) {
                if (in_array($nombre, $nombres, true)) {
                    $cols[$clave] = $idx;
                    break;
                }
            }
        }
        return $cols;
    }
}
