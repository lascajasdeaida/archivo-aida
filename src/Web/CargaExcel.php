<?php
declare(strict_types=1);

namespace Aida\Web;

use Aida\Alertas;
use Aida\Importador;
use Aida\Reporte;

/**
 * Subida del Excel del catálogo desde el backoffice y corrida del importador.
 *
 * Flujo: subir → simular (siempre) → ver el resumen → importar de verdad el mismo archivo.
 * El archivo se guarda fuera de public/ con un nombre generado; el nombre original solo queda
 * como «fuente» en la bitácora. Reusa Aida\Importador sin cambios de reglas.
 */
final class CargaExcel
{
    /** Límites para no abrir cualquier cosa: tamaño descomprimido total y cantidad de partes del .xlsx. */
    private const MAX_DESCOMPRIMIDO = 400 * 1048576;
    private const MAX_PARTES = 5000;
    /** Una simulación vale para importar durante este tiempo. */
    public const VIGENCIA = 7200;

    public static function directorio(): string
    {
        $dir = (string)(aida_config()['importaciones_dir'] ?? dirname(__DIR__, 2) . '/var/importaciones');
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException("No se pudo crear la carpeta de importaciones: $dir");
        }
        return rtrim($dir, '/');
    }

    /** Tamaño máximo efectivo en bytes: el menor entre la configuración y los límites de PHP. */
    public static function maxBytes(): int
    {
        $cfg = (int)(aida_config()['importar_max_mb'] ?? 40) * 1048576;
        $lim = array_filter([$cfg, self::iniBytes('upload_max_filesize'), self::iniBytes('post_max_size')], fn($v) => $v > 0);
        return $lim ? min($lim) : $cfg;
    }

    private static function iniBytes(string $clave): int
    {
        $v = trim((string)ini_get($clave));
        if ($v === '') {
            return 0;
        }
        $n = (int)$v;
        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n,
        };
    }

    /** true si el POST vino más grande que post_max_size (PHP descarta $_POST y $_FILES sin avisar). */
    public static function postDemasiadoGrande(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($_POST) && empty($_FILES)
            && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
    }

    /** Valida el archivo subido. Devuelve el mensaje de error o null si está bien. */
    public static function validar(?array $f): ?string
    {
        $max = self::maxBytes();
        $maxTxt = number_format($max / 1048576, 0, ',', '.') . ' MB';
        if (!$f || !isset($f['error']) || is_array($f['error'])) {
            return 'Elegí el archivo Excel del catálogo.';
        }
        switch ($f['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return 'Elegí el archivo Excel del catálogo.';
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return "El archivo supera el máximo permitido ($maxTxt).";
            case UPLOAD_ERR_PARTIAL:
                return 'El archivo llegó incompleto. Probá subirlo de nuevo.';
            default:
                return 'No se pudo recibir el archivo (error del servidor ' . (int)$f['error'] . '). Avisá al equipo técnico.';
        }
        if (strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
            return 'Tiene que ser un Excel .xlsx. Si está en .xls o en Google Sheets, descargalo como «Microsoft Excel (.xlsx)».';
        }
        $tmp = (string)$f['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            return 'No se pudo recibir el archivo. Probá de nuevo.';
        }
        $tam = (int)filesize($tmp);
        if ($tam === 0) {
            return 'El archivo está vacío.';
        }
        if ($tam > $max) {
            return "El archivo supera el máximo permitido ($maxTxt).";
        }
        return self::validarZip($tmp);
    }

    /** Un .xlsx es un zip con [Content_Types].xml y xl/workbook.xml. */
    public static function validarZip(string $ruta): ?string
    {
        $malo = 'El archivo no es un Excel válido (está dañado o no es .xlsx aunque lo diga el nombre).';
        $fh = fopen($ruta, 'rb');
        $firma = $fh ? fread($fh, 4) : '';
        if ($fh) {
            fclose($fh);
        }
        if ($firma !== "PK\x03\x04") {
            return $malo;
        }
        $z = new \ZipArchive();
        if ($z->open($ruta, \ZipArchive::CHECKCONS) !== true) {
            return $malo;
        }
        try {
            if ($z->locateName('[Content_Types].xml') === false || $z->locateName('xl/workbook.xml') === false) {
                return $malo;
            }
            if ($z->numFiles > self::MAX_PARTES) {
                return $malo;
            }
            $total = 0;
            for ($i = 0; $i < $z->numFiles; $i++) {
                $total += (int)($z->statIndex($i)['size'] ?? 0);
                if ($total > self::MAX_DESCOMPRIMIDO) {
                    return 'El Excel es demasiado grande para procesarlo desde el backoffice. Pedile al equipo técnico que lo importe por consola.';
                }
            }
        } finally {
            $z->close();
        }
        return null;
    }

    /**
     * Interpreta «1, 2, 6» o «10-14». Vacío = todas las cajas.
     * @return int[]|null|false  null = todas · false = formato inválido
     */
    public static function cajas(string $txt): array|null|false
    {
        $txt = trim((string)preg_replace('/\s*-\s*/', '-', $txt));
        if ($txt === '') {
            return null;
        }
        $out = [];
        foreach (preg_split('/[\s,;]+/', $txt) as $parte) {
            if (!preg_match('/^(\d+)(?:-(\d+))?$/', $parte, $m)) {
                return false;
            }
            $desde = (int)$m[1];
            $hasta = isset($m[2]) ? (int)$m[2] : $desde;
            if ($desde < 1 || $hasta < $desde || $hasta - $desde > 500) {
                return false;
            }
            array_push($out, ...range($desde, $hasta));
        }
        $out = array_values(array_unique($out));
        sort($out);
        return $out;
    }

    /** Nombre original, solo para mostrar y para la bitácora. */
    public static function nombreOriginal(string $n): string
    {
        $n = basename(str_replace('\\', '/', $n));
        $n = (string)preg_replace('/[\x00-\x1F\x7F]+/u', '', $n);
        return mb_substr($n !== '' ? $n : 'catalogo.xlsx', 0, 200);
    }

    /** Borra las subidas que se simularon y nunca se importaron (más viejas que la vigencia). */
    public static function limpiarViejas(string $dir): void
    {
        foreach (glob($dir . '/sim-*.xlsx') ?: [] as $f) {
            if (filemtime($f) < time() - self::VIGENCIA * 2) {
                @unlink($f);
            }
        }
    }

    /**
     * Corre el importador (el candado contra importaciones simultáneas lo toma el propio Importador, así vale también para la consola).
     * Cierra la sesión mientras trabaja para no bloquear otras pestañas del mismo usuario.
     * @return array{0:Reporte,1:float} [reporte, segundos]
     */
    public static function correr(string $ruta, ?array $cajas, bool $simulacion, string $fuente): array
    {
        $db = aida_db();
        @set_time_limit(900);
        $mem = (string)ini_get('memory_limit');
        if ($mem !== '-1' && self::iniBytes('memory_limit') < 512 * 1048576) {
            @ini_set('memory_limit', '512M');
        }
        session_write_close();
        $t = microtime(true);
        try {
            $rep = (new Importador($db, App::usuarioId()))->importar($ruta, $cajas, $simulacion, $fuente);
        } finally {
            App::iniciarSesion();
        }
        return [$rep, round(microtime(true) - $t, 1)];
    }

    /**
     * Resumen para mostrar (y guardar en sesión): contadores e incidencias agrupadas por código.
     * @return array{cuentas:array<string,int>, grupos:list<array>, total:int, a_validar:int}
     */
    public static function resumen(Reporte $rep): array
    {
        $grupos = [];
        $aValidar = 0;
        foreach ($rep->incidencias as $i) {
            $k = $i['codigo'];
            $validar = Alertas::requiereValidacion($i['codigo'], $i['nivel']);
            $aValidar += (int)$validar;
            if (!isset($grupos[$k])) {
                $al = Alertas::de($k);
                $grupos[$k] = ['codigo' => $k, 'nivel' => $i['nivel'], 'titulo' => $al['titulo'], 'leyenda' => $al['leyenda'],
                               'validar' => $validar, 'n' => 0, 'ejemplos' => []];
            }
            $grupos[$k]['n']++;
            if (count($grupos[$k]['ejemplos']) < 8) {
                $donde = $i['caja'] !== null ? 'C' . $i['caja'] . ($i['registro'] !== null ? 'R' . $i['registro'] : '') : '';
                $grupos[$k]['ejemplos'][] = [$donde, mb_substr($i['detalle'], 0, 300)];
            }
        }
        $grupos = array_values($grupos);
        usort($grupos, fn($a, $b) => [$b['validar'], $b['n']] <=> [$a['validar'], $a['n']]);
        return ['cuentas' => $rep->cuentas, 'grupos' => $grupos, 'total' => count($rep->incidencias), 'a_validar' => $aValidar];
    }
}

