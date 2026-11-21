<?php
declare(strict_types=1);

namespace Aida\Web;

use Aida\Archivos\Origenes;

/**
 * Medición del archivo (GTM + GA4) en el sitio público. Detalle en docs/medicion.md.
 *
 * Reglas:
 *  - Solo se carga si config/config.php trae 'analitica' => ['gtm' => 'GTM-…']. Sin esa clave no se imprime nada
 *    (ni GTM, ni dataLayer, ni public/sitio/medicion.js): local y pruebas quedan limpios.
 *  - Solo la usa SitioPublico::pagina(). El backoffice y su vista previa renderizan las mismas plantillas sin $medicion,
 *    así que nunca disparan eventos.
 *  - Prueba / producción: lo decide el navegador por el host. Fuera de los hosts de producción se empuja
 *    entorno 'prueba', traffic_type 'internal' y debug_mode true (DebugView); en producción, entorno 'produccion'
 *    y sin debug_mode (GA4 toma cualquier debug_mode, aunque sea false, como depuración).
 *  - Quien mira con una sesión del backoffice abierta es tráfico interno también en producción.
 *  - Nada que identifique personas: códigos de registro, números de caja y nombres de vocabulario, nunca datos del
 *    formulario de consulta. Los textos libres (búsquedas) se limpian en el navegador (medicion.js).
 */
final class Medicion
{
    public const HOSTS_PRODUCCION = ['lascajasdeaida.com.ar', 'www.lascajasdeaida.com.ar', 'lascajasdeaida.com', 'www.lascajasdeaida.com'];

    /** Vista → sección del dataLayer. Lo que no está acá no se mide. */
    private const SECCIONES = [
        'inicio' => 'inicio', 'explorar' => 'explorar', 'cajas' => 'cajas', 'caja' => 'caja', 'ficha' => 'registro',
        'consulta' => 'consulta', 'consulta_enviada' => 'consulta', 'sobre' => 'sobre',
        'pagina_404' => 'error', 'no_disponible' => 'error', 'error' => 'error',
    ];

    /** @return array{gtm:string, hosts:list<string>}|null */
    public static function config(): ?array
    {
        $c = aida_config()['analitica'] ?? null;
        $gtm = is_array($c) ? strtoupper(trim((string)($c['gtm'] ?? ''))) : '';
        if (!preg_match('/^GTM-[A-Z0-9]{4,12}$/', $gtm)) {
            return null;
        }
        $hosts = array_values(array_filter(array_map(fn($h) => strtolower(trim((string)$h)),
            (array)($c['hosts_produccion'] ?? self::HOSTS_PRODUCCION))));
        return ['gtm' => $gtm, 'hosts' => $hosts ?: self::HOSTS_PRODUCCION];
    }

    /**
     * Contexto de la página para el dataLayer y la vista que la abre (ver_caja, ver_registro, enviar_consulta).
     * null = no se mide (sin configuración o vista sin sección).
     * @return array{gtm:string, hosts:list<string>, interno:bool, contexto:array<string,scalar>, vista:?array}|null
     */
    public static function pagina(string $vista, array $datos, bool $equipo): ?array
    {
        $cfg = self::config();
        $seccion = self::SECCIONES[$vista] ?? null;
        if ($cfg === null || $seccion === null) {
            return null;
        }
        $ctx = ['sitio' => 'lascajasdeaida.com.ar', 'tipo_contenido' => 'archivo', 'rama' => 'Archivo',
                'content_group' => 'Archivo', 'seccion' => $seccion];
        $evento = null;
        $d = $datos['d'] ?? null;
        if ($vista === 'ficha' && is_array($d)) {
            $ctx += self::datosRegistro($d);
            $evento = ['ver_registro', []];
        } elseif ($vista === 'caja' && is_array($d)) {
            $ctx += ['caja_numero' => (int)$d['caja']['numero']] + self::etapa((int)$d['caja']['id']);
            $evento = ['ver_caja', ['caja_numero' => (int)$d['caja']['numero'], 'documentos' => (int)($d['cifras']['documentos'] ?? 0)]];
        } elseif ($vista === 'consulta' && is_array($d)) {
            $ctx += self::datosRegistro($d) + ['tipo_consulta' => (string)($datos['tipo'] ?? '')];
        } elseif ($vista === 'consulta_enviada' && is_array($d)) {
            $ctx += self::datosRegistro($d);
            $tipo = ($d['documento'] ?? '') === 'pedido' ? 'a_pedido' : 'original';
            $evento = ['enviar_consulta', ['tipo_consulta' => $tipo]];
        } elseif ($vista === 'pagina_404' || $vista === 'no_disponible' || $vista === 'error') {
            $ctx['tipo_error'] = $vista === 'no_disponible' ? '410' : ($vista === 'error' ? '500' : '404');
        }
        return $cfg + ['interno' => $equipo, 'contexto' => $ctx, 'vista' => $evento];
    }

    /** Lo que se mide de un registro: código C2R5 (no el id interno), caja, tipos, acceso, etapa. Sin título ni autor. */
    private static function datosRegistro(array $d): array
    {
        $tipos = implode('|', array_map(fn($t) => (string)$t['nombre'], $d['tipos'] ?? []));
        return [
            'registro_codigo' => (string)$d['registro']['codigo'],
            'caja_numero' => (int)$d['caja']['numero'],
            'tipos_documento' => mb_substr($tipos, 0, 100),
            'acceso_documento' => match ($d['documento'] ?? '') { 'ver' => 'libre', 'pedido' => 'a_pedido', default => 'solo_ficha' },
            'digitalizado' => !empty($d['digital']) ? 'si' : 'no',
        ] + self::etapa((int)$d['caja']['id']);
    }

    /** Etapas (períodos) de la caja, si ya están asignadas. Hoy la tabla está vacía: no se manda nada. */
    private static function etapa(int $cajaId): array
    {
        try {
            $st = aida_db()->prepare('SELECT p.nombre FROM caja_periodo cp JOIN periodo p ON p.id = cp.periodo_id
                WHERE cp.caja_id = ? ORDER BY p.orden, p.anio_desde, p.nombre');
            $st->execute([$cajaId]);
            $n = implode('|', $st->fetchAll(\PDO::FETCH_COLUMN));
        } catch (\PDOException) {
            $n = '';
        }
        return $n !== '' ? ['etapa' => mb_substr($n, 0, 100)] : [];
    }

    /** <script> del <head>: contexto + entorno (antes de GTM) y el contenedor. '' si no se mide. */
    public static function cabeza(?array $m): string
    {
        if ($m === null) {
            return '';
        }
        $j = fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $vista = $m['vista'] ? ['evento' => $m['vista'][0], 'datos' => (object)$m['vista'][1]] : null;
        $gtm = $j($m['gtm']);
        return "<!-- Medición (docs/medicion.md): contexto antes de GTM -->\n"
            . '<script>window.dataLayer=window.dataLayer||[];(function(){'
            . 'var h=location.hostname.toLowerCase().replace(/\.$/,""),prod=' . $j($m['hosts']) . '.indexOf(h)!==-1,c=' . $j((object)$m['contexto']) . ';'
            . 'c.entorno=prod?"produccion":"prueba";if(!prod){c.traffic_type="internal";c.debug_mode=true;}'
            . ($m['interno'] ? 'c.traffic_type="internal";' : '')
            . 'dataLayer.push(c);window.aidaMedicion={entorno:c.entorno,pagina:c,vista:' . $j($vista) . '};})();</script>' . "\n"
            . "<!-- Google Tag Manager -->\n"
            . "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',$gtm);</script>\n"
            . "<!-- End Google Tag Manager -->\n";
    }

    public static function noscript(?array $m): string
    {
        if ($m === null) {
            return '';
        }
        return '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . rawurlencode($m['gtm'])
            . '" height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>' . "\n";
    }

    /**
     * Atributos para un botón de archivo de la ficha (ver_archivo o descargar_archivo): medicion.js los manda al hacer
     * clic, porque el archivo se entrega por redirección (Drive) y la página nunca ve la descarga. '' si no se mide.
     */
    public static function atributosArchivo(?array $m, string $evento, array $a, int $parte, string $ubicacion): string
    {
        if ($m === null) {
            return '';
        }
        $datos = [
            'registro_codigo' => $m['contexto']['registro_codigo'] ?? null,
            'caja_numero' => $m['contexto']['caja_numero'] ?? null,
            'archivo_parte' => $parte,
            'archivo_formato' => strtolower((string)($a['formato'] ?? '')),
            'origen_archivo' => self::origen(),
            'peso_mb' => isset($a['peso']) ? round($a['peso'] / 1048576, 1) : null,
            'ubicacion_enlace' => $ubicacion,
        ];
        return ' data-medicion="' . Html::e($evento) . '" data-medicion-datos="'
            . Html::e(json_encode(array_filter($datos, fn($v) => $v !== null && $v !== ''), JSON_UNESCAPED_UNICODE)) . '"';
    }

    /** 'drive' | 'disco' */
    public static function origen(): string
    {
        static $o = null;
        return $o ??= Origenes::desdeConfig(aida_config())->nombre();
    }
}
