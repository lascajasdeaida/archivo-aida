<?php
declare(strict_types=1);

namespace Aida\Web;

use Aida\Alertas;
use Aida\Historial;
use Aida\Texto;

/**
 * Controladores del backoffice. Cada método público es una ruta (?r=nombre).
 * Lecturas por GET; toda escritura por POST con token CSRF y a través de Historial.
 */
final class Rutas
{
    /** Campos editables de la ficha del registro, con su etiqueta. */
    public const CAMPOS_REGISTRO = [
        'titulo' => 'Título', 'autor' => 'Autor', 'lugar' => 'Lugar', 'fecha_texto' => 'Fecha (como dice el documento)',
        'anio_texto' => 'Año', 'paginas' => 'Páginas', 'resumen' => 'Resumen', 'isbn' => 'ISBN',
        'obs_editorial' => 'Observaciones / editorial', 'edicion_catalogo' => 'Edición de catálogo',
        'obs_conservacion' => 'Observaciones de conservación', 'obs_digitalizacion' => 'Observaciones de digitalización',
    ];

    private static function db(): \PDO
    {
        return aida_db();
    }

    private static function historial(): Historial
    {
        return new Historial(self::db(), App::usuarioId(), 'backoffice');
    }

    private static function int(string $k): int
    {
        return (int)($_REQUEST[$k] ?? 0);
    }

    // ------------------------------------------------------------------
    // Sesión
    // ------------------------------------------------------------------

    public static function login(): void
    {
        $error = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            App::verificarPost();
            $st = self::db()->prepare('SELECT * FROM usuario WHERE email = ? AND activo = 1');
            $st->execute([trim((string)($_POST['email'] ?? ''))]);
            $u = $st->fetch();
            if ($u && password_verify((string)($_POST['clave'] ?? ''), $u['password_hash']) && $u['rol'] !== 'visitante') {
                session_regenerate_id(true);
                $_SESSION['usuario'] = ['id' => (int)$u['id'], 'email' => $u['email'], 'nombre' => $u['nombre'], 'rol' => $u['rol']];
                App::redirigir('cajas');
            }
            $error = 'Usuario o contraseña incorrectos.';
            usleep(400000);
        }
        App::render('login', ['error' => $error]);
    }

    public static function salir(): void
    {
        App::verificarPost();
        $_SESSION = [];
        session_destroy();
        App::redirigir('login');
    }

    // ------------------------------------------------------------------
    // Cajas
    // ------------------------------------------------------------------

    public static function cajas(): void
    {
        App::exigir('ver');
        $verBajas = !empty($_GET['bajas']);
        $sql = 'SELECT c.*,
                  (SELECT COUNT(*) FROM registro r WHERE r.caja_id = c.id AND r.eliminado_en IS NULL) registros,
                  (SELECT COUNT(*) FROM registro r WHERE r.caja_id = c.id AND r.eliminado_en IS NULL AND r.digitalizado = 1) digitalizados,
                  (SELECT COUNT(*) FROM registro r WHERE r.caja_id = c.id AND r.eliminado_en IS NULL AND r.acceso <> \'publico\') no_publicos,
                  (SELECT COUNT(*) FROM incidencia i WHERE i.caja = c.numero AND i.resuelta = 0 AND i.requiere_validacion = 1) alertas
                FROM caja c ' . ($verBajas ? '' : 'WHERE c.eliminado_en IS NULL ') . 'ORDER BY c.numero';
        $todas = self::db()->query('SELECT id, numero, nombre FROM caja WHERE eliminado_en IS NULL')->fetchAll();
        App::render('cajas', ['cajas' => self::db()->query($sql)->fetchAll(), 'verBajas' => $verBajas,
                              'parecidos' => count(self::gruposParecidos($todas))]);
    }

    public static function caja(): void
    {
        App::exigir('ver');
        $c = self::cargarCaja(self::int('id'));
        $f = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'estado' => (string)($_GET['estado'] ?? ''),
            'acceso' => (string)($_GET['acceso'] ?? ''),
            'bajas' => !empty($_GET['bajas']),
        ];
        $w = ['r.caja_id = ?'];
        $p = [$c['id']];
        if (!$f['bajas']) {
            $w[] = 'r.eliminado_en IS NULL';
        }
        if ($f['q'] !== '') {
            $w[] = "(r.titulo LIKE ? OR r.resumen LIKE ? OR r.autor LIKE ? OR r.codigo = ? OR EXISTS (SELECT 1 FROM registro_vocabulario rv
                    JOIN vocabulario v ON v.id = rv.vocabulario_id WHERE rv.registro_id = r.id AND v.nombre LIKE ?))";
            array_push($p, "%{$f['q']}%", "%{$f['q']}%", "%{$f['q']}%", strtoupper($f['q']), "%{$f['q']}%");
        }
        if ($f['estado'] === 'digitalizado') {
            $w[] = 'r.digitalizado = 1';
        } elseif ($f['estado'] === 'pendiente') {
            $w[] = 'r.digitalizado = 0';
        }
        if (in_array($f['acceso'], ['publico', 'restringido', 'a_revisar'], true)) {
            $w[] = 'r.acceso = ?';
            $p[] = $f['acceso'];
        }
        $st = self::db()->prepare("SELECT r.*,
              (SELECT COUNT(*) FROM incidencia i WHERE i.caja = ? AND i.registro = r.numero AND i.resuelta = 0 AND i.requiere_validacion = 1) alertas,
              (SELECT GROUP_CONCAT(v.nombre ORDER BY rv.orden SEPARATOR ' · ') FROM registro_vocabulario rv
                 JOIN vocabulario v ON v.id = rv.vocabulario_id WHERE rv.registro_id = r.id AND v.tipo = 'tipo_documento') tipos
            FROM registro r WHERE " . implode(' AND ', $w) . ' ORDER BY r.numero');
        $st->execute(array_merge([$c['numero']], $p));
        App::render('caja', [
            'caja' => $c,
            'registros' => $st->fetchAll(),
            'filtros' => $f,
            'alertas' => self::alertasAbiertas((int)$c['numero'], null),
            'historial' => self::historialDe('caja', (int)$c['id']),
        ]);
    }

    /**
     * Sugiere grupos de cajas con nombres casi iguales (tildes, mayúsculas, comillas, errores de tipeo)
     * y permite unificarlos. Cada cambio pasa por el historial (se puede deshacer y bloquea el campo para el Excel).
     */
    public static function cajas_unificar(): void
    {
        App::exigir('editar');
        $cajas = self::db()->query('SELECT id, numero, nombre, campos_bloqueados FROM caja WHERE eliminado_en IS NULL ORDER BY numero')->fetchAll();
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            App::verificarPost();
            $nombre = trim((string)($_POST['nombre'] ?? ''));
            $ids = array_map('intval', (array)($_POST['cajas'] ?? []));
            if ($nombre === '' || count($ids) < 1) {
                App::aviso('error', 'Elegí al menos una caja y escribí cómo tiene que quedar el nombre.');
            } else {
                $h = self::historial();
                $n = 0;
                foreach ($cajas as $c) {
                    if (in_array((int)$c['id'], $ids, true) && $c['nombre'] !== $nombre) {
                        $h->actualizar('caja', (int)$c['id'], ['nombre' => $nombre], true);
                        $n++;
                    }
                }
                App::aviso('ok', $n ? "Nombre unificado en $n caja" . ($n === 1 ? '' : 's') . ": «{$nombre}». Se puede deshacer desde el historial de cada caja."
                                    : 'Esas cajas ya tenían ese nombre.');
            }
            App::redirigir('cajas_unificar');
        }
        App::render('cajas_unificar', ['grupos' => self::gruposParecidos($cajas)]);
    }

    /** @return list<array{cajas:list<array>, sugerido:string}> */
    private static function gruposParecidos(array $cajas): array
    {
        $base = function (string $s): string {
            $s = Texto::clave($s);
            $s = (string)preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $s);
            return trim((string)preg_replace('/\s+/', ' ', $s));
        };
        $sinParentesis = fn(string $s) => trim((string)preg_replace('/\([^)]*\)/u', '', $s));
        $n = count($cajas);
        $padre = range(0, $n - 1);
        $raiz = function (int $i) use (&$padre, &$raiz): int { return $padre[$i] === $i ? $i : ($padre[$i] = $raiz($padre[$i])); };
        $bases = array_map(fn($c) => $base($c['nombre']), $cajas);
        $cortas = array_map(fn($c) => $base($sinParentesis($c['nombre'])), $cajas);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $bases[$i]; $b = $bases[$j];
                if ($a === '' || $b === '') continue;
                $parecido = $a === $b || ($cortas[$i] !== '' && $cortas[$i] === $cortas[$j]);
                if (!$parecido && strlen($a) >= 6 && strlen($b) >= 6 && strlen($a) < 255 && strlen($b) < 255) {
                    $parecido = levenshtein($a, $b) / max(strlen($a), strlen($b)) <= 0.15;
                }
                if ($parecido) {
                    $padre[$raiz($i)] = $raiz($j);
                }
            }
        }
        $grupos = [];
        foreach ($cajas as $i => $c) {
            $grupos[$raiz($i)][] = $c;
        }
        $out = [];
        foreach ($grupos as $g) {
            if (count(array_unique(array_column($g, 'nombre'))) < 2) {
                continue; // mismos nombres exactos: no hay nada que unificar
            }
            // Sugerencia: la variante con más tildes; a igualdad, la que no está toda en mayúsculas
            $mejor = null;
            $puntaje = -1;
            foreach ($g as $c) {
                $nom = trim((string)preg_replace(['/["“”«»]/u', '/\s+/u'], ['', ' '], $c['nombre']));
                $letras = max(1, preg_match_all('/\p{L}/u', $nom));
                $mayus = preg_match_all('/\p{Lu}/u', $nom);
                $p = preg_match_all('/[áéíóúÁÉÍÓÚñÑü]/u', $nom) * 10 + (1 - $mayus / $letras) * 9;
                if ($p > $puntaje) {
                    $puntaje = $p;
                    $mejor = $nom;
                }
            }
            $out[] = ['cajas' => $g, 'sugerido' => (string)$mejor];
        }
        return $out;
    }

    public static function caja_nueva(): void
    {
        App::exigir('baja');
        $sugerido = (int)self::db()->query('SELECT COALESCE(MAX(numero), 0) + 1 FROM caja')->fetchColumn();
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            App::verificarPost();
            $num = (int)($_POST['numero'] ?? 0);
            $nombre = trim((string)($_POST['nombre'] ?? ''));
            $err = self::numeroCajaInvalido($num) ?? ($nombre === '' ? 'Falta el nombre de la caja.' : null);
            if ($err) {
                App::render('caja_nueva', ['sugerido' => $sugerido, 'error' => $err, 'datos' => $_POST]);
                return;
            }
            $id = self::historial()->crear('caja', ['numero' => $num, 'nombre' => $nombre,
                                                    'resumen' => trim((string)($_POST['resumen'] ?? '')) ?: null]);
            App::aviso('ok', "Caja $num creada.");
            App::redirigir('caja', ['id' => $id]);
        }
        App::render('caja_nueva', ['sugerido' => $sugerido, 'error' => null, 'datos' => []]);
    }

    public static function caja_guardar(): void
    {
        App::exigir('editar');
        App::verificarPost();
        $c = self::cargarCaja(self::int('id'));
        $nuevos = [
            'nombre' => trim((string)($_POST['nombre'] ?? '')),
            'resumen' => trim((string)($_POST['resumen'] ?? '')) ?: null,
            'publicada' => empty($_POST['publicada']) ? 0 : 1,
        ];
        if ($nuevos['nombre'] === '') {
            App::aviso('error', 'El nombre de la caja no puede quedar vacío.');
            App::redirigir('caja', ['id' => $c['id']]);
        }
        $cambios = self::soloCambios($c, $nuevos);
        if ($cambios) {
            self::historial()->actualizar('caja', (int)$c['id'], $cambios, true);
            App::aviso('ok', 'Caja guardada. Los campos editados ya no se actualizan desde el Excel.');
        }
        App::redirigir('caja', ['id' => $c['id']]);
    }

    public static function caja_baja(): void
    {
        App::exigir('baja');
        $c = self::cargarCaja(self::int('id'));
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            App::verificarPost();
            self::historial()->darDeBaja('caja', (int)$c['id']);
            App::aviso('ok', "Caja {$c['numero']} dada de baja. Se puede restaurar desde su ficha o deshacer desde el historial.");
            App::redirigir('caja', ['id' => $c['id']]);
        }
        $st = self::db()->prepare('SELECT COUNT(*) regs, COALESCE(SUM(digitalizado),0) digs FROM registro WHERE caja_id = ? AND eliminado_en IS NULL');
        $st->execute([$c['id']]);
        $n = $st->fetch();
        App::render('confirmar', [
            'titulo' => "Dar de baja la Caja {$c['numero']}",
            'texto' => "Vas a dar de baja la Caja {$c['numero']} · {$c['nombre']}, con {$n['regs']} registros ({$n['digs']} digitalizados). "
                     . 'Dejan de mostrarse en el sitio y la importación del Excel no la vuelve a crear. Se puede restaurar.',
            'accion' => App::url('caja_baja', ['id' => $c['id']]),
            'boton' => 'Dar de baja',
            'volver' => App::url('caja', ['id' => $c['id']]),
            'peligro' => true,
        ]);
    }

    public static function caja_restaurar(): void
    {
        App::exigir('baja');
        App::verificarPost();
        $c = self::cargarCaja(self::int('id'));
        self::historial()->restaurar('caja', (int)$c['id']);
        App::aviso('ok', "Caja {$c['numero']} restaurada.");
        App::redirigir('caja', ['id' => $c['id']]);
    }

    // ------------------------------------------------------------------
    // Registros
    // ------------------------------------------------------------------

    public static function registro(): void
    {
        App::exigir('ver');
        $r = self::cargarRegistro(self::int('id'));
        $c = self::cargarCaja((int)$r['caja_id']);
        $h = self::historial();
        $st = self::db()->prepare('SELECT * FROM archivo_digital WHERE registro_id = ? ORDER BY parte, nombre_archivo');
        $st->execute([$r['id']]);
        $hermanos = self::db()->prepare('SELECT id, numero FROM registro WHERE caja_id = ? AND eliminado_en IS NULL ORDER BY numero');
        $hermanos->execute([$r['caja_id']]);
        $lista = $hermanos->fetchAll();
        $pos = array_search((int)$r['id'], array_map(fn($x) => (int)$x['id'], $lista), true);
        // Quién y cuándo bloqueó cada campo (último cambio manual de ese campo)
        $bloq = json_decode((string)$r['campos_bloqueados'], true) ?: [];
        $quien = [];
        if ($bloq) {
            foreach (self::historialDe('registro', (int)$r['id']) as $a) {
                if ($a['origen'] !== 'backoffice') {
                    continue;
                }
                foreach (array_keys($a['cambios']) as $campo) {
                    $quien[$campo] ??= $a;
                }
            }
        }
        App::render('registro', [
            'r' => $r,
            'caja' => $c,
            'tipos' => $h->leerLista((int)$r['id'], 'tipos'),
            'descriptores' => $h->leerLista((int)$r['id'], 'descriptores'),
            'vocab' => self::vocabularioParaSelect(),
            'archivos' => $st->fetchAll(),
            'bloqueados' => $bloq,
            'bloqueadoPor' => $quien,
            'alertas' => self::alertasAbiertas((int)$c['numero'], (int)$r['numero']),
            'historial' => self::historialDe('registro', (int)$r['id']),
            'anterior' => $pos !== false && $pos > 0 ? $lista[$pos - 1] : null,
            'siguiente' => $pos !== false && isset($lista[$pos + 1]) ? $lista[$pos + 1] : null,
        ]);
    }

    public static function registro_guardar(): void
    {
        App::exigir('editar');
        App::verificarPost();
        $r = self::cargarRegistro(self::int('id'));
        $nuevos = [];
        foreach (array_keys(self::CAMPOS_REGISTRO) as $k) {
            $v = trim((string)($_POST[$k] ?? ''));
            $nuevos[$k] = $v === '' ? null : $v;
        }
        $nuevos['paginas'] = $nuevos['paginas'] !== null ? (Texto::entero($nuevos['paginas']) ?? $r['paginas']) : null;
        $nuevos['publicado'] = empty($_POST['publicado']) ? 0 : 1;
        if (App::puede('acceso') && isset($_POST['acceso']) && in_array($_POST['acceso'], ['publico', 'restringido', 'a_revisar'], true)) {
            $nuevos['acceso'] = $_POST['acceso'];
        }
        $cambios = self::soloCambios($r, $nuevos);
        if (array_key_exists('anio_texto', $cambios)) {
            $a = Texto::entero($cambios['anio_texto']);
            $cambios['anio'] = ($a !== null && $a >= 1900 && $a <= 2100) ? $a : null;
        }

        // Tipos y descriptores (listas)
        $h = self::historial();
        foreach (['tipos' => 'tipos', 'descriptores' => 'descriptores'] as $campo => $post) {
            $sel = array_values(array_filter(array_map('trim', (array)($_POST[$post] ?? [])), 'strlen'));
            if ($sel !== $h->leerLista((int)$r['id'], $campo)) {
                $cambios[$campo] = $sel;
            }
        }

        // Pasar a público algo que el Excel marca como privado pide confirmación
        if (($cambios['acceso'] ?? null) === 'publico' && $r['excel_privado'] === 'VERDADERO' && empty($_POST['confirmar_publico'])) {
            App::aviso('error', 'La planilla marca este registro como PRIVADO. Para publicarlo igual, tildá la confirmación junto al acceso.');
            App::redirigir('registro', ['id' => $r['id']]);
        }

        if ($cambios) {
            $h->actualizar('registro', (int)$r['id'], $cambios, $r['origen'] === 'excel');
            App::aviso('ok', 'Registro guardado.' . ($r['origen'] === 'excel'
                ? ' Los campos que editaste ya no se actualizan desde el Excel.' : ''));
        } else {
            App::aviso('info', 'No había cambios para guardar.');
        }
        App::redirigir('registro', ['id' => $r['id']]);
    }

    public static function registro_nuevo(): void
    {
        App::exigir('editar');
        $c = self::cargarCaja(self::int('caja'));
        // El siguiente número libre, sin reutilizar números de registros dados de baja
        $st = self::db()->prepare('SELECT COALESCE(MAX(numero), 0) + 1 FROM registro WHERE caja_id = ?');
        $st->execute([$c['id']]);
        $sugerido = (int)$st->fetchColumn();
        $error = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            App::verificarPost();
            $num = (int)($_POST['numero'] ?? 0);
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $existe = self::db()->prepare('SELECT codigo, eliminado_en FROM registro WHERE caja_id = ? AND numero = ?');
            $existe->execute([$c['id'], $num]);
            $ya = $existe->fetch();
            if ($num < 1) {
                $error = 'El número de registro tiene que ser mayor que cero.';
            } elseif ($ya) {
                $error = "Ya existe el registro {$ya['codigo']}" . ($ya['eliminado_en'] ? ' (dado de baja; se puede restaurar)' : '') . '.';
            } elseif ($num !== $sugerido && !App::puede('nomenclatura')) {
                $error = 'Solo un editor o administrador puede elegir un número distinto del sugerido.';
            } elseif ($num !== $sugerido && empty($_POST['confirmar_numero'])) {
                $error = "El número sugerido es $sugerido. Para usar $num, tildá la confirmación.";
            } elseif ($titulo === '') {
                $error = 'Falta el título.';
            }
            if (!$error) {
                $id = self::historial()->crear('registro', [
                    'caja_id' => $c['id'], 'numero' => $num, 'codigo' => Texto::codigo((int)$c['numero'], $num),
                    'titulo' => $titulo, 'acceso' => 'publico',
                ]);
                App::aviso('ok', 'Registro ' . Texto::codigo((int)$c['numero'], $num) . ' creado. Completá el resto de la ficha.');
                App::redirigir('registro', ['id' => $id]);
            }
        }
        App::render('registro_nuevo', ['caja' => $c, 'sugerido' => $sugerido, 'error' => $error, 'datos' => $_POST]);
    }

    public static function registro_baja(): void
    {
        App::exigir('baja');
        $r = self::cargarRegistro(self::int('id'));
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            App::verificarPost();
            self::historial()->darDeBaja('registro', (int)$r['id']);
            App::aviso('ok', "Registro {$r['codigo']} dado de baja.");
            App::redirigir('registro', ['id' => $r['id']]);
        }
        $st = self::db()->prepare('SELECT COUNT(*) FROM archivo_digital WHERE registro_id = ? AND presente = 1 AND eliminado_en IS NULL');
        $st->execute([$r['id']]);
        $arch = (int)$st->fetchColumn();
        App::render('confirmar', [
            'titulo' => "Dar de baja el registro {$r['codigo']}",
            'texto' => "Vas a dar de baja el registro {$r['codigo']}" . ($arch ? " con $arch archivo(s) asociado(s), que se ocultan junto con el registro" : '')
                     . '. Deja de mostrarse en el sitio y la importación no lo vuelve a crear. Se puede restaurar.',
            'accion' => App::url('registro_baja', ['id' => $r['id']]),
            'boton' => 'Dar de baja',
            'volver' => App::url('registro', ['id' => $r['id']]),
            'peligro' => true,
        ]);
    }

    public static function registro_restaurar(): void
    {
        App::exigir('baja');
        App::verificarPost();
        $r = self::cargarRegistro(self::int('id'));
        self::historial()->restaurar('registro', (int)$r['id']);
        App::aviso('ok', "Registro {$r['codigo']} restaurado.");
        App::redirigir('registro', ['id' => $r['id']]);
    }

    /** Vuelve a tomar un campo desde el Excel (lo saca de campos_bloqueados). */
    public static function campo_desbloquear(): void
    {
        App::exigir('editar');
        App::verificarPost();
        $entidad = ($_POST['entidad'] ?? '') === 'caja' ? 'caja' : 'registro';
        $fila = $entidad === 'caja' ? self::cargarCaja(self::int('id')) : self::cargarRegistro(self::int('id'));
        $campo = (string)($_POST['campo'] ?? '');
        $bloq = json_decode((string)$fila['campos_bloqueados'], true) ?: [];
        $nuevo = array_values(array_diff($bloq, [$campo]));
        self::historial()->actualizar($entidad, (int)$fila['id'], ['campos_bloqueados' => $nuevo ? json_encode($nuevo) : null], false);
        App::aviso('ok', "«{$campo}» se va a volver a actualizar desde el Excel en la próxima importación.");
        App::redirigir($entidad, ['id' => $fila['id']]);
    }

    public static function origen_excel(): void
    {
        App::exigir('baja');
        App::verificarPost();
        $r = self::cargarRegistro(self::int('id'));
        self::historial()->actualizar('registro', (int)$r['id'], ['origen' => 'excel'], false);
        App::aviso('ok', "{$r['codigo']} pasa a actualizarse desde el Excel (salvo los campos editados a mano).");
        App::redirigir('registro', ['id' => $r['id']]);
    }

    // ------------------------------------------------------------------
    // Archivos
    // ------------------------------------------------------------------

    public static function archivo_baja(): void
    {
        App::exigir('baja');
        App::verificarPost();
        $a = self::cargarArchivo(self::int('id'));
        self::historial()->darDeBaja('archivo_digital', (int)$a['id']);
        self::recalcularDigitalizado((int)$a['registro_id']);
        App::aviso('ok', "Se quitó {$a['nombre_archivo']} del registro. El escaneo no lo vuelve a asociar.");
        App::redirigir('registro', ['id' => $a['registro_id']], 'archivos');
    }

    public static function archivo_restaurar(): void
    {
        App::exigir('baja');
        App::verificarPost();
        $a = self::cargarArchivo(self::int('id'));
        self::historial()->restaurar('archivo_digital', (int)$a['id']);
        self::recalcularDigitalizado((int)$a['registro_id']);
        App::aviso('ok', "{$a['nombre_archivo']} vuelve a estar asociado.");
        App::redirigir('registro', ['id' => $a['registro_id']], 'archivos');
    }

    // ------------------------------------------------------------------
    // Deshacer
    // ------------------------------------------------------------------

    public static function deshacer(): void
    {
        App::verificarPost();
        $id = self::int('cambio');
        $st = self::db()->prepare('SELECT * FROM auditoria WHERE id = ?');
        $st->execute([$id]);
        $a = $st->fetch();
        if (!$a) {
            App::redirigir('cajas');
        }
        $propio = $a['usuario_id'] !== null && (int)$a['usuario_id'] === App::usuarioId();
        App::exigir($propio ? 'deshacer_propio' : 'deshacer_todo');
        $forzar = !empty($_POST['forzar']) && App::puede('forzar');

        $db = self::db();
        $db->beginTransaction();
        $res = self::historial()->revertir($id, $forzar);
        $res['ok'] ? $db->commit() : $db->rollBack();
        if ($a['entidad'] === 'archivo_digital' && $res['ok']) {
            $reg = $db->prepare('SELECT registro_id FROM archivo_digital WHERE id = ?');
            $reg->execute([$a['entidad_id']]);
            self::recalcularDigitalizado((int)$reg->fetchColumn());
        }
        if (!$res['ok'] && $res['conflictos']) {
            $_SESSION['conflicto'] = ['cambio' => $id, 'conflictos' => $res['conflictos']];
        }
        App::aviso($res['ok'] ? 'ok' : 'error', $res['mensaje']);
        $volver = (string)($_POST['volver'] ?? '');
        header('Location: ' . (str_starts_with($volver, '?r=') ? $volver : App::url('cajas')));
        exit;
    }

    // ------------------------------------------------------------------
    // Notificaciones
    // ------------------------------------------------------------------

    public static function notificaciones(): void
    {
        App::exigir('ver');
        $tab = in_array($_GET['tab'] ?? '', ['pendientes', 'informativas', 'resueltas'], true) ? $_GET['tab'] : 'pendientes';
        $w = match ($tab) {
            'pendientes' => 'i.resuelta = 0 AND i.requiere_validacion = 1',
            'informativas' => 'i.resuelta = 0 AND i.requiere_validacion = 0',
            'resueltas' => 'i.resuelta = 1',
        };
        $p = [];
        if (!empty($_GET['caja'])) {
            $w .= ' AND i.caja = ?';
            $p[] = (int)$_GET['caja'];
        }
        if (!empty($_GET['codigo'])) {
            $w .= ' AND i.codigo = ?';
            $p[] = (string)$_GET['codigo'];
        }
        $st = self::db()->prepare("SELECT i.*, p.tipo proceso_tipo, p.iniciado_en, u.nombre resuelta_por_nombre, u.email resuelta_por_email,
                r.id registro_id, c.id caja_id
            FROM incidencia i JOIN proceso p ON p.id = i.proceso_id
            LEFT JOIN usuario u ON u.id = i.resuelta_por
            LEFT JOIN caja c ON c.numero = i.caja
            LEFT JOIN registro r ON r.caja_id = c.id AND r.numero = i.registro
            WHERE $w ORDER BY " . ($tab === 'resueltas' ? 'i.resuelta_en DESC' : 'i.caja, i.registro, i.codigo') . ' LIMIT 500');
        $st->execute($p);
        $cont = self::db()->query('SELECT
              SUM(resuelta = 0 AND requiere_validacion = 1) pendientes,
              SUM(resuelta = 0 AND requiere_validacion = 0) informativas,
              SUM(resuelta = 1) resueltas FROM incidencia')->fetch();
        $tipos = self::db()->query("SELECT codigo, COUNT(*) n FROM incidencia i WHERE " . preg_replace('/ AND i\.(caja|codigo) = \?/', '', $w) . ' GROUP BY codigo ORDER BY n DESC')->fetchAll();
        App::render('notificaciones', ['tab' => $tab, 'items' => $st->fetchAll(), 'cont' => $cont, 'tipos' => $tipos]);
    }

    public static function validar(): void
    {
        App::exigir('validar');
        App::verificarPost();
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        $nota = trim((string)($_POST['nota'] ?? ''));
        if (!$ids) {
            App::aviso('error', 'No elegiste ninguna verificación.');
        } elseif ($nota === '') {
            App::aviso('error', 'Escribí una nota con lo que decidiste: queda registrada junto a la validación.');
        } else {
            $st = self::db()->prepare('UPDATE incidencia SET resuelta = 1, resuelta_por = ?, resuelta_en = NOW(), resolucion = ? WHERE id = ? AND resuelta = 0');
            $n = 0;
            foreach ($ids as $id) {
                $st->execute([App::usuarioId(), mb_substr($nota, 0, 500), $id]);
                $n += $st->rowCount();
            }
            App::aviso('ok', $n === 1 ? 'Verificación validada.' : "$n verificaciones validadas.");
        }
        $volver = (string)($_POST['volver'] ?? '');
        header('Location: ' . (str_starts_with($volver, '?r=') ? $volver : App::url('notificaciones')));
        exit;
    }

    // ------------------------------------------------------------------
    // Importaciones y escaneos
    // ------------------------------------------------------------------

    public static function procesos(): void
    {
        App::exigir('ver');
        $rows = self::db()->query("SELECT p.*,
              (SELECT COUNT(*) FROM auditoria a WHERE a.proceso_id = p.id AND a.accion <> 'reversion') cambios,
              (SELECT COUNT(*) FROM auditoria a WHERE a.proceso_id = p.id AND a.accion <> 'reversion' AND a.revertido_en IS NOT NULL) deshechos,
              (SELECT COUNT(*) FROM incidencia i WHERE i.proceso_id = p.id AND i.resuelta = 0 AND i.requiere_validacion = 1) pendientes
            FROM proceso p WHERE p.simulacion = 0 ORDER BY p.id DESC LIMIT 200")->fetchAll();
        App::render('procesos', ['procesos' => $rows]);
    }

    public static function proceso_deshacer(): void
    {
        App::exigir('deshacer_todo');
        $id = self::int('id');
        $p = self::db()->prepare('SELECT * FROM proceso WHERE id = ?');
        $p->execute([$id]);
        $proc = $p->fetch() ?: App::redirigir('procesos');
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            App::verificarPost();
            $res = self::historial()->revertirProceso($id, !empty($_POST['forzar']) && App::puede('forzar'));
            if (!$res['ok'] && $res['conflictos']) {
                $_SESSION['conflicto'] = ['proceso' => $id, 'conflictos' => array_slice($res['conflictos'], 0, 50)];
            }
            App::aviso($res['ok'] ? 'ok' : 'error', $res['mensaje']);
            App::redirigir('procesos');
        }
        $n = self::db()->prepare("SELECT accion, entidad, COUNT(*) n FROM auditoria WHERE proceso_id = ? AND accion <> 'reversion' AND revertido_en IS NULL GROUP BY accion, entidad");
        $n->execute([$id]);
        $det = array_map(fn($x) => "{$x['n']} " . ($x['accion'] === 'alta' ? 'altas' : 'modificaciones') . " de {$x['entidad']}", $n->fetchAll());
        App::render('confirmar', [
            'titulo' => "Deshacer la {$proc['tipo']} #{$id}",
            'texto' => "Se van a deshacer " . ($det ? implode(', ', $det) : 'sus cambios') . " (del {$proc['fuente']}, " . fecha($proc['iniciado_en']) . '). '
                     . 'Las altas quedan dadas de baja y las modificaciones vuelven al valor anterior. '
                     . 'Si algún dato se modificó después, no se aplica nada y se muestran los conflictos.',
            'accion' => App::url('proceso_deshacer', ['id' => $id]),
            'boton' => 'Deshacer la importación',
            'volver' => App::url('procesos'),
            'peligro' => true,
        ]);
    }

    // ------------------------------------------------------------------
    // Vocabularios
    // ------------------------------------------------------------------

    public static function vocabularios(): void
    {
        App::exigir('ver');
        $tipo = ($_GET['tipo'] ?? '') === 'descriptor' ? 'descriptor' : 'tipo_documento';
        $st = self::db()->prepare('SELECT v.*, d.nombre fusion_nombre,
              (SELECT COUNT(*) FROM registro_vocabulario rv JOIN registro r ON r.id = rv.registro_id
               WHERE rv.vocabulario_id = v.id AND r.eliminado_en IS NULL) usos
            FROM vocabulario v LEFT JOIN vocabulario d ON d.id = v.fusionado_en
            WHERE v.tipo = ? ORDER BY v.fusionado_en IS NOT NULL, v.estado DESC, v.nombre');
        $st->execute([$tipo]);
        App::render('vocabularios', ['tipo' => $tipo, 'terminos' => $st->fetchAll()]);
    }

    public static function vocab_accion(): void
    {
        App::exigir('vocabularios');
        App::verificarPost();
        $db = self::db();
        $tipo = ($_POST['tipo'] ?? '') === 'descriptor' ? 'descriptor' : 'tipo_documento';
        $acc = (string)($_POST['accion'] ?? '');
        $id = self::int('id');
        try {
            switch ($acc) {
                case 'nuevo':
                    $n = trim((string)($_POST['nombre'] ?? ''));
                    if ($n === '') {
                        throw new \RuntimeException('Falta el nombre del término.');
                    }
                    $db->prepare("INSERT INTO vocabulario (tipo, nombre, clave, estado) VALUES (?,?,?,'aprobado')")
                       ->execute([$tipo, $n, Texto::clave($n)]);
                    App::aviso('ok', "Término «{$n}» agregado.");
                    break;
                case 'aprobar_todos':
                    $st = $db->prepare("UPDATE vocabulario SET estado = 'aprobado' WHERE tipo = ? AND estado = 'propuesto' AND fusionado_en IS NULL");
                    $st->execute([$tipo]);
                    App::aviso('ok', $st->rowCount() . ' términos aprobados.');
                    break;
                case 'aprobar':
                    $db->prepare("UPDATE vocabulario SET estado = 'aprobado' WHERE id = ?")->execute([$id]);
                    App::aviso('ok', 'Término aprobado.');
                    break;
                case 'renombrar':
                    $n = trim((string)($_POST['nombre'] ?? ''));
                    $db->prepare('UPDATE vocabulario SET nombre = ? WHERE id = ?')->execute([$n, $id]);
                    App::aviso('ok', 'Término renombrado. Se ve así en todos los registros que lo usan.');
                    break;
                case 'fusionar':
                    $destino = (int)($_POST['destino'] ?? 0);
                    if ($destino === $id || $destino === 0) {
                        throw new \RuntimeException('Elegí otro término como destino de la fusión.');
                    }
                    $db->beginTransaction();
                    // Los registros que ya tenían el destino no lo duplican
                    $db->prepare('UPDATE IGNORE registro_vocabulario SET vocabulario_id = ? WHERE vocabulario_id = ?')->execute([$destino, $id]);
                    $db->prepare('DELETE FROM registro_vocabulario WHERE vocabulario_id = ?')->execute([$id]);
                    $db->prepare('UPDATE vocabulario SET fusionado_en = ? WHERE id = ?')->execute([$destino, $id]);
                    $db->commit();
                    App::aviso('ok', 'Términos fusionados. Las próximas importaciones usan el término destino.');
                    break;
                default:
                    throw new \RuntimeException('Acción desconocida.');
            }
        } catch (\PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            App::aviso('error', str_contains($e->getMessage(), 'Duplicate') ? 'Ya existe un término con ese nombre.' : 'No se pudo guardar.');
        } catch (\RuntimeException $e) {
            App::aviso('error', $e->getMessage());
        }
        App::redirigir('vocabularios', ['tipo' => $tipo]);
    }

    // ------------------------------------------------------------------
    // Auxiliares
    // ------------------------------------------------------------------

    private static function cargarCaja(int $id): array
    {
        $st = self::db()->prepare('SELECT * FROM caja WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: self::noEncontrado('caja');
    }

    private static function cargarRegistro(int $id): array
    {
        $st = self::db()->prepare('SELECT * FROM registro WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: self::noEncontrado('registro');
    }

    private static function cargarArchivo(int $id): array
    {
        $st = self::db()->prepare('SELECT * FROM archivo_digital WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: self::noEncontrado('archivo');
    }

    private static function noEncontrado(string $que): never
    {
        http_response_code(404);
        App::render('error', ['titulo' => 'No encontrado', 'mensaje' => "No existe ese $que."]);
        exit;
    }

    private static function numeroCajaInvalido(int $num): ?string
    {
        if ($num < 1) {
            return 'El número de caja tiene que ser mayor que cero.';
        }
        $st = self::db()->prepare('SELECT eliminado_en FROM caja WHERE numero = ?');
        $st->execute([$num]);
        $c = $st->fetch();
        return $c ? "Ya existe la caja $num" . ($c['eliminado_en'] ? ' (dada de baja; se puede restaurar)' : '') . '.' : null;
    }

    /** @return array<string,mixed> solo los campos cuyo valor cambia */
    private static function soloCambios(array $actual, array $nuevos): array
    {
        $out = [];
        foreach ($nuevos as $k => $v) {
            $a = $actual[$k] ?? null;
            if (($a === null ? null : (string)$a) !== ($v === null ? null : (string)$v)) {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private static function recalcularDigitalizado(int $registroId): void
    {
        self::db()->prepare('UPDATE registro r SET digitalizado = EXISTS (SELECT 1 FROM archivo_digital a
            WHERE a.registro_id = r.id AND a.presente = 1 AND a.eliminado_en IS NULL) WHERE r.id = ?')->execute([$registroId]);
    }

    /** @return list<array<string,mixed>> */
    private static function alertasAbiertas(int $caja, ?int $registro): array
    {
        $sql = 'SELECT * FROM incidencia WHERE resuelta = 0 AND caja = ?' . ($registro === null ? '' : ' AND registro = ?')
             . ' ORDER BY requiere_validacion DESC, codigo';
        $st = self::db()->prepare($sql);
        $st->execute($registro === null ? [$caja] : [$caja, $registro]);
        return array_map(fn($i) => $i + ['_alerta' => Alertas::de($i['codigo'])], $st->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    private static function historialDe(string $entidad, int $id): array
    {
        $st = self::db()->prepare('SELECT a.*, u.nombre usuario_nombre, u.email usuario_email FROM auditoria a
            LEFT JOIN usuario u ON u.id = a.usuario_id WHERE a.entidad = ? AND a.entidad_id = ? ORDER BY a.id DESC');
        $st->execute([$entidad, $id]);
        return array_map(function ($a) {
            $a['cambios'] = json_decode((string)$a['cambios'], true) ?: [];
            return $a;
        }, $st->fetchAll());
    }

    /** @return array{tipo_documento:list<array>, descriptor:list<array>} */
    private static function vocabularioParaSelect(): array
    {
        $rows = self::db()->query('SELECT tipo, nombre, estado FROM vocabulario WHERE fusionado_en IS NULL ORDER BY nombre')->fetchAll();
        $out = ['tipo_documento' => [], 'descriptor' => []];
        foreach ($rows as $r) {
            $out[$r['tipo']][] = $r;
        }
        return $out;
    }
}
