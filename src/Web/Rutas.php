<?php
declare(strict_types=1);

namespace Aida\Web;

use Aida\Alertas;
use Aida\Archivos\Drive;
use Aida\Archivos\Espacio;
use Aida\Archivos\ListadoDrive;
use Aida\Archivos\Origenes;
use Aida\Escaner;
use Aida\Consultas;
use Aida\Anios;
use Aida\Correccion;
use Aida\Curaduria;
use Aida\Historial;
use Aida\Sitio;
use Aida\Intentos;
use Aida\Texto;
use Aida\Usuarios;
use Aida\Correo;
use Aida\Turnstile;
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
            if (!Turnstile::verificar('login')) {
                App::render('login', ['error' => 'No pudimos verificar que no seas un robot. Esperá a que aparezca el tilde y volvé a probar.']);
                return;
            }
            $email = Usuarios::normalizarEmail((string)($_POST['email'] ?? ''));
            $intentos = new Intentos(self::db());
            if ($intentos->loginBloqueado($email)) {
                // Mismo mensaje exista o no la cuenta; no se prueba la contraseña mientras dure el bloqueo
                usleep(400000);
                App::render('login', ['error' => 'Demasiados intentos fallidos. Esperá unos minutos antes de volver a probar, o usá “Olvidé mi contraseña”.']);
                return;
            }
            $st = self::db()->prepare('SELECT * FROM usuario WHERE email = ? AND activo = 1');
            $st->execute([$email]);
            $u = $st->fetch();
            if ($u && password_verify((string)($_POST['clave'] ?? ''), $u['password_hash']) && $u['rol'] !== 'visitante') {
                $intentos->loginCorrecto($email);
                App::abrirSesionUsuario($u);
                (new Usuarios(self::db()))->registrarIngreso((int)$u['id']);
                App::redirigir('cajas');
            }
            $intentos->loginFallido($email);
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
        $pub = in_array($_GET['pub'] ?? '', ['1', '0'], true) ? (string)$_GET['pub'] : '';
        $where = array_filter([$verBajas ? '' : 'c.eliminado_en IS NULL', $pub !== '' ? 'c.publicada = ' . (int)$pub : '']);
        $sql = 'SELECT c.*,
                  (SELECT COUNT(*) FROM registro r WHERE r.caja_id = c.id AND r.eliminado_en IS NULL) registros,
                  (SELECT COUNT(*) FROM registro r WHERE r.caja_id = c.id AND r.eliminado_en IS NULL AND r.digitalizado = 1) digitalizados,
                  (SELECT COUNT(*) FROM registro r WHERE r.caja_id = c.id AND r.eliminado_en IS NULL AND r.acceso <> \'publico\') no_publicos,
                  (SELECT COUNT(*) FROM incidencia i WHERE i.caja = c.numero AND i.resuelta = 0 AND i.requiere_validacion = 1) alertas
                FROM caja c ' . ($where ? 'WHERE ' . implode(' AND ', $where) . ' ' : '') . 'ORDER BY c.numero';
        $publicadas = self::db()->query('SELECT SUM(publicada = 1), SUM(publicada = 0) FROM caja WHERE eliminado_en IS NULL')->fetch(\PDO::FETCH_NUM);
        $todas = self::db()->query('SELECT id, numero, nombre, resumen FROM caja WHERE eliminado_en IS NULL ORDER BY numero')->fetchAll();
        // Cuentan los grupos donde hay algo para corregir (no los de partes o versiones que ya están bien escritos)
        $paraRevisar = array_filter(self::gruposParecidos($todas), fn($g) => in_array($g['clase'], ['grafia', 'resumen'], true)
            || array_filter($g['cajas'], fn($c) => $c['propuesto'] !== $c['nombre']));
        App::render('cajas', ['cajas' => self::db()->query($sql)->fetchAll(), 'verBajas' => $verBajas, 'pub' => $pub,
                              'nPublicadas' => (int)$publicadas[0], 'nSinPublicar' => (int)$publicadas[1],
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
            'publicacion' => self::publicacionCaja((int)$c['id']),
        ]);
    }

    /** Qué mostraría el sitio de la caja al publicarla (para el panel “Publicación en el sitio”). */
    private static function publicacionCaja(int $id): array
    {
        $st = self::db()->prepare("SELECT SUM(eliminado_en IS NULL AND publicado = 1 AND acceso <> 'a_revisar') visibles,
                SUM(eliminado_en IS NULL AND publicado = 1 AND acceso = 'restringido') a_pedido,
                SUM(eliminado_en IS NULL AND publicado = 1 AND acceso = 'a_revisar') a_revisar,
                SUM(eliminado_en IS NULL AND publicado = 0) no_publicados
            FROM registro WHERE caja_id = ?");
        $st->execute([$id]);
        return array_map('intval', $st->fetch() ?: []);
    }

    /**
     * Publicar o despublicar una caja en el sitio (decisión del PM: se publica caja por caja). Pasa por Historial
     * (se deshace desde el historial de la caja) y no bloquea ningún campo: la importación no toca “publicada”.
     */
    public static function caja_publicar(): void
    {
        App::exigir('publicar');
        App::verificarPost();
        $c = self::cargarCaja(self::int('id'));
        $nuevo = empty($_POST['publicar']) ? 0 : 1;
        if ($c['eliminado_en']) {
            App::aviso('error', 'La caja está dada de baja: restaurala antes de publicarla.');
        } elseif ((int)$c['publicada'] !== $nuevo) {
            self::historial()->actualizar('caja', (int)$c['id'], ['publicada' => $nuevo], false);
            App::aviso('ok', $nuevo
                ? "Caja {$c['numero']} publicada: ya se ve en el sitio. Se puede deshacer desde el historial."
                : "Caja {$c['numero']} despublicada: ni ella ni sus registros se ven en el sitio. Se puede deshacer desde el historial.");
        }
        App::redirigir('caja', ['id' => $c['id']]);
    }

    /**
     * Sugiere grupos de cajas con nombres parecidos y permite unificarlos. Cada cambio pasa por el historial
     * (se puede deshacer y bloquea el campo para el Excel). Clases de grupo (docs/backoffice.md §10):
     *  - grafia:     mismo nombre salvo tildes, mayúsculas, comillas o un error de tipeo → unificar.
     *  - parte:      misma obra en varias cajas numeradas (“Cenizas del Paraíso” / “… II”) → no unificar;
     *                a lo sumo unificar el título conservando el numeral de cada caja.
     *  - aclaracion: mismo título con distinta aclaración (“(España)” / “(Argentina)”) → versiones distintas;
     *                a lo sumo unificar el título conservando la aclaración de cada caja.
     *  - resumen:    nombres distintos que el resumen de una caja presenta juntos (“Cajas 8 y 12”) → revisar.
     */
    public static function cajas_unificar(): void
    {
        App::exigir('correccion');   // renombra varias cajas a la vez: mismos perfiles que la corrección masiva
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
                        $nuevos[] = "{$c['numero']} “{$nuevo}”";
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
     * Separa un nombre de caja en título, parte final (numeral: “II”, “2”, “91/92/93”) y aclaración final entre paréntesis.
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
        // Aclaración escrita sin paréntesis: “Eramos tan jóvenes España” frente a “Éramos tan jóvenes (España)”
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

        // Cajas que un resumen presenta juntas: “Cajas 8 y 12”, “3 CAJAS, las: 17, 29 y 32”
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
                                $vinculos["$a-$b"][] = 'El resumen de la caja ' . $c['numero'] . ' dice “' . trim(mb_substr($m[0], 0, 60)) . '”';
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
                // Solo si un nombre contiene al otro (“Azucena” / “Azucena Villaflor”); si no, que lo escriba el operador
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
            'origen' => Origenes::desdeConfig(aida_config()),
            'bloqueados' => $bloq,
            'bloqueadoPor' => $quien,
            'alertas' => self::alertasAbiertas((int)$c['numero'], (int)$r['numero']),
            'historial' => self::historialDe('registro', (int)$r['id']),
            'anios' => (new Anios(self::db()))->leer((int)$r['id']),
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
        // Publicar o despublicar una ficha suelta es decisión editorial, como publicar una caja
        $nuevos['publicado'] = App::puede('publicar') ? (empty($_POST['publicado']) ? 0 : 1) : (int)$r['publicado'];
        if (App::puede('acceso') && isset($_POST['acceso']) && in_array($_POST['acceso'], ['publico', 'restringido', 'a_revisar'], true)) {
            $nuevos['acceso'] = $_POST['acceso'];
        }
        $cambios = self::soloCambios($r, $nuevos);
        if (array_key_exists('anio_texto', $cambios)) {
            // Mismas reglas que la importación: año, rango (“1981/82”) o con duda (“1972?”)
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
        if (($cambios['acceso'] ?? null) === 'publico' && Texto::marcaPrivada($r['excel_privado']) && empty($_POST['confirmar_publico'])) {
            App::aviso('error', 'La planilla marca este registro en la columna PRIVADO (“' . $r['excel_privado'] . '”). Para publicarlo igual, tildá la confirmación junto al acceso.');
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
                    // Las altas de carga de datos nacen sin publicar: las publica un editor
                    'publicado' => App::puede('publicar') ? 1 : 0,
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
            'texto' => "Vas a dar de baja {$r['codigo']}" . ($r['titulo'] ? " · “{$r['titulo']}”" : '') . " (Caja {$cj['numero']} · {$cj['nombre']})"
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
        if (!in_array($campo, $bloq, true)) {
            App::aviso('error', 'Ese campo no está bloqueado.');
            App::redirigir($entidad, ['id' => $fila['id']]);
        }
        if ($campo === 'acceso') {
            // Que el acceso vuelva a tomarse del Excel puede hacerlo público: es decisión editorial
            App::exigir('acceso');
        }
        $nuevo = array_values(array_diff($bloq, [$campo]));
        self::historial()->actualizar($entidad, (int)$fila['id'], ['campos_bloqueados' => $nuevo ? json_encode($nuevo) : null], false);
        App::aviso('ok', "“{$campo}” se va a volver a actualizar desde el Excel en la próxima importación.");
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
        $pt = self::db()->prepare('SELECT tipo FROM proceso WHERE id = ?');
        $pt->execute([(int)$a['proceso_id']]);
        $a['proceso_tipo'] = $a['proceso_id'] !== null ? $pt->fetchColumn() : null;
        $a['cambios'] = json_decode((string)$a['cambios'], true) ?: [];
        if ($motivo = self::motivoSinDeshacer($a)) {
            http_response_code(403);
            App::render('error', ['titulo' => 'No se puede deshacer', 'mensaje' => $motivo]);
            exit;
        }
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

    /**
     * Reglas del servidor para deshacer un cambio, además de deshacer_propio / deshacer_todo: deshacer no puede hacer
     * lo que el perfil no podría hacer directamente. Devuelve el motivo si no se puede, o null.
     *
     * @param array{entidad:string,accion:string,cambios:array,proceso_tipo:?string,entidad_id:int|string} $a
     */
    public static function motivoSinDeshacer(array $a): ?string
    {
        $entidad = $a['entidad'];
        $campos = array_keys($a['cambios']);
        $sin = 'Tu perfil no puede hacer este cambio, así que tampoco puede deshacerlo.';
        if (($a['proceso_tipo'] ?? null) === 'curaduria') {
            return 'Un cambio de curaduría se deshace completo, para todos sus registros, desde Vocabularios → Cambios de curaduría.';
        }
        if ($entidad === 'usuario') {
            return 'Los cambios de usuarios se corrigen desde Usuarios.';
        }
        if ($entidad === 'consulta') {
            if ($a['accion'] === 'alta') {
                return 'Un pedido recibido desde el sitio no se deshace: se descarta desde Consultas.';
            }
            return App::puede('consultas') ? null : $sin;
        }
        if (in_array($entidad, ['vocabulario', 'vocabulario_alias', 'periodo'], true) && !App::puede('vocabularios')) {
            return $sin;
        }
        // Altas, bajas y restauraciones (deshacer un alta da de baja; deshacer una baja restaura)
        if ((in_array($a['accion'], ['alta', 'baja', 'restauracion'], true) || in_array('eliminado_en', $campos, true)
             || in_array('origen', $campos, true)) && !App::puede('baja')) {
            return $sin;
        }
        if (array_intersect($campos, ['publicado', 'publicada']) && !App::puede('publicar')) {
            return $sin;
        }
        if (array_intersect($campos, ['numero', 'codigo', 'caja_id']) && !App::puede('nomenclatura')) {
            return $sin;
        }
        $tocaAcceso = in_array('acceso', $campos, true);
        if (in_array('campos_bloqueados', $campos, true)) {
            [$antes, $despues] = $a['cambios']['campos_bloqueados'];
            $lista = fn($v) => is_array($v) ? $v : (is_string($v) && $v !== '' ? (json_decode($v, true) ?: []) : []);
            $tocaAcceso = $tocaAcceso || in_array('acceso', $lista($antes), true) !== in_array('acceso', $lista($despues), true);
        }
        if ($tocaAcceso) {
            if (!App::puede('acceso')) {
                return $sin;
            }
            // Volver a público algo que la planilla marca PRIVADO pide la confirmación de la ficha
            if ($entidad === 'registro' && in_array('acceso', $campos, true) && ($a['cambios']['acceso'][0] ?? null) === 'publico') {
                $st = self::db()->prepare('SELECT excel_privado FROM registro WHERE id = ?');
                $st->execute([(int)$a['entidad_id']]);
                if (Texto::marcaPrivada($st->fetchColumn() ?: null)) {
                    return 'Este cambio vuelve público un registro que la planilla marca en PRIVADO. Cambialo desde la ficha, '
                         . 'que pide la confirmación.';
                }
            }
        }
        return null;
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
        $rows = self::db()->query("SELECT p.*, u.nombre usuario_nombre,
              (SELECT COUNT(*) FROM auditoria a WHERE a.proceso_id = p.id AND a.accion <> 'reversion') cambios,
              (SELECT COUNT(*) FROM auditoria a WHERE a.proceso_id = p.id AND a.accion <> 'reversion' AND a.revertido_en IS NOT NULL) deshechos,
              (SELECT COUNT(*) FROM incidencia i WHERE i.proceso_id = p.id AND i.resuelta = 0 AND i.requiere_validacion = 1) pendientes
            FROM proceso p LEFT JOIN usuario u ON u.id = p.usuario_id
            WHERE p.simulacion = 0 AND p.tipo <> 'curaduria' ORDER BY p.id DESC LIMIT 200")->fetchAll();
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

    // ------------------------------------------------------------------
    // Corrección masiva por planilla (src/Correccion.php)
    // ------------------------------------------------------------------

    private const CORRECCION_MAX_MB = 5;

    /**
     * Subir la planilla de correcciones (CSV o .xlsx) y ver la vista previa. Nada se guarda hasta “Aplicar”.
     */
    public static function correccion(): void
    {
        App::exigir('correccion');
        if (CargaExcel::postDemasiadoGrande()) {
            App::aviso('error', 'El archivo supera el máximo permitido (' . self::CORRECCION_MAX_MB . ' MB).');
            App::redirigir('correccion');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            App::verificarPost();
            $f = $_FILES['planilla'] ?? null;
            $ext = strtolower(pathinfo((string)($f['name'] ?? ''), PATHINFO_EXTENSION));
            $err = null;
            if (!$f || !isset($f['error']) || is_array($f['error']) || $f['error'] === UPLOAD_ERR_NO_FILE) {
                $err = 'Elegí la planilla de correcciones (.csv o .xlsx).';
            } elseif ($f['error'] !== UPLOAD_ERR_OK) {
                $err = in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                    ? 'El archivo supera el máximo permitido (' . self::CORRECCION_MAX_MB . ' MB).' : 'No se pudo recibir el archivo. Probá de nuevo.';
            } elseif (!in_array($ext, ['csv', 'xlsx'], true)) {
                $err = 'Tiene que ser un .csv o un .xlsx. Desde Google Sheets: Archivo → Descargar → CSV o Microsoft Excel.';
            } elseif (!is_uploaded_file((string)$f['tmp_name']) || (int)filesize((string)$f['tmp_name']) === 0) {
                $err = 'El archivo está vacío o no llegó bien.';
            } elseif ((int)filesize((string)$f['tmp_name']) > self::CORRECCION_MAX_MB * 1048576) {
                $err = 'El archivo supera el máximo permitido (' . self::CORRECCION_MAX_MB . ' MB).';
            } elseif ($ext === 'xlsx') {
                $err = CargaExcel::validarZip((string)$f['tmp_name']);
            }
            if ($err) {
                App::aviso('error', $err);
                App::redirigir('correccion');
            }
            $dir = CargaExcel::directorio();
            self::descartarCorreccion();
            $nombre = 'corr-' . bin2hex(random_bytes(12)) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'], "$dir/$nombre")) {
                App::aviso('error', 'No se pudo guardar el archivo en el servidor. Avisá al equipo técnico.');
                App::redirigir('correccion');
            }
            @chmod("$dir/$nombre", 0640);
            try {
                $filas = Correccion::leerArchivo("$dir/$nombre", $ext);
                $plan = (new Correccion(self::db(), App::usuarioId()))->plan($filas);
            } catch (\RuntimeException $e) {
                @unlink("$dir/$nombre");
                App::aviso('error', $e->getMessage());
                App::redirigir('correccion');
            } catch (\Throwable $e) {
                @unlink("$dir/$nombre");
                error_log('[aida-bo] corrección: ' . $e);
                App::aviso('error', 'No se pudo leer el archivo. Revisá que sea un CSV o un Excel con las columnas registro, columna y valor_nuevo.');
                App::redirigir('correccion');
            }
            unset($_SESSION['correccion_hecha']);
            $_SESSION['correccion'] = [
                'token' => bin2hex(random_bytes(16)), 'archivo' => $nombre, 'ext' => $ext,
                'original' => CargaExcel::nombreOriginal((string)$f['name']), 'sha1' => sha1_file("$dir/$nombre"),
                'firma' => $plan['firma'], 'en' => time(),
            ];
            App::redirigir('correccion');
        }
        $s = self::correccionVigente();
        $plan = null;
        if ($s) {
            // La vista previa se recalcula al mostrarla: refleja la base de ahora
            $plan = (new Correccion(self::db(), App::usuarioId()))->plan(Correccion::leerArchivo(CargaExcel::directorio() . '/' . $s['archivo'], $s['ext']));
            $_SESSION['correccion']['firma'] = $plan['firma'];
        }
        App::render('correccion', ['sim' => $s, 'plan' => $plan, 'hecha' => $s ? null : ($_SESSION['correccion_hecha'] ?? null),
                                   'maxMb' => self::CORRECCION_MAX_MB]);
    }

    public static function correccion_aplicar(): void
    {
        App::exigir('correccion');
        App::verificarPost();
        $s = self::correccionVigente();
        if (!$s || !hash_equals($s['token'], (string)($_POST['vista'] ?? ''))) {
            App::aviso('error', 'La vista previa venció o ya se usó. Subí la planilla de nuevo.');
            App::redirigir('correccion');
        }
        $dir = CargaExcel::directorio();
        try {
            $filas = Correccion::leerArchivo("$dir/{$s['archivo']}", $s['ext']);
            // Pasar a público lo que la planilla marca PRIVADO exige la casilla aparte (se valida también acá)
            $r = (new Correccion(self::db(), App::usuarioId()))->aplicar($filas, $s['original'], $s['firma'],
                !empty($_POST['confirmar_privados']));
        } catch (\RuntimeException $e) {
            App::aviso('error', $e->getMessage());
            App::redirigir('correccion');
        } catch (\Throwable $e) {
            error_log('[aida-bo] corrección: ' . $e);
            App::aviso('error', 'La corrección falló y no se guardó nada. Ya quedó registrado; avisá al equipo técnico.');
            App::redirigir('correccion');
        }
        // Se conserva la planilla aplicada, con el número de proceso
        @rename("$dir/{$s['archivo']}", sprintf('%s/correccion-%d-%s.%s', $dir, $r['proceso'], date('Ymd-His'), $s['ext']));
        unset($_SESSION['correccion']);
        $_SESSION['correccion_hecha'] = $r + ['original' => $s['original']];
        App::aviso('ok', "Corrección #{$r['proceso']}: se aplicaron {$r['aplicadas']} cambios. Se puede deshacer completa desde Importaciones.");
        if ($r['omitidas_privado']) {
            App::aviso('error', ($r['omitidas_privado'] === 1 ? 'No se aplicó 1 fila que pasaba a público un registro'
                : "No se aplicaron {$r['omitidas_privado']} filas que pasaban a público registros")
                . ' que la planilla marca PRIVADO: faltó tildar la confirmación. Si corresponde, subí la planilla de nuevo y confirmalo.');
        }
        App::redirigir('correccion');
    }

    public static function correccion_descartar(): void
    {
        App::exigir('correccion');
        App::verificarPost();
        self::descartarCorreccion();
        unset($_SESSION['correccion_hecha']);
        App::aviso('info', 'Vista previa descartada. No se cambió nada.');
        App::redirigir('correccion');
    }

    private static function correccionVigente(): ?array
    {
        $s = $_SESSION['correccion'] ?? null;
        if (!$s) {
            return null;
        }
        $ruta = CargaExcel::directorio() . '/' . basename((string)$s['archivo']);
        if (time() - (int)$s['en'] > CargaExcel::VIGENCIA || !is_file($ruta) || sha1_file($ruta) !== $s['sha1']) {
            self::descartarCorreccion();
            return null;
        }
        return $s;
    }

    private static function descartarCorreccion(): void
    {
        $s = $_SESSION['correccion'] ?? null;
        if ($s && preg_match('/^corr-[0-9a-f]{24}\.(csv|xlsx)$/', (string)$s['archivo'])) {
            @unlink(CargaExcel::directorio() . '/' . $s['archivo']);
        }
        unset($_SESSION['correccion']);
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
            'titulo' => ($proc['tipo'] === 'correccion' ? 'Deshacer la corrección' : ($proc['tipo'] === 'escaneo' ? 'Deshacer el escaneo' : 'Deshacer la importación')) . " #{$id}",
            'texto' => "Se van a deshacer " . ($det ? implode(', ', $det) : 'sus cambios') . " (del {$proc['fuente']}, " . fecha($proc['iniciado_en']) . '). '
                     . ($proc['tipo'] === 'correccion'
                        ? 'Cada celda corregida vuelve a su valor anterior y, si no estaba editada a mano, vuelve a tomarse del Excel. '
                        : 'Las altas quedan dadas de baja y las modificaciones vuelven al valor anterior. ')
                     . 'Si algún dato se modificó después, no se aplica nada y se muestran los conflictos.',
            'migas' => [['Importaciones', App::url('procesos')]],
            'accion' => App::url('proceso_deshacer', ['id' => $id]),
            'boton' => $proc['tipo'] === 'correccion' ? 'Deshacer la corrección completa' : 'Deshacer la importación',
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
              (SELECT GROUP_CONCAT(a.nombre ORDER BY a.nombre SEPARATOR \'”, “\') FROM vocabulario_alias a
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
                            throw new \RuntimeException("“{$n}” ya se reconoce como “{$r['final']['nombre']}” ("
                                . Vocabulario::NOMBRES[$r['final']['tipo']] . ($r['final']['estado'] === 'descartado' ? ', descartado' : '') . ').');
                        }
                    }
                    $h->crear('vocabulario', ['tipo' => $tipo, 'nombre' => $n, 'clave' => Texto::clave($n), 'estado' => 'aprobado']);
                    App::aviso('ok', "Término “{$n}” agregado.");
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
                    App::aviso('ok', "“{$t['nombre']}” descartado y quitado de " . ($r['registros'] === 1 ? '1 registro' : "{$r['registros']} registros")
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
                    App::aviso('ok', ($op === 'mover' ? "“{$t['nombre']}” se movió a " . Vocabulario::PLURALES[$tipoDest] . " como “{$r['destino']['nombre']}”"
                                                      : "“{$t['nombre']}” se fusionó en “{$r['destino']['nombre']}”")
                        . ': ' . ($r['registros'] === 1 ? '1 registro' : "{$r['registros']} registros")
                        . (!empty($r['aprobado']) ? ". “{$r['destino']['nombre']}” estaba propuesto y quedó aprobado" : '')
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
    // Vista previa del sitio público
    // ------------------------------------------------------------------

    /**
     * “Ver como en el sitio”: ?r=vista_previa&registro=ID o &caja=ID. Renderiza las mismas plantillas del sitio
     * público (vistas/publico/ficha.php y caja.php) con un banner arriba y, si hoy no aparece en el sitio,
     * los motivos. Solo lectura; no cuenta en evento_uso (el visor usa archivo.php con la sesión del backoffice).
     */
    // ------------------------------------------------------------------
    // Consultas a pedido del sitio público (src/Consultas.php)
    // ------------------------------------------------------------------

    public static function consultas(): void
    {
        App::exigir('consultas');
        $estado = isset(Consultas::ESTADOS[$_GET['estado'] ?? '']) ? (string)$_GET['estado'] : null;
        $q = trim((string)($_GET['q'] ?? ''));
        $c = new Consultas(self::db());
        App::render('consultas', ['consultas' => $c->lista($estado, $q), 'conteos' => $c->conteos(), 'estado' => $estado, 'q' => $q]);
    }

    public static function consulta(): void
    {
        App::exigir('consultas');
        $c = (new Consultas(self::db()))->buscar(self::int('id'));
        if (!$c || $c['eliminado_en']) {
            self::noEncontrado('consulta');
        }
        App::render('consulta', ['c' => $c, 'historial' => self::historialDe('consulta', (int)$c['id'])]);
    }

    public static function consulta_guardar(): void
    {
        App::exigir('consultas');
        App::verificarPost();
        $id = self::int('id');
        $estado = (string)($_POST['estado'] ?? '');
        if (!isset(Consultas::ESTADOS[$estado])) {
            App::aviso('error', 'Elegí un estado.');
            App::redirigir('consulta', ['id' => $id]);
        }
        $cambio = (new Consultas(self::db()))->actualizar($id, $estado, (string)($_POST['notas'] ?? ''), (int)App::usuarioId());
        App::aviso('ok', $cambio ? 'Consulta guardada.' : 'No había cambios para guardar.');
        App::redirigir('consulta', ['id' => $id]);
    }

    public static function vista_previa(): void
    {
        App::exigir('ver');
        $sitio = new Sitio(self::db(), true);
        if (isset($_GET['registro'])) {
            $d = $sitio->fichaPorId(self::int('registro')) ?? self::noEncontrado('registro');
            $vista = 'ficha';
            $volver = App::url('registro', ['id' => (int)$d['registro']['id']]);
            $que = 'Este registro';
        } elseif (isset($_GET['caja'])) {
            $d = $sitio->cajaPorId(self::int('caja')) ?? self::noEncontrado('caja');
            $vista = 'caja';
            $volver = App::url('caja', ['id' => (int)$d['caja']['id']]);
            $que = 'Esta caja';
        } else {
            self::noEncontrado('documento');
        }
        $enl = Enlaces::vistaPrevia();
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: private, no-store');
        $datos = [
            'd' => $d,
            'enl' => $enl,
            'robots' => 'noindex,nofollow',
            'cabezaExtra' => '<link rel="stylesheet" href="' . e($enl->recurso('sitio/vista-previa.css')) . '">' . "\n"
                . '<script src="' . e($enl->recurso('sitio/vista-previa.js')) . '" defer></script>',
        ];
        $datos['antes'] = self::parcial('publico/_vista_previa', [
            'motivos' => $d['motivos'], 'volver' => $volver, 'que' => $que,
            'ocultos' => $vista === 'caja' ? $d['ocultos'] : [],
        ]);
        self::parcialPublico($vista, $datos);
    }

    /** Renderiza una vista a texto. @param array<string,mixed> $datos */
    private static function parcial(string $vista, array $datos): string
    {
        extract($datos, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__, 2) . "/vistas/$vista.php";
        return (string)ob_get_clean();
    }

    /** Página completa del sitio público (vistas/publico/), sin el layout del backoffice. */
    private static function parcialPublico(string $vista, array $datos): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo self::parcial("publico/$vista", $datos);
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

    // ------------------------------------------------------------------
    // Almacenamiento: origen de los archivos, listado de Drive e informe de espacio
    // ------------------------------------------------------------------

    private const LISTADO_MAX_MB = 5;

    /**
     * Informe de espacio (para dimensionar el hosting) y carga del listado de Drive. Subir el listado lo simula;
     * se guarda recién con “Cargar el listado” (drive_listado_aplicar), sobre el mismo archivo.
     */
    public static function almacenamiento(): void
    {
        App::exigir('ver');
        $cfg = aida_config();
        $origen = Origenes::desdeConfig($cfg);
        $drive = Origenes::drive($cfg);
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            App::exigir('procesos');
            App::verificarPost();
            $f = $_FILES['listado'] ?? null;
            $err = match (true) {
                !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE => 'Elegí el archivo del listado.',
                ($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($f['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE
                    || (int)($f['size'] ?? 0) > self::LISTADO_MAX_MB * 1048576 => 'El listado supera los ' . self::LISTADO_MAX_MB . ' MB.',
                ($f['error'] ?? 0) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name']) => 'No se pudo subir el archivo. Probá de nuevo.',
                !preg_match('/\.(tsv|csv|txt)$/i', (string)$f['name']) => 'El listado tiene que ser .tsv, .csv o .txt (texto separado por tabulaciones, comas o punto y coma).',
                default => null,
            };
            if ($err) {
                App::aviso('error', $err);
                App::redirigir('almacenamiento');
            }
            $dir = CargaExcel::directorio();
            self::descartarListado();
            $nombre = 'sim-drive-' . bin2hex(random_bytes(12)) . '.tsv';
            if (!move_uploaded_file($f['tmp_name'], "$dir/$nombre")) {
                App::aviso('error', 'No se pudo guardar el archivo en el servidor. Avisá al equipo técnico.');
                App::redirigir('almacenamiento');
            }
            @chmod("$dir/$nombre", 0640);
            $original = CargaExcel::nombreOriginal((string)$f['name']);
            try {
                $rep = (new Escaner(self::db()))->escanear($drive->conListado("$dir/$nombre"), true);
            } catch (\RuntimeException $e) {
                @unlink("$dir/$nombre");
                App::aviso('error', 'No se pudo leer el listado: ' . $e->getMessage());
                App::redirigir('almacenamiento');
            }
            $_SESSION['listado_drive'] = CargaExcel::resumen($rep) + [
                'token' => bin2hex(random_bytes(16)), 'archivo' => $nombre, 'original' => $original,
                'sha1' => sha1_file("$dir/$nombre"), 'bytes' => (int)filesize("$dir/$nombre"), 'en' => time(),
            ];
            unset($_SESSION['listado_drive_hecho']);
            App::redirigir('almacenamiento');
        }
        $sim = self::listadoVigente();
        App::render('almacenamiento', [
            'origen' => $origen,
            'drive' => $drive,
            'informe' => (new Espacio(self::db(), Origenes::cajasTotal($cfg), $drive->visorMaxBytes()))->informe(),
            'sim' => $sim,
            'hecho' => $sim ? null : ($_SESSION['listado_drive_hecho'] ?? null),
            'maxMb' => self::LISTADO_MAX_MB,
            'conIds' => (int)self::db()->query('SELECT COUNT(*) FROM archivo_digital WHERE drive_id IS NOT NULL AND eliminado_en IS NULL AND presente = 1')->fetchColumn(),
        ]);
    }

    /** Carga de verdad el listado que se acaba de simular: escanea con él y lo deja como listado vigente. */
    public static function drive_listado_aplicar(): void
    {
        App::exigir('procesos');
        App::verificarPost();
        $sim = self::listadoVigente();
        if (!$sim || !hash_equals($sim['token'], (string)($_POST['simulacion'] ?? ''))) {
            App::aviso('error', 'La simulación venció o ya se usó. Subí el listado de nuevo.');
            App::redirigir('almacenamiento');
        }
        $cfg = aida_config();
        if (Origenes::desdeConfig($cfg)->nombre() !== Origenes::DRIVE) {
            App::aviso('error', 'El origen de los archivos configurado es “disco”: el listado de Drive se puede simular, pero cargarlo cambiaría '
                . 'qué archivos figuran presentes. Para cargarlo hay que pasar el origen a “drive” (docs/despliegue.md).');
            App::redirigir('almacenamiento');
        }
        $drive = Origenes::drive($cfg);
        $ruta = CargaExcel::directorio() . '/' . $sim['archivo'];
        try {
            $rep = (new Escaner(self::db()))->escanear($drive->conListado($ruta), false);
            $drive->guardarListado($ruta, $sim['original']);
        } catch (\Throwable $e) {
            error_log('[aida-bo] listado de Drive: ' . $e);
            App::aviso('error', 'No se pudo cargar el listado. Ya quedó registrado; avisá al equipo técnico.');
            App::redirigir('almacenamiento');
        }
        @rename($ruta, sprintf('%s/drive-%d-%s.tsv', CargaExcel::directorio(), (int)$rep->procesoId, date('Ymd-His')));
        unset($_SESSION['listado_drive']);
        $_SESSION['listado_drive_hecho'] = CargaExcel::resumen($rep) + ['proceso' => $rep->procesoId, 'original' => $sim['original']];
        App::aviso('ok', "Listado de Drive cargado (escaneo #{$rep->procesoId}).");
        App::redirigir('almacenamiento');
    }

    public static function drive_listado_descartar(): void
    {
        App::exigir('procesos');
        App::verificarPost();
        self::descartarListado();
        unset($_SESSION['listado_drive_hecho']);
        App::aviso('info', 'Simulación descartada. No se cargó nada.');
        App::redirigir('almacenamiento');
    }

    /** Vuelve a escanear con el último listado de Drive (por ejemplo, después de cambiar accesos), para poner al día las alertas. */
    public static function drive_reescanear(): void
    {
        App::exigir('procesos');
        App::verificarPost();
        $origen = Origenes::desdeConfig(aida_config());
        if ($origen->nombre() !== Origenes::DRIVE) {
            App::aviso('error', 'El origen de los archivos configurado no es Drive.');
            App::redirigir('almacenamiento');
        }
        try {
            $rep = (new Escaner(self::db()))->escanear($origen, false);
        } catch (\RuntimeException $e) {
            App::aviso('error', $e->getMessage());
            App::redirigir('almacenamiento');
        }
        unset($_SESSION['listado_drive']);
        $_SESSION['listado_drive_hecho'] = CargaExcel::resumen($rep) + ['proceso' => $rep->procesoId, 'original' => 'último listado cargado'];
        App::aviso('ok', "Controles de Drive al día (escaneo #{$rep->procesoId}).");
        App::redirigir('almacenamiento');
    }

    private static function listadoVigente(): ?array
    {
        $s = $_SESSION['listado_drive'] ?? null;
        if (!$s) {
            return null;
        }
        $ruta = CargaExcel::directorio() . '/' . basename((string)$s['archivo']);
        if (time() - (int)$s['en'] > CargaExcel::VIGENCIA || !is_file($ruta) || sha1_file($ruta) !== $s['sha1']) {
            self::descartarListado();
            return null;
        }
        return $s;
    }

    private static function descartarListado(): void
    {
        $s = $_SESSION['listado_drive'] ?? null;
        if ($s && preg_match('/^sim-drive-[0-9a-f]{24}\.tsv$/', (string)$s['archivo'])) {
            @unlink(CargaExcel::directorio() . '/' . $s['archivo']);
        }
        unset($_SESSION['listado_drive']);
    }

    // ------------------------------------------------------------------
    // Usuarios (solo administradores) y acceso
    // ------------------------------------------------------------------

    private static function usuarios_(): Usuarios
    {
        return new Usuarios(self::db(), App::usuarioId());
    }

    /** @return array{email:string,nombre:string,rol:string,activo:int} */
    private static function datosUsuario(): array
    {
        return [
            'email'  => Usuarios::normalizarEmail((string)($_POST['email'] ?? '')),
            'nombre' => trim((string)preg_replace('/\s+/u', ' ', (string)($_POST['nombre'] ?? ''))),
            'rol'    => (string)($_POST['rol'] ?? ''),
            'activo' => isset($_POST['activo']) ? 1 : 0,
        ];
    }

    public static function usuarios(): void
    {
        App::exigir('usuarios');
        App::render('usuarios', ['usuarios' => self::usuarios_()->listar(), 'correo' => Correo::configurado()]);
    }

    public static function usuario(): void
    {
        App::exigir('usuarios');
        $id = self::int('id');
        $u = $id ? self::usuarios_()->buscar($id) : null;
        if ($id && (!$u || $u['rol'] === 'visitante')) {
            App::aviso('error', 'Ese usuario no existe.');
            App::redirigir('usuarios');
        }
        $enlace = $_SESSION['enlace_generado'] ?? null;
        unset($_SESSION['enlace_generado']);
        if ($enlace && (int)$enlace['usuario'] !== $id) {
            $enlace = null;
        }
        App::render('usuario', ['u' => $u, 'form' => $u ?: ['email' => '', 'nombre' => '', 'rol' => 'editor', 'activo' => 1],
                                'errores' => [], 'enlace' => $enlace, 'correo' => Correo::configurado()]);
    }

    public static function usuario_guardar(): void
    {
        App::exigir('usuarios');
        App::verificarPost();
        $id = self::int('id') ?: null;
        $us = self::usuarios_();
        $d = self::datosUsuario();
        $err = $us->validar($d, $id);
        if ($err) {
            $u = $id ? $us->buscar($id) : null;
            App::render('usuario', ['u' => $u, 'form' => $d, 'errores' => $err, 'enlace' => null, 'correo' => Correo::configurado()]);
            return;
        }
        if ($id) {
            $us->actualizar($id, $d);
            App::aviso('ok', 'Cambios guardados.');
            App::redirigir('usuario', ['id' => $id]);
        }
        $id = $us->crear($d);
        $msj = self::mandarEnlace($us, $id, 'alta');
        App::aviso('ok', 'Usuario creado. ' . $msj);
        App::redirigir('usuario', ['id' => $id]);
    }

    /** Genera un enlace de alta o restablecimiento para otro usuario (o para uno mismo). */
    public static function usuario_enlace(): void
    {
        App::exigir('usuarios');
        App::verificarPost();
        $id = self::int('id');
        $us = self::usuarios_();
        $u = $us->buscar($id);
        if (!$u || !(int)$u['activo'] || $u['rol'] === 'visitante') {
            App::aviso('error', 'Solo se puede mandar el enlace a un usuario activo.');
            App::redirigir('usuarios');
        }
        $tipo = $u['ultimo_ingreso'] ? 'restablecer' : 'alta';
        App::aviso('ok', self::mandarEnlace($us, $id, $tipo));
        App::redirigir('usuario', ['id' => $id]);
    }

    /** Crea el enlace, intenta mandarlo por correo y deja una copia para mostrarle una sola vez al administrador. */
    private static function mandarEnlace(Usuarios $us, int $id, string $tipo): string
    {
        $u = $us->buscar($id);
        $url = self::urlAcceso($us->crearEnlace($id, $tipo), true);
        $enviado = Correo::enviar((string)$u['email'],
            $tipo === 'alta' ? 'Tu acceso al backoffice · Las cajas de Aída' : 'Restablecer tu contraseña · Las cajas de Aída',
            Usuarios::textoCorreo($tipo, $url, $u['nombre']));
        $_SESSION['enlace_generado'] = ['usuario' => $id, 'url' => $url, 'tipo' => $tipo, 'enviado' => $enviado];
        $horas = Usuarios::VIGENCIA[$tipo];
        return $enviado
            ? "Le mandamos el enlace a {$u['email']} (vale $horas horas)."
            : "No se pudo mandar el correo: copiá el enlace de abajo y pasáselo por otro medio (vale $horas horas).";
    }

    /**
     * URL absoluta del enlace de acceso. Con config 'backoffice' => ['url' => ...] se usa esa (recomendado: evita que
     * un encabezado Host falso cambie el dominio del enlace). Sin ella, solo para un administrador logueado, se arma
     * con el host del pedido.
     */
    private static function urlAcceso(string $valor, bool $permitirHost): ?string
    {
        $base = rtrim((string)(aida_config()['backoffice']['url'] ?? ''), '/');
        if ($base === '') {
            if (!$permitirHost) {
                return null;
            }
            $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
            $base = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
        }
        return $base . '/?r=restablecer&t=' . $valor;
    }

    /** «Olvidé mi contraseña». Siempre responde lo mismo, exista o no el correo. */
    public static function olvide(): void
    {
        if (App::usuario()) {
            App::redirigir('cuenta');
        }
        $enviado = false;
        $error = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            App::verificarPost();
            $email = Usuarios::normalizarEmail((string)($_POST['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Escribí un correo válido.';
            } elseif (!Turnstile::verificar('olvide')) {
                $error = 'No pudimos verificar que no seas un robot. Esperá a que aparezca el tilde y volvé a probar.';
            } elseif ((new Intentos(self::db()))->excedeIp('olvide_ip', 5, 60)) {
                $error = 'Se pidieron demasiados enlaces desde esta conexión. Probá de nuevo en una hora.';
            } else {
                $us = new Usuarios(self::db());
                $u = $us->porEmail($email);
                if ($u && (int)$u['activo'] && $u['rol'] !== 'visitante' && !$us->enlaceReciente((int)$u['id'])) {
                    $valor = $us->crearEnlace((int)$u['id'], 'restablecer');
                    $url = self::urlAcceso($valor, false);
                    if (!$url || !Correo::enviar($u['email'], 'Restablecer tu contraseña · Las cajas de Aída', Usuarios::textoCorreo('restablecer', $url, $u['nombre']))) {
                        $us->anularEnlaces((int)$u['id']);
                        error_log('[aida-bo] olvide: no se pudo mandar el correo (revisar config correo / backoffice.url)');
                    }
                }
                usleep(random_int(200000, 500000));
                $enviado = true;
            }
        }
        App::render('acceso', ['modo' => 'olvide', 'enviado' => $enviado, 'error' => $error]);
    }

    /** Crear o restablecer la contraseña desde un enlace. */
    public static function restablecer(): void
    {
        $valor = (string)($_REQUEST['t'] ?? '');
        $us = new Usuarios(self::db());
        $e = $us->enlaceVigente($valor);
        if (!$e) {
            App::render('acceso', ['modo' => 'vencido']);
            return;
        }
        $error = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            App::verificarPost();
            $clave = (string)($_POST['clave'] ?? '');
            $error = Usuarios::errorClave($clave, (string)($_POST['clave2'] ?? ''), (string)$e['usuario']['email']);
            if (!$error) {
                if (!$us->usarEnlace($valor, $clave)) {
                    App::render('acceso', ['modo' => 'vencido']);
                    return;
                }
                $_SESSION = [];
                session_regenerate_id(true);
                App::aviso('ok', 'Listo: ya podés ingresar con tu nueva contraseña.');
                App::redirigir('login');
            }
        }
        App::render('acceso', ['modo' => $e['tipo'], 'valor' => $valor, 'email' => $e['usuario']['email'], 'error' => $error]);
    }

    /** Mi cuenta: cambiar la propia contraseña. */
    public static function cuenta(): void
    {
        App::exigir('ver');
        $error = null;
        $us = self::usuarios_();
        $u = $us->buscar((int)App::usuarioId());
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            App::verificarPost();
            $intentos = new Intentos(self::db());
            if ($intentos->loginBloqueado((string)$u['email'])) {
                $error = 'Demasiados intentos fallidos. Esperá unos minutos antes de volver a probar.';
            } elseif (!password_verify((string)($_POST['actual'] ?? ''), (string)$u['password_hash'])) {
                $intentos->loginFallido((string)$u['email']);
                $error = 'La contraseña actual no es correcta.';
                usleep(400000);
            } else {
                $clave = (string)($_POST['clave'] ?? '');
                $error = Usuarios::errorClave($clave, (string)($_POST['clave2'] ?? ''), (string)$u['email']);
                if (!$error) {
                    $us->cambiarClave((int)$u['id'], $clave);
                    App::abrirSesionUsuario((array)$us->buscar((int)$u['id'])); // esta sesión sigue; las demás se cierran
                    App::aviso('ok', 'Contraseña cambiada. Las otras sesiones abiertas con tu usuario se cerraron.');
                    App::redirigir('cuenta');
                }
            }
        }
        App::render('cuenta', ['u' => $u, 'error' => $error]);
    }
}
