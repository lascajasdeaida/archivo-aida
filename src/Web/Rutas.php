<?php
declare(strict_types=1);

namespace Aida\Web;

use Aida\Alertas;
use Aida\Curaduria;
use Aida\Historial;
use Aida\Texto;
use Aida\Vocabulario;

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
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'domain' => $p['domain'],
            'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite'] ?: 'Lax']);
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
        $todas = self::db()->query('SELECT id, numero, nombre, resumen FROM caja WHERE eliminado_en IS NULL ORDER BY numero')->fetchAll();
        // Cuentan los grupos donde hay algo para corregir (no los de partes o versiones que ya están bien escritos)
        $paraRevisar = array_filter(self::gruposParecidos($todas), fn($g) => in_array($g['clase'], ['grafia', 'resumen'], true)
            || array_filter($g['cajas'], fn($c) => $c['propuesto'] !== $c['nombre']));
        App::render('cajas', ['cajas' => self::db()->query($sql)->fetchAll(), 'verBajas' => $verBajas,
                              'parecidos' => count($paraRevisar)]);
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
     * Sugiere grupos de cajas con nombres parecidos y permite unificarlos. Cada cambio pasa por el historial
     * (se puede deshacer y bloquea el campo para el Excel). Clases de grupo (docs/backoffice.md §10):
     *  - grafia:     mismo nombre salvo tildes, mayúsculas, comillas o un error de tipeo → unificar.
     *  - parte:      misma obra en varias cajas numeradas («Cenizas del Paraíso» / «… II») → no unificar;
     *                a lo sumo unificar el título conservando el numeral de cada caja.
     *  - aclaracion: mismo título con distinta aclaración («(España)» / «(Argentina)») → versiones distintas;
     *                a lo sumo unificar el título conservando la aclaración de cada caja.
     *  - resumen:    nombres distintos que el resumen de una caja presenta juntos («Cajas 8 y 12») → revisar.
     */
    public static function cajas_unificar(): void
    {
        App::exigir('editar');
        $cajas = self::db()->query('SELECT id, numero, nombre, resumen, campos_bloqueados FROM caja WHERE eliminado_en IS NULL ORDER BY numero')->fetchAll();
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            App::verificarPost();
            $modo = ($_POST['modo'] ?? '') === 'conservar' ? 'conservar' : 'unico';
            $nombre = trim((string)preg_replace('/\s+/u', ' ', (string)($_POST['nombre'] ?? '')));
            $ids = array_map('intval', (array)($_POST['cajas'] ?? []));
            if ($nombre === '' || count($ids) < 1) {
                App::aviso('error', $modo === 'conservar' ? 'Elegí al menos una caja y escribí cómo tiene que quedar el título.'
                                                          : 'Elegí al menos una caja y escribí cómo tiene que quedar el nombre.');
            } else {
                $partes = [];
                foreach (self::gruposParecidos($cajas) as $g) {
                    foreach ($g['cajas'] as $c) {
                        $partes[(int)$c['id']] = $c;
                    }
                }
                $h = self::historial();
                $n = 0;
                $nuevos = [];
                foreach ($cajas as $c) {
                    if (!in_array((int)$c['id'], $ids, true)) {
                        continue;
                    }
                    $nuevo = $modo === 'conservar' && isset($partes[(int)$c['id']])
                        ? self::componerNombre($nombre, $partes[(int)$c['id']]['parte'], $partes[(int)$c['id']]['aclaracion'])
                        : $nombre;
                    if ($c['nombre'] !== $nuevo) {
                        $h->actualizar('caja', (int)$c['id'], ['nombre' => $nuevo], true);
                        $nuevos[] = "{$c['numero']} «{$nuevo}»";
                        $n++;
                    }
                }
                App::aviso('ok', $n ? 'Nombre corregido en ' . ($n === 1 ? '1 caja' : "$n cajas") . ': ' . implode(', ', $nuevos) . '. Se puede deshacer desde el historial de cada caja.'
                                    : 'Esas cajas ya tenían ese nombre.');
            }
            App::redirigir('cajas_unificar');
        }
        App::render('cajas_unificar', ['grupos' => self::gruposParecidos($cajas)]);
    }

    private static function componerNombre(string $titulo, string $parte, ?string $aclaracion): string
    {
        return $titulo . ($parte !== '' ? " $parte" : '') . ($aclaracion !== null && $aclaracion !== '' ? " ($aclaracion)" : '');
    }

    /**
     * Separa un nombre de caja en título, parte final (numeral: «II», «2», «91/92/93») y aclaración final entre paréntesis.
     * @return array{titulo:string, parte:string, aclaracion:?string}
     */
    private static function partesNombre(string $nombre): array
    {
        $n = trim((string)preg_replace(['/["“”«»]/u', '/\s+/u'], ['', ' '], $nombre));
        $acl = null;
        if (preg_match('/^(.+?)\s*\(([^()]*)\)\s*$/u', $n, $m)) {
            [$n, $acl] = [trim($m[1]), trim($m[2])];
        }
        $tokens = explode(' ', $n);
        $parte = [];
        while (count($tokens) > 1 && preg_match('~^(i{1,3}|iv|vi{0,3}|ix|x|\d+[a-z]?(?:[/\-.]\d+)*)$~u', Texto::clave(end($tokens)))) {
            array_unshift($parte, array_pop($tokens));
        }
        return ['titulo' => implode(' ', $tokens), 'parte' => implode(' ', $parte), 'aclaracion' => $acl];
    }

    /** @return list<array{clase:string, cajas:list<array>, sugerido:string, titulo:string, evidencia:list<string>}> */
    private static function gruposParecidos(array $cajas): array
    {
        $base = function (?string $s): string {
            $s = Texto::clave((string)$s);
            $s = (string)preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $s);
            return trim((string)preg_replace('/\s+/', ' ', $s));
        };
        $cajas = array_values($cajas);
        $n = count($cajas);
        $p = array_map(fn($c) => self::partesNombre((string)$c['nombre']), $cajas);
        // Aclaración escrita sin paréntesis: «Eramos tan jóvenes España» frente a «Éramos tan jóvenes (España)»
        for ($i = 0; $i < $n; $i++) {
            if ($p[$i]['aclaracion'] !== null) {
                continue;
            }
            for ($j = 0; $j < $n; $j++) {
                $acl = $p[$j]['aclaracion'];
                if ($j === $i || $acl === null || $base($acl) === '') {
                    continue;
                }
                $palabras = count(explode(' ', $acl));
                $tok = explode(' ', $p[$i]['titulo']);
                if (count($tok) > $palabras && $base($p[$i]['titulo']) === $base($p[$j]['titulo']) . ' ' . $base($acl)) {
                    $p[$i]['aclaracion'] = implode(' ', array_splice($tok, -$palabras));
                    $p[$i]['titulo'] = implode(' ', $tok);
                    break;
                }
            }
        }
        $kt = array_map(fn($x) => $base($x['titulo']), $p);
        $ka = array_map(fn($x) => $base($x['aclaracion']), $p);
        $kp = array_map(fn($x) => $base($x['parte']), $p);

        // Cajas que un resumen presenta juntas: «Cajas 8 y 12», «3 CAJAS, las: 17, 29 y 32»
        $porNumero = [];
        foreach ($cajas as $i => $c) {
            $porNumero[(int)$c['numero']] = $i;
        }
        $vinculos = [];
        foreach ($cajas as $c) {
            if (preg_match_all('/cajas?[^.\d]{0,15}((?:\d+\s*,\s*)*\d+\s+y\s+\d+)/iu', (string)$c['resumen'], $mm, PREG_SET_ORDER)) {
                foreach ($mm as $m) {
                    preg_match_all('/\d+/', $m[1], $nums);
                    $nums = array_values(array_unique(array_filter(array_map('intval', $nums[0]), fn($x) => isset($porNumero[$x]))));
                    foreach ($nums as $a) {
                        foreach ($nums as $b) {
                            if ($a < $b) {
                                $vinculos["$a-$b"][] = 'El resumen de la caja ' . $c['numero'] . ' dice «' . trim(mb_substr($m[0], 0, 60)) . '»';
                            }
                        }
                    }
                }
            }
        }

        $padre = range(0, max(0, $n - 1));
        $raiz = function (int $i) use (&$padre, &$raiz): int { return $padre[$i] === $i ? $i : ($padre[$i] = $raiz($padre[$i])); };
        $clases = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $clase = null;
                $parecido = $kt[$i] !== '' && ($kt[$i] === $kt[$j] || (strlen($kt[$i]) >= 6 && strlen($kt[$j]) >= 6
                    && strlen($kt[$i]) < 255 && strlen($kt[$j]) < 255 && levenshtein($kt[$i], $kt[$j]) / max(strlen($kt[$i]), strlen($kt[$j])) <= 0.15));
                if ($cajas[$i]['nombre'] === $cajas[$j]['nombre']) {
                    $clase = 'igual';
                } elseif ($parecido) {
                    $clase = $ka[$i] !== $ka[$j] ? 'aclaracion' : ($kp[$i] !== $kp[$j] ? 'parte' : 'grafia');
                }
                $a = min((int)$cajas[$i]['numero'], (int)$cajas[$j]['numero']);
                $b = max((int)$cajas[$i]['numero'], (int)$cajas[$j]['numero']);
                if ($clase === null && isset($vinculos["$a-$b"])) {
                    $clase = 'resumen';
                }
                if ($clase !== null) {
                    $padre[$raiz($i)] = $raiz($j);
                    $clases[] = [$i, $j, $clase];
                }
            }
        }
        $grupos = [];
        foreach ($cajas as $i => $c) {
            $grupos[$raiz($i)][] = $i;
        }
        $prioridad = ['aclaracion', 'parte', 'grafia', 'resumen'];
        $puntaje = function (string $nom): float {
            $letras = max(1, preg_match_all('/\p{L}/u', $nom));
            $mayus = preg_match_all('/\p{Lu}/u', $nom);
            return preg_match_all('/[áéíóúÁÉÍÓÚñÑü]/u', $nom) * 10 + (1 - $mayus / $letras) * 9 + mb_strlen($nom) / 1000;
        };
        $out = [];
        foreach ($grupos as $r => $miembros) {
            if (count($miembros) < 2 || count(array_unique(array_map(fn($i) => $cajas[$i]['nombre'], $miembros))) < 2) {
                continue; // mismos nombres exactos: no hay nada que unificar
            }
            $presentes = [];
            $evid = [];
            foreach ($clases as [$i, $j, $cl]) {
                if ($raiz($i) === $r) {
                    $presentes[$cl] = true;
                }
            }
            foreach ($miembros as $i) {
                foreach ($miembros as $j) {
                    $k = $cajas[$i]['numero'] . '-' . $cajas[$j]['numero'];
                    foreach ($vinculos[$k] ?? [] as $e) {
                        $evid[$e] = true;
                    }
                }
            }
            $clase = 'resumen';
            foreach ($prioridad as $cl) {
                if (isset($presentes[$cl])) {
                    $clase = $cl;
                    break;
                }
            }
            // Título sugerido: la variante con más tildes; a igualdad, la que no está toda en mayúsculas, y la más larga
            $titulo = '';
            $mejor = -1;
            $nombreMejor = '';
            $mejorN = -1;
            foreach ($miembros as $i) {
                if (($x = $puntaje($p[$i]['titulo'])) > $mejor) {
                    [$mejor, $titulo] = [$x, $p[$i]['titulo']];
                }
                $completo = self::componerNombre($p[$i]['titulo'], $p[$i]['parte'], $p[$i]['aclaracion']);
                if (($x = $puntaje($completo)) > $mejorN) {
                    [$mejorN, $nombreMejor] = [$x, $completo];
                }
            }
            $sugerido = $nombreMejor;
            if ($clase === 'resumen') {
                // Solo si un nombre contiene al otro («Azucena» / «Azucena Villaflor»); si no, que lo escriba el operador
                $sugerido = '';
                foreach ($miembros as $i) {
                    $contiene = true;
                    foreach ($miembros as $j) {
                        if ($i !== $j && !str_contains($kt[$i], $kt[$j])) {
                            $contiene = false;
                        }
                    }
                    if ($contiene) {
                        $sugerido = $p[$i]['titulo'];
                    }
                }
            }
            $lista = [];
            foreach ($miembros as $i) {
                $lista[] = $cajas[$i] + $p[$i] + ['propuesto' => self::componerNombre($titulo, $p[$i]['parte'], $p[$i]['aclaracion'])];
            }
            usort($lista, fn($a, $b) => $a['numero'] <=> $b['numero']);
            $out[] = ['clase' => $clase, 'cajas' => $lista, 'sugerido' => $sugerido, 'titulo' => $titulo, 'evidencia' => array_keys($evid)];
        }
        usort($out, fn($a, $b) => [array_search($a['clase'], ['grafia', 'resumen', 'parte', 'aclaracion'], true), $a['cajas'][0]['numero']]
                                 <=> [array_search($b['clase'], ['grafia', 'resumen', 'parte', 'aclaracion'], true), $b['cajas'][0]['numero']]);
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
            'migas' => [['Cajas', App::url('cajas')], ["Caja {$c['numero']}", App::url('caja', ['id' => $c['id']])]],
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
            // Mismas reglas que la importación: año, rango («1981/82») o con duda («1972?»)
            $an = Texto::anio($cambios['anio_texto']);
            $cambios['anio'] = $an['desde'];
            $cambios['anio_hasta'] = $an['hasta'];
            $cambios['anio_sin_confirmar'] = (int)$an['sin_confirmar'];
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
        $cj = self::cargarCaja((int)$r['caja_id']);
        App::render('confirmar', [
            'titulo' => "Dar de baja el registro {$r['codigo']}",
            'texto' => "Vas a dar de baja {$r['codigo']}" . ($r['titulo'] ? " · «{$r['titulo']}»" : '') . " (Caja {$cj['numero']} · {$cj['nombre']})"
                     . ($arch ? ($arch === 1 ? ', con 1 archivo asociado que se oculta' : ", con $arch archivos asociados que se ocultan") . ' junto con el registro' : '')
                     . '. Deja de mostrarse en el sitio y la importación no lo vuelve a crear. Se puede restaurar.',
            'migas' => [['Cajas', App::url('cajas')], ["Caja {$cj['numero']}", App::url('caja', ['id' => $cj['id']])], [$r['codigo'], App::url('registro', ['id' => $r['id']])]],
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
            WHERE $w ORDER BY " . ($tab === 'resueltas' ? 'i.codigo, i.resuelta_en DESC' : 'i.codigo, i.caja, i.registro') . ' LIMIT 500');
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
            FROM proceso p WHERE p.simulacion = 0 AND p.tipo <> 'curaduria' ORDER BY p.id DESC LIMIT 200")->fetchAll();
        App::render('procesos', ['procesos' => $rows]);
    }

    /**
     * Subir el Excel y simular. La importación real se habilita recién después de ver la simulación
     * y usa el mismo archivo (ver importar_confirmar).
     */
    public static function importar(): void
    {
        App::exigir('procesos');
        if (CargaExcel::postDemasiadoGrande()) {
            App::aviso('error', 'El archivo supera el máximo permitido (' . number_format(CargaExcel::maxBytes() / 1048576, 0, ',', '.') . ' MB).');
            App::redirigir('importar');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            App::verificarPost();
            $f = $_FILES['excel'] ?? null;
            $cajas = CargaExcel::cajas((string)($_POST['cajas'] ?? ''));
            $err = CargaExcel::validar($f) ?? ($cajas === false ? 'Las cajas se indican con números separados por comas o rangos, por ejemplo: 1, 2, 10-14.' : null);
            if ($err) {
                App::aviso('error', $err);
                App::redirigir('importar');
            }
            $dir = CargaExcel::directorio();
            CargaExcel::limpiarViejas($dir);
            self::descartarSimulacion();
            $nombre = 'sim-' . bin2hex(random_bytes(12)) . '.xlsx';
            if (!move_uploaded_file($f['tmp_name'], "$dir/$nombre")) {
                App::aviso('error', 'No se pudo guardar el archivo en el servidor. Avisá al equipo técnico.');
                App::redirigir('importar');
            }
            @chmod("$dir/$nombre", 0640);
            $original = CargaExcel::nombreOriginal((string)$f['name']);
            try {
                [$rep, $seg] = CargaExcel::correr("$dir/$nombre", $cajas, true, $original);
            } catch (\Throwable $e) {
                @unlink("$dir/$nombre");
                if (!$e instanceof \RuntimeException || $e->getCode() !== 409) {
                    error_log('[aida-bo] simulación: ' . $e);
                }
                App::aviso('error', $e instanceof \RuntimeException && $e->getCode() === 409 ? $e->getMessage()
                    : 'No se pudo leer el Excel. Revisá que sea el catálogo (una pestaña por caja) y que no esté dañado.');
                App::redirigir('importar');
            }
            $_SESSION['importacion'] = CargaExcel::resumen($rep) + [
                'token' => bin2hex(random_bytes(16)), 'archivo' => $nombre, 'original' => $original,
                'sha1' => sha1_file("$dir/$nombre"), 'bytes' => (int)filesize("$dir/$nombre"),
                'cajas' => $cajas, 'en' => time(), 'segundos' => $seg, 'usuario' => App::usuarioId(),
            ];
            unset($_SESSION['importacion_hecha']);
            App::redirigir('importar');
        }
        $sim = self::simulacionVigente();
        App::render('importar', [
            'sim' => $sim,
            'hecha' => $sim ? null : ($_SESSION['importacion_hecha'] ?? null),
            'maxBytes' => CargaExcel::maxBytes(),
        ]);
    }

    /** Importación real del archivo que se acaba de simular. */
    public static function importar_confirmar(): void
    {
        App::exigir('procesos');
        App::verificarPost();
        $sim = self::simulacionVigente();
        if (!$sim || !hash_equals($sim['token'], (string)($_POST['simulacion'] ?? ''))) {
            App::aviso('error', 'La simulación venció o ya se usó. Subí el Excel de nuevo para simular antes de importar.');
            App::redirigir('importar');
        }
        $dir = CargaExcel::directorio();
        $ruta = "$dir/{$sim['archivo']}";
        try {
            [$rep, $seg] = CargaExcel::correr($ruta, $sim['cajas'], false, $sim['original']);
        } catch (\Throwable $e) {
            $ocupado = $e instanceof \RuntimeException && $e->getCode() === 409;
            if (!$ocupado) {
                error_log('[aida-bo] importación: ' . $e);
            }
            App::aviso('error', $ocupado ? $e->getMessage() : 'La importación falló y no se guardó nada. Ya quedó registrado; avisá al equipo técnico.');
            App::redirigir('importar');
        }
        // Se conserva el Excel importado, con el número de proceso, para saber qué planilla generó cada cambio.
        @rename($ruta, sprintf('%s/importacion-%d-%s.xlsx', $dir, (int)$rep->procesoId, date('Ymd-His')));
        unset($_SESSION['importacion']);
        $_SESSION['importacion_hecha'] = CargaExcel::resumen($rep) + [
            'proceso' => $rep->procesoId, 'original' => $sim['original'], 'cajas' => $sim['cajas'], 'segundos' => $seg,
        ];
        App::aviso('ok', "Importación #{$rep->procesoId} terminada en " . number_format($seg, 1, ',', '.') . ' s. Se puede deshacer completa desde la lista.');
        App::redirigir('importar');
    }

    public static function importar_descartar(): void
    {
        App::exigir('procesos');
        App::verificarPost();
        self::descartarSimulacion();
        unset($_SESSION['importacion_hecha']);
        App::aviso('info', 'Simulación descartada. No se importó nada.');
        App::redirigir('importar');
    }

    /** La simulación guardada en la sesión, si el archivo sigue igual y no venció. */
    private static function simulacionVigente(): ?array
    {
        $s = $_SESSION['importacion'] ?? null;
        if (!$s) {
            return null;
        }
        $ruta = CargaExcel::directorio() . '/' . basename((string)$s['archivo']);
        if (time() - (int)$s['en'] > CargaExcel::VIGENCIA || !is_file($ruta) || sha1_file($ruta) !== $s['sha1']) {
            self::descartarSimulacion();
            return null;
        }
        return $s;
    }

    private static function descartarSimulacion(): void
    {
        $s = $_SESSION['importacion'] ?? null;
        if ($s && preg_match('/^sim-[0-9a-f]{24}\.xlsx$/', (string)$s['archivo'])) {
            @unlink(CargaExcel::directorio() . '/' . $s['archivo']);
        }
        unset($_SESSION['importacion']);
    }

    public static function proceso_deshacer(): void
    {
        App::exigir('deshacer_todo');
        $id = self::int('id');
        $p = self::db()->prepare('SELECT * FROM proceso WHERE id = ?');
        $p->execute([$id]);
        $proc = $p->fetch() ?: App::redirigir('procesos');
        if ($proc['tipo'] === 'curaduria') {
            App::redirigir('curaduria_deshacer', ['id' => $id]);
        }
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
            'migas' => [['Importaciones', App::url('procesos')]],
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
        $st = self::db()->prepare('SELECT v.*, d.nombre fusion_nombre, d.tipo fusion_tipo,
              (SELECT COUNT(*) FROM registro_vocabulario rv JOIN registro r ON r.id = rv.registro_id
               WHERE rv.vocabulario_id = v.id AND r.eliminado_en IS NULL) usos,
              (SELECT GROUP_CONCAT(a.nombre ORDER BY a.nombre SEPARATOR \'», «\') FROM vocabulario_alias a
               WHERE a.vocabulario_id = v.id AND a.eliminado_en IS NULL) alias
            FROM vocabulario v LEFT JOIN vocabulario d ON d.id = v.fusionado_en
            WHERE v.tipo = ? AND v.eliminado_en IS NULL
            ORDER BY v.fusionado_en IS NOT NULL, v.estado = \'descartado\', v.estado DESC, v.nombre');
        $st->execute([$tipo]);
        $cambios = (int)self::db()->query("SELECT COUNT(*) FROM proceso WHERE tipo = 'curaduria'")->fetchColumn();
        App::render('vocabularios', ['tipo' => $tipo, 'terminos' => $st->fetchAll(), 'cambiosCuraduria' => $cambios]);
    }

    /** Acciones simples sobre términos: nuevo, aprobar, renombrar, restaurar. Todas quedan en el historial. */
    public static function vocab_accion(): void
    {
        App::exigir('vocabularios');
        App::verificarPost();
        $db = self::db();
        $tipo = ($_POST['tipo'] ?? '') === 'descriptor' ? 'descriptor' : 'tipo_documento';
        $acc = (string)($_POST['accion'] ?? '');
        $id = self::int('id');
        $h = self::historial();
        try {
            switch ($acc) {
                case 'nuevo':
                    $n = trim((string)preg_replace('/\s+/u', ' ', (string)($_POST['nombre'] ?? '')));
                    if ($n === '' || mb_strlen($n) > 120) {
                        throw new \RuntimeException('El nombre del término tiene que tener entre 1 y 120 caracteres.');
                    }
                    if ($r = (new Vocabulario($db))->resolver($tipo, $n)) {
                        if ($r['via'] !== 'eliminado') {
                            throw new \RuntimeException("«{$n}» ya se reconoce como «{$r['final']['nombre']}» ("
                                . Vocabulario::NOMBRES[$r['final']['tipo']] . ($r['final']['estado'] === 'descartado' ? ', descartado' : '') . ').');
                        }
                    }
                    $h->crear('vocabulario', ['tipo' => $tipo, 'nombre' => $n, 'clave' => Texto::clave($n), 'estado' => 'aprobado']);
                    App::aviso('ok', "Término «{$n}» agregado.");
                    break;
                case 'aprobar_todos':
                    $st = $db->prepare("SELECT id FROM vocabulario WHERE tipo = ? AND estado = 'propuesto' AND fusionado_en IS NULL AND eliminado_en IS NULL");
                    $st->execute([$tipo]);
                    $ids = $st->fetchAll(\PDO::FETCH_COLUMN);
                    $db->beginTransaction();
                    foreach ($ids as $v) {
                        $h->actualizar('vocabulario', (int)$v, ['estado' => 'aprobado'], false);
                    }
                    $db->commit();
                    App::aviso('ok', count($ids) . ' términos aprobados. Se puede deshacer uno por uno desde el historial de cada término.');
                    break;
                case 'aprobar':
                    $h->actualizar('vocabulario', $id, ['estado' => 'aprobado'], false);
                    App::aviso('ok', 'Término aprobado.');
                    break;
                case 'renombrar':
                    $r = (new Curaduria($db, App::usuarioId()))->renombrar($id, (string)($_POST['nombre'] ?? ''), (string)($_POST['motivo'] ?? ''));
                    App::aviso('ok', 'Término renombrado: se ve así en ' . ($r['registros'] === 1 ? 'el registro que lo usa' : "los {$r['registros']} registros que lo usan")
                        . '. Si la planilla trae la grafía anterior, la importación carga este término.');
                    break;
                case 'restaurar':
                    $r = (new Curaduria($db, App::usuarioId()))->restaurar($id, !empty($_POST['forzar']) && App::puede('forzar'));
                    if (!$r['ok'] && $r['conflictos']) {
                        $_SESSION['conflicto'] = ['curaduria' => $r['proceso'] ?? 0, 'conflictos' => array_slice($r['conflictos'], 0, 50)];
                    }
                    App::aviso($r['ok'] ? 'ok' : 'error', $r['ok'] ? 'Término restaurado: vuelve a sus registros y a la lista.' : $r['mensaje']);
                    break;
                default:
                    throw new \RuntimeException('Acción desconocida.');
            }
        } catch (\PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[aida-bo] vocabulario: ' . $e->getMessage());
            App::aviso('error', str_contains($e->getMessage(), 'Duplicate') ? 'Ya existe un término con ese nombre.' : 'No se pudo guardar.');
        } catch (\RuntimeException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            App::aviso('error', $e->getMessage());
        }
        App::redirigir('vocabularios', ['tipo' => $tipo]);
    }

    /**
     * Mover al otro vocabulario, fusionar o descartar: pantalla de confirmación (GET) y ejecución (POST).
     * Muestra cuántos registros cambian, cuáles quedan sin tipo/descriptor y los destinos equivalentes.
     */
    public static function vocab_curar(): void
    {
        App::exigir('vocabularios');
        $db = self::db();
        $op = in_array($_REQUEST['op'] ?? '', ['mover', 'fusionar', 'descartar'], true) ? $_REQUEST['op'] : 'mover';
        $id = self::int('id');
        $cur = new Curaduria($db, App::usuarioId());
        try {
            $imp = $cur->impacto($id);
        } catch (\RuntimeException $e) {
            App::aviso('error', $e->getMessage());
            App::redirigir('vocabularios');
        }
        $t = $imp['termino'];
        $tipoDest = $op === 'mover' ? Vocabulario::otro($t['tipo']) : $t['tipo'];
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            App::verificarPost();
            $motivo = (string)($_POST['motivo'] ?? '');
            try {
                if ($op === 'descartar') {
                    $r = $cur->descartar($id, $motivo);
                    App::aviso('ok', "«{$t['nombre']}» descartado y quitado de " . ($r['registros'] === 1 ? '1 registro' : "{$r['registros']} registros")
                        . '. Los demás tipos y descriptores no se tocaron. Se puede restaurar.');
                } else {
                    $dest = (string)($_POST['destino'] ?? '');
                    $destId = $dest === 'nuevo' ? null : (int)$dest;
                    if ($op === 'fusionar' || $destId !== null) {
                        if (!$destId) {
                            throw new \RuntimeException('Elegí el término destino.');
                        }
                    }
                    $r = $op === 'mover' ? $cur->mover($id, $destId, $motivo) : $cur->fusionar($id, (int)$destId, $motivo);
                    App::aviso('ok', ($op === 'mover' ? "«{$t['nombre']}» se movió a " . Vocabulario::PLURALES[$tipoDest] . " como «{$r['destino']['nombre']}»"
                                                      : "«{$t['nombre']}» se fusionó en «{$r['destino']['nombre']}»")
                        . ': ' . ($r['registros'] === 1 ? '1 registro' : "{$r['registros']} registros")
                        . (!empty($r['aprobado']) ? ". «{$r['destino']['nombre']}» estaba propuesto y quedó aprobado" : '')
                        . '. La importación ya lo carga así. Se puede deshacer desde Cambios de curaduría.');
                }
                App::redirigir('vocab_informe');
            } catch (\RuntimeException $e) {
                App::aviso('error', $e->getMessage());
                App::redirigir('vocab_curar', ['op' => $op, 'id' => $id]);
            }
        }
        App::render('vocab_curar', [
            'op' => $op, 't' => $t, 'imp' => $imp, 'tipoDest' => $tipoDest,
            'destinos' => $op === 'descartar' ? [] : $cur->destinos($id, $tipoDest),
            'otroIgual' => self::terminoIgual($tipoDest, $t),
        ]);
    }

    /** ¿Hay en $tipo un término con la misma clave que $t? (no se puede crear otro igual). */
    private static function terminoIgual(string $tipo, array $t): bool
    {
        $st = self::db()->prepare('SELECT 1 FROM vocabulario WHERE tipo = ? AND clave = ? AND id <> ? AND eliminado_en IS NULL');
        $st->execute([$tipo, $t['clave'], $t['id']]);
        return (bool)$st->fetchColumn();
    }

    /** Informe de curaduría: movimientos, fusiones, renombres y descartes. ?formato=csv | texto */
    public static function vocab_informe(): void
    {
        App::exigir('ver');
        $cur = new Curaduria(self::db(), App::usuarioId());
        $formato = (string)($_GET['formato'] ?? '');
        if ($formato === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="cambios-curaduria-' . date('Ymd') . '.csv"');
            header('Cache-Control: no-store');
            echo $cur->csv();
            exit;
        }
        App::render('vocab_informe', ['items' => $cur->informe(), 'texto' => $cur->texto()]);
    }

    /** Deshace una operación de curaduría completa (con confirmación). */
    public static function curaduria_deshacer(): void
    {
        App::exigir('vocabularios');
        $id = self::int('id');
        $item = null;
        foreach ((new Curaduria(self::db(), App::usuarioId()))->informe() as $x) {
            if ($x['id'] === $id) {
                $item = $x;
            }
        }
        if (!$item || $item['deshecho']) {
            App::aviso('error', $item ? 'Ese cambio ya se deshizo.' : 'No existe ese cambio de curaduría.');
            App::redirigir('vocab_informe');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            App::verificarPost();
            $res = (new Curaduria(self::db(), App::usuarioId()))->deshacer($id, !empty($_POST['forzar']) && App::puede('forzar'));
            if (!$res['ok'] && $res['conflictos']) {
                $_SESSION['conflicto'] = ['curaduria' => $id, 'conflictos' => array_slice($res['conflictos'], 0, 50)];
            }
            App::aviso($res['ok'] ? 'ok' : 'error', $res['mensaje']);
            App::redirigir('vocab_informe');
        }
        $n = $item['registros'];
        App::render('confirmar', [
            'titulo' => 'Deshacer: ' . $item['descripcion'],
            'texto' => 'Vuelve todo a como estaba antes del ' . fecha($item['fecha']) . ' (' . $item['usuario'] . ')'
                . ($item['accion'] === 'renombrar' ? ': el término recupera su nombre anterior.'
                    : ': ' . ($n === 1 ? '1 registro recupera' : "$n registros recuperan") . ' sus tipos y descriptores de ese momento.')
                . ' Si alguno cambió después, no se aplica nada y se muestran los conflictos. El deshacer también queda en el informe.',
            'migas' => [['Vocabularios', App::url('vocabularios')], ['Cambios de curaduría', App::url('vocab_informe')]],
            'accion' => App::url('curaduria_deshacer', ['id' => $id]),
            'boton' => 'Deshacer el cambio',
            'volver' => App::url('vocab_informe'),
            'peligro' => true,
        ]);
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
        $st = self::db()->prepare('SELECT a.*, u.nombre usuario_nombre, u.email usuario_email, p.tipo proceso_tipo, p.fuente proceso_fuente
            FROM auditoria a LEFT JOIN usuario u ON u.id = a.usuario_id LEFT JOIN proceso p ON p.id = a.proceso_id
            WHERE a.entidad = ? AND a.entidad_id = ? ORDER BY a.id DESC');
        $st->execute([$entidad, $id]);
        return array_map(function ($a) {
            $a['cambios'] = json_decode((string)$a['cambios'], true) ?: [];
            return $a;
        }, $st->fetchAll());
    }

    /** @return array{tipo_documento:list<array>, descriptor:list<array>} */
    private static function vocabularioParaSelect(): array
    {
        $rows = self::db()->query("SELECT tipo, nombre, estado FROM vocabulario WHERE fusionado_en IS NULL AND eliminado_en IS NULL
            AND estado <> 'descartado' ORDER BY nombre")->fetchAll();
        $out = ['tipo_documento' => [], 'descriptor' => []];
        foreach ($rows as $r) {
            $out[$r['tipo']][] = $r;
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Base de datos: migraciones pendientes (para hostings sin consola)
    // ------------------------------------------------------------------

    public static function base(): void
    {
        App::exigir('migraciones');
        $m = new \Aida\Migrador(self::db());
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            App::verificarPost();
            $hechas = [];
            foreach ($m->pendientes() as $v => $f) {
                try {
                    $hechas[] = basename($f) . ' (' . $m->aplicar($v) . ' ms)';
                } catch (\RuntimeException $e) {
                    error_log('[aida-migrar] ' . $e->getMessage());
                    App::aviso('error', 'No se pudo aplicar ' . basename($f) . '. Nada de esa migración quedó registrado; se puede reintentar. Detalle: '
                        . mb_strimwidth($e->getMessage(), 0, 300, '…'));
                    break;
                }
            }
            if ($hechas) {
                App::aviso('ok', 'Migraciones aplicadas: ' . implode(', ', $hechas) . '.');
            }
            App::redirigir('base');
        }
        App::render('base', ['archivos' => $m->archivos, 'aplicadas' => $m->aplicadas(), 'pendientes' => $m->pendientes(),
            'modificadas' => $m->modificadas()]);
    }
}
