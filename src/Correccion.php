<?php
declare(strict_types=1);

namespace Aida;

/**
 * Corrección masiva por planilla (backoffice: Importaciones o Cajas → «Corrección masiva…»).
 *
 * El archivo (CSV o .xlsx) trae una fila por celda a corregir:
 *   registro     C14R3, o «Caja 5 (todas las filas)» para un dato de la caja
 *   columna      el nombre de la columna del Excel del catálogo («Autor», «Año», «Nombre de caja (columna B)», «PRIVADO»…)
 *   valor_nuevo  el valor corregido (también se acepta `correccion_sugerida`, el encabezado de docs/datos/correcciones-planilla.csv)
 *   valor_actual opcional: si no coincide con la base, alguien ya lo cambió → conflicto, no se aplica (vacío = sin control)
 *   accion       opcional: «Revisar» se saltea (queda listada aparte)
 *
 * Siempre primero el plan (vista previa); aplicar vuelve a calcularlo y exige que no haya cambiado.
 * Se aplica con Historial::actualizar (auditado, bloquea los campos para que el Excel no los pise)
 * dentro de un proceso de tipo `correccion`, que se deshace completo desde Importaciones.
 * Pasar a público un registro que la planilla marca PRIVADO (privado_publico) pide una confirmación aparte:
 * sin ella, esas filas no se aplican (decisión del PM, 15/11/2026; igual que en la ficha).
 */
final class Correccion
{
    public const MAX_FILAS = 5000;

    /** columna del Excel (Texto::clave, sin paréntesis) => [entidad, campo, etiqueta] */
    private const COLUMNAS = [
        'autor'                           => ['registro', 'autor', 'Autor'],
        'titulo'                          => ['registro', 'titulo', 'Título'],
        'lugar'                           => ['registro', 'lugar', 'Lugar'],
        'fecha como dice el documento'    => ['registro', 'fecha_texto', 'Fecha como dice el documento'],
        'fecha como dice el doc'          => ['registro', 'fecha_texto', 'Fecha como dice el documento'],
        'fecha'                           => ['registro', 'fecha_texto', 'Fecha como dice el documento'],
        'ano'                             => ['registro', 'anio_texto', 'Año'],
        'paginas'                         => ['registro', 'paginas', 'Páginas'],
        'resumen'                         => ['registro', 'resumen', 'Resumen'],
        'isbn'                            => ['registro', 'isbn', 'ISBN'],
        'observaciones/editorial'         => ['registro', 'obs_editorial', 'Observaciones/Editorial'],
        'observaciones / editorial'       => ['registro', 'obs_editorial', 'Observaciones/Editorial'],
        'editorial'                       => ['registro', 'obs_editorial', 'Observaciones/Editorial'],
        'edicion de catalogo'             => ['registro', 'edicion_catalogo', 'Edición de catálogo'],
        'observaciones de conservacion'   => ['registro', 'obs_conservacion', 'Observaciones de conservación'],
        'observaciones de digitalizacion' => ['registro', 'obs_digitalizacion', 'Observaciones de digitalización'],
        'seleccion para digitalizacion'   => ['registro', 'seleccion_digit', 'Selección para digitalización'],
        'acceso'                          => ['registro', 'acceso', 'Acceso'],
        'nivel de acceso'                 => ['registro', 'acceso', 'Acceso'],
        'privado'                         => ['registro', 'acceso', 'Acceso'],
        'nombre de caja'                  => ['caja', 'nombre', 'Nombre de caja'],
        'resumen de caja'                 => ['caja', 'resumen', 'Resumen de la caja'],
        'resumen de la caja'              => ['caja', 'resumen', 'Resumen de la caja'],
    ];

    /** Columnas del Excel que existen pero no se corrigen por planilla, con el motivo. */
    private const RECHAZADAS = [
        'tipo'           => 'Tipos y descriptores son vocabularios: se corrigen desde la ficha o desde Vocabularios (fusionar, mover, descartar).',
        'descriptor'     => 'Tipos y descriptores son vocabularios: se corrigen desde la ficha o desde Vocabularios (fusionar, mover, descartar).',
        'caja'           => 'El número de caja es parte de la clave del registro: se cambia desde la ficha.',
        'registro'       => 'El número de registro es parte de la clave: se cambia desde la ficha.',
        'digitalizacion' => 'Si está digitalizado lo define la existencia del archivo, no la planilla.',
        'nombre de archivo digital' => 'La nomenclatura del Excel es solo control cruzado; la clave es caja + registro.',
    ];

    /** Largo máximo por campo (db/schema.sql). */
    private const LARGOS = ['titulo' => 500, 'autor' => 500, 'lugar' => 500, 'fecha_texto' => 500, 'anio_texto' => 60,
        'isbn' => 60, 'seleccion_digit' => 500, 'nombre' => 255];

    public const ESTADOS = [
        'aplicar'    => 'Se aplica',
        'sin_cambio' => 'Ya estaba así',
        'conflicto'  => 'Conflicto: no se aplica',
        'saltear'    => 'Se saltea',
        'error'      => 'No se puede aplicar',
    ];

    public function __construct(private readonly \PDO $db, private readonly ?int $usuarioId = null)
    {
    }

    // ------------------------------------------------------------------
    // Lectura del archivo
    // ------------------------------------------------------------------

    /**
     * Lee un CSV (coma, punto y coma o tabulación; UTF-8 o Windows-1252) o la primera hoja de un .xlsx.
     * @return list<array{fila:int,registro:string,columna:string,valor_actual:?string,valor_nuevo:string,accion:string,motivo:string}>
     */
    public static function leerArchivo(string $ruta, string $extension): array
    {
        $tabla = strtolower($extension) === 'xlsx' ? self::tablaXlsx($ruta) : self::tablaCsv($ruta);
        if (!$tabla) {
            throw new \RuntimeException('El archivo está vacío.');
        }
        $enc = array_shift($tabla);
        $idx = [];
        foreach ($enc as $i => $t) {
            $k = str_replace([' ', '-'], '_', Texto::clave((string)$t));
            $campo = match ($k) {
                'registro', 'codigo', 'c#r#', 'nomenclatura' => 'registro',
                'columna', 'campo' => 'columna',
                'valor_nuevo', 'correccion_sugerida', 'correccion', 'nuevo', 'valor_corregido' => 'valor_nuevo',
                'valor_actual', 'actual', 'valor_anterior' => 'valor_actual',
                'accion' => 'accion',
                'motivo', 'nota' => 'motivo',
                default => null,
            };
            if ($campo !== null && !isset($idx[$campo])) {
                $idx[$campo] = $i;
            }
        }
        $faltan = array_diff(['registro', 'columna', 'valor_nuevo'], array_keys($idx));
        if ($faltan) {
            throw new \RuntimeException('La primera fila tiene que tener los encabezados registro, columna y valor_nuevo '
                . '(o correccion_sugerida). Falta: ' . implode(', ', $faltan) . '.');
        }
        $out = [];
        foreach ($tabla as $n => $row) {
            $v = fn(string $c) => isset($idx[$c]) ? self::celda($row[$idx[$c]] ?? null) : '';
            if ($v('registro') === '' && $v('columna') === '' && $v('valor_nuevo') === '') {
                continue;
            }
            $out[] = [
                'fila' => $n + 2,
                'registro' => $v('registro'), 'columna' => $v('columna'),
                // valor_actual vacío = sin control (no se sabe qué había)
                'valor_actual' => isset($idx['valor_actual']) && $v('valor_actual') !== '' ? $v('valor_actual') : null,
                'valor_nuevo' => $v('valor_nuevo'), 'accion' => $v('accion'), 'motivo' => $v('motivo'),
            ];
            if (count($out) > self::MAX_FILAS) {
                throw new \RuntimeException('El archivo tiene más de ' . self::MAX_FILAS . ' filas. Partilo en varios.');
            }
        }
        return $out;
    }

    /** @return list<list<mixed>> */
    private static function tablaCsv(string $ruta): array
    {
        $txt = (string)file_get_contents($ruta);
        $txt = (string)preg_replace('/^\xEF\xBB\xBF/', '', $txt);
        if (!mb_check_encoding($txt, 'UTF-8')) {
            $txt = mb_convert_encoding($txt, 'UTF-8', 'Windows-1252');
        }
        $primera = strtok($txt, "\n") ?: '';
        $sep = ',';
        foreach ([';', "\t"] as $s) {
            if (substr_count($primera, $s) > substr_count($primera, $sep)) {
                $sep = $s;
            }
        }
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, $txt);
        rewind($fh);
        $out = [];
        while (($r = fgetcsv($fh, 0, $sep, '"', '')) !== false) {
            $out[] = $r;
        }
        fclose($fh);
        return $out;
    }

    /** @return list<list<mixed>> */
    private static function tablaXlsx(string $ruta): array
    {
        $x = new Xlsx($ruta);
        $hojas = $x->sheetNames();
        if (!$hojas) {
            return [];
        }
        $out = [];
        foreach ($x->rows($hojas[0]) as $r) {
            $fila = [];
            if ($r) {
                for ($i = 0, $max = max(array_keys($r)); $i <= $max; $i++) {
                    $fila[] = $r[$i] ?? null;
                }
            }
            $out[] = $fila;
        }
        return $out;
    }

    private static function celda(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? 'VERDADERO' : 'FALSO';
        }
        if (is_float($v) && floor($v) === $v) {
            $v = (int)$v;
        }
        return trim((string)preg_replace('/[ \t]+/u', ' ', (string)$v));
    }

    // ------------------------------------------------------------------
    // Plan (vista previa)
    // ------------------------------------------------------------------

    /**
     * Qué se va a hacer con cada fila, comparando con la base. No escribe nada.
     * @param list<array> $filas de leerArchivo()
     * @return array{filas:list<array>, cuentas:array<string,int>, firma:string}
     */
    public function plan(array $filas): array
    {
        $out = [];
        $vistos = [];
        $anular = [];
        foreach ($filas as $i => $f) {
            $p = $f + ['destino' => $f['registro'], 'entidad' => null, 'id' => null, 'campo' => null, 'etiqueta' => $f['columna'],
                       'actual' => null, 'nuevo' => null, 'estado' => 'aplicar', 'nota' => '', 'url' => null, 'bloquear' => true,
                       'privado_publico' => false, '_i' => $i];
            $out[] = $this->planFila($p, $vistos, $anular);
        }
        // Dos filas que cambian el mismo campo con valores distintos: no se aplica ninguna
        foreach ($anular as $i => $filaOtra) {
            if (in_array($out[$i]['estado'], ['aplicar', 'sin_cambio', 'conflicto'], true)) {
                $out[$i]['estado'] = 'error';
                $out[$i]['nota'] = "La fila $filaOtra cambia el mismo campo con otro valor. Dejá una sola.";
            }
        }
        $cuentas = array_fill_keys(array_keys(self::ESTADOS), 0) + ['privado_publico' => 0];
        foreach ($out as $p) {
            $cuentas[$p['estado']]++;
            if ($p['estado'] === 'aplicar' && $p['privado_publico']) {
                $cuentas['privado_publico']++;
            }
        }
        return ['filas' => $out, 'cuentas' => $cuentas, 'firma' => self::firma($out)];
    }

    /** @param array<string,int> $vistos entidad|id|campo => índice de la primera fila que lo cambia */
    private function planFila(array $p, array &$vistos, array &$anular): array
    {
        $nuevoTxt = $p['valor_nuevo'];
        if (Texto::clave($p['accion']) === 'revisar') {
            return ['estado' => 'saltear', 'nota' => 'Marcada «Revisar»: la decide el equipo' . ($p['motivo'] !== '' ? '. ' . $p['motivo'] : '')] + $p;
        }
        if ($nuevoTxt === '' || preg_match('/^[—–\-]+$/u', $nuevoTxt)) {
            return ['estado' => 'saltear', 'nota' => 'Sin valor nuevo'] + $p;
        }

        // Columna → campo
        $col = Texto::clave(trim((string)preg_replace('/\([^)]*\)/u', '', $p['columna'])));
        $col = (string)preg_replace('/\s*\d+$/', '', $col); // «Tipo 1», «Descriptor 3»
        $col = rtrim($col, ' .:');
        if (isset(self::RECHAZADAS[$col])) {
            return ['estado' => 'error', 'nota' => "La columna «{$p['columna']}» no se corrige por planilla. " . self::RECHAZADAS[$col]] + $p;
        }
        $map = self::COLUMNAS[$col] ?? null;
        if ($map === null) {
            return ['estado' => 'error', 'nota' => "No se reconoce la columna «{$p['columna']}». Usá el nombre de la columna del Excel "
                . '(Autor, Título, Lugar, Fecha como dice el documento, Año, Páginas, Resumen, ISBN, Observaciones/Editorial, '
                . 'Edición de catálogo, Observaciones de conservación, Observaciones de digitalización, Selección para digitalización, '
                . 'Acceso o PRIVADO, Nombre de caja, Resumen de la caja).'] + $p;
        }
        [$entidad, $campo, $etiqueta] = $map;
        $p['campo'] = $campo;
        $p['etiqueta'] = $etiqueta;

        // Registro o caja
        $destino = trim($p['registro']);
        $cod = Texto::parsearCodigo($destino);
        $numCaja = null;
        if ($cod !== null && $cod[2] === 0) {
            $numCaja = $cod[0];
        } elseif (preg_match('/^caja\s*(?:n[°ºo.]*\s*)?(\d+)\b/iu', $destino, $m)) {
            $numCaja = (int)$m[1];
            if ($entidad === 'registro') {
                return ['estado' => 'error', 'nota' => "«{$etiqueta}» es un dato de cada registro: indicá el registro (C#R#), no la caja."] + $p;
            }
        } else {
            return ['estado' => 'error', 'nota' => "«{$destino}» no es un registro (C14R3) ni una caja («Caja 5 (todas las filas)»)."] + $p;
        }
        if ($entidad === 'caja') {
            $st = $this->db->prepare('SELECT * FROM caja WHERE numero = ?');
            $st->execute([$numCaja]);
            $fila = $st->fetch();
            if (!$fila) {
                return ['estado' => 'error', 'nota' => "No existe la caja $numCaja."] + $p;
            }
            $p['destino'] = "Caja $numCaja";
        } else {
            $codigo = Texto::codigo($cod[0], $cod[1]);
            $st = $this->db->prepare('SELECT * FROM registro WHERE codigo = ?');
            $st->execute([$codigo]);
            $fila = $st->fetch();
            if (!$fila) {
                return ['estado' => 'error', 'destino' => $codigo, 'nota' => "No existe el registro $codigo."] + $p;
            }
            $p['destino'] = $codigo;
            $p['bloquear'] = $fila['origen'] === 'excel';
        }
        $p['entidad'] = $entidad;
        $p['id'] = (int)$fila['id'];
        $p['url'] = [$entidad, (int)$fila['id']];
        if ($fila['eliminado_en'] !== null) {
            return ['estado' => 'error', 'nota' => ($entidad === 'caja' ? 'La caja' : 'El registro') . ' está dado de baja. Restauralo primero.'] + $p;
        }
        $actual = $fila[$campo];
        $p['actual'] = $actual;

        // Valor nuevo según el campo
        if ($campo === 'acceso') {
            $nuevo = self::acceso($nuevoTxt);
            if ($nuevo === null) {
                return ['estado' => 'error', 'nota' => "«{$nuevoTxt}» no es un acceso. Usá Público, Restringido o A revisar "
                    . '(también VERDADERO = restringido, FALSO = público, VERLO = a revisar).'] + $p;
            }
            if ($nuevo === 'publico' && $fila['excel_privado'] === 'VERDADERO') {
                $p['nota'] = 'La planilla lo marca PRIVADO: pasa a público solo con la confirmación aparte.';
                $p['privado_publico'] = true;
            }
        } elseif ($campo === 'paginas') {
            $nuevo = Texto::entero($nuevoTxt);
            if ($nuevo === null) {
                return ['estado' => 'error', 'nota' => "Páginas tiene que ser un número entero («{$nuevoTxt}»)."] + $p;
            }
        } else {
            $nuevo = $nuevoTxt;
            if (isset(self::LARGOS[$campo]) && mb_strlen($nuevo) > self::LARGOS[$campo]) {
                return ['estado' => 'error', 'nota' => "El valor supera los " . self::LARGOS[$campo] . ' caracteres que admite el campo.'] + $p;
            }
            if ($campo === 'anio_texto') {
                $an = Texto::anio($nuevo);
                $p['nota'] = $an['desde'] === null ? 'Sin año para el filtro (no se pudo leer un año).'
                    : 'Para el filtro: ' . Texto::aniosEtiqueta($an['anios']) . '.';
            }
        }
        $p['nuevo'] = $nuevo;

        // Repetidas en el archivo
        $k = "$entidad|{$p['id']}|$campo";
        if (isset($vistos[$k])) {
            [$filaAnt, $valAnt, $iAnt] = $vistos[$k];
            if ((string)$valAnt === (string)$nuevo) {
                return ['estado' => 'sin_cambio', 'nota' => "Repetida: la fila $filaAnt ya hace este cambio."] + $p;
            }
            $anular[$iAnt] = $p['fila'];
            return ['estado' => 'error', 'nota' => "La fila $filaAnt cambia el mismo campo con otro valor («{$valAnt}»). Dejá una sola."] + $p;
        }
        $vistos[$k] = [$p['fila'], $nuevo, $p['_i']];

        if ((string)$actual === (string)$nuevo) {
            return ['estado' => 'sin_cambio', 'nota' => trim('Ya tiene ese valor. ' . $p['nota'])] + $p;
        }
        if ($p['valor_actual'] !== null && !$this->mismoValor($campo, $p['valor_actual'], $actual)) {
            return ['estado' => 'conflicto', 'nota' => 'El archivo esperaba «' . $p['valor_actual']
                . '» pero en la base hoy dice otra cosa: alguien ya lo cambió. No se aplica.'] + $p;
        }
        return $p;
    }

    /** valor_actual del archivo contra la base: sin espacios ni mayúsculas de diferencia, con tildes. */
    private function mismoValor(string $campo, string $delArchivo, mixed $enBase): bool
    {
        if ($campo === 'acceso') {
            return self::acceso($delArchivo) === $enBase;
        }
        return Texto::comparable($delArchivo) === Texto::comparable($enBase);
    }

    /** Público / Restringido / A revisar (y los valores de la columna PRIVADO). */
    public static function acceso(string $v): ?string
    {
        $k = str_replace(['_', '-'], ' ', Texto::clave($v));
        return match (true) {
            in_array($k, ['publico', 'falso', 'false', 'no', 'abierto'], true) => 'publico',
            in_array($k, ['restringido', 'verdadero', 'true', 'si', 'privado', 'x', 'a pedido'], true) => 'restringido',
            in_array($k, ['a revisar', 'revisar', 'verlo', 'sin definir'], true) => 'a_revisar',
            default => null,
        };
    }

    /** @param list<array> $filas */
    private static function firma(array $filas): string
    {
        return sha1(json_encode(array_map(fn($p) => [$p['fila'], $p['entidad'], $p['id'], $p['campo'], $p['actual'], $p['nuevo'], $p['estado']], $filas),
            JSON_UNESCAPED_UNICODE));
    }

    // ------------------------------------------------------------------
    // Aplicar
    // ------------------------------------------------------------------

    /**
     * Aplica las filas «aplicar» en un proceso de tipo `correccion`. Recalcula el plan dentro de la transacción
     * y, si no coincide con la vista previa que vio el operador ($firma), no aplica nada.
     * Las que pasan a público un registro que la planilla marca PRIVADO se aplican solo con $confirmaPrivados;
     * si no, se omiten y se cuentan en 'omitidas_privado'.
     * @return array{proceso:int, aplicadas:int, registros:int, cajas:int, omitidas_privado:int, cuentas:array<string,int>}
     */
    public function aplicar(array $filas, string $fuente, ?string $firma = null, bool $confirmaPrivados = false): array
    {
        $propia = !$this->db->inTransaction();
        if ($propia) {
            $this->db->beginTransaction();
        }
        try {
            $plan = $this->plan($filas);
            if ($firma !== null && !hash_equals($firma, $plan['firma'])) {
                throw new \RuntimeException('La base cambió desde la vista previa (alguien editó alguno de estos datos). '
                    . 'Volvé a subir el archivo para ver la vista previa actualizada.');
            }
            $porDestino = [];
            $omitidas = 0;
            foreach ($plan['filas'] as $p) {
                if ($p['estado'] !== 'aplicar') {
                    continue;
                }
                if ($p['privado_publico'] && !$confirmaPrivados) {
                    $omitidas++;
                    continue;
                }
                $k = "{$p['entidad']}|{$p['id']}";
                $porDestino[$k] ??= ['entidad' => $p['entidad'], 'id' => $p['id'], 'bloquear' => $p['bloquear'], 'datos' => []];
                $porDestino[$k]['datos'][$p['campo']] = $p['nuevo'];
                if ($p['campo'] === 'anio_texto') {
                    // Mismas reglas que la importación y la ficha (Texto::anio)
                    $an = Texto::anio($p['nuevo']);
                    $porDestino[$k]['datos'] += ['anio' => $an['desde'], 'anio_hasta' => $an['hasta'], 'anio_sin_confirmar' => (int)$an['sin_confirmar']];
                }
            }
            $pid = $this->abrirProceso($fuente);
            $h = new Historial($this->db, $this->usuarioId, 'backoffice', $pid);
            $n = ['registro' => 0, 'caja' => 0];
            foreach ($porDestino as $d) {
                $h->actualizar($d['entidad'], $d['id'], $d['datos'], $d['bloquear']);
                $n[$d['entidad']]++;
            }
            $resumen = array_filter([
                'correcciones aplicadas' => $plan['cuentas']['aplicar'] - $omitidas,
                'PRIVADO a público sin confirmar (no se aplicaron)' => $omitidas,
                'registros corregidos' => $n['registro'],
                'cajas corregidas' => $n['caja'],
                'ya estaban así' => $plan['cuentas']['sin_cambio'],
                'salteadas' => $plan['cuentas']['saltear'],
                'conflictos (no se aplicaron)' => $plan['cuentas']['conflicto'],
                'con error (no se aplicaron)' => $plan['cuentas']['error'],
            ]);
            $this->cerrarProceso($pid, $resumen);
            if ($propia) {
                $this->db->commit();
            }
            return ['proceso' => $pid, 'aplicadas' => $plan['cuentas']['aplicar'] - $omitidas, 'registros' => $n['registro'], 'cajas' => $n['caja'],
                    'omitidas_privado' => $omitidas, 'cuentas' => $plan['cuentas']];
        } catch (\Throwable $e) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function abrirProceso(string $fuente): int
    {
        $this->db->prepare("INSERT INTO proceso (tipo, fuente, simulacion, usuario_id, iniciado_en) VALUES ('correccion', ?, 0, ?, NOW())")
                 ->execute([mb_substr($fuente, 0, 500), $this->usuarioId]);
        return (int)$this->db->lastInsertId();
    }

    /** @param array<string,int> $resumen */
    public function cerrarProceso(int $pid, array $resumen): void
    {
        $this->db->prepare('UPDATE proceso SET resumen = ?, terminado_en = NOW() WHERE id = ?')
                 ->execute([json_encode($resumen, JSON_UNESCAPED_UNICODE), $pid]);
    }
}
