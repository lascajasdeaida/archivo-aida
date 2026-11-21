<?php
declare(strict_types=1);

namespace Aida;

/**
 * Resolución de términos de vocabulario: a qué término vigente corresponde lo que escribió el Excel
 * (o lo que guarda el historial) en una columna de Tipo o de Descriptor.
 *
 * Reglas (docs/decisiones.md, “Vocabularios”):
 *  1. Término con esa clave en esa columna. Si está fusionado o se movió al otro vocabulario
 *     (fusionado_en), se sigue la cadena hasta el término vigente, que puede ser del otro vocabulario.
 *  2. Si no, una grafía anterior de un término renombrado (vocabulario_alias).
 *  3. Si el término vigente está descartado, no se asigna.
 * Las claves se comparan con Texto::clave (sin tildes ni mayúsculas).
 */
final class Vocabulario
{
    public const NOMBRES = ['tipo_documento' => 'tipo de documento', 'descriptor' => 'descriptor'];
    public const PLURALES = ['tipo_documento' => 'tipos de documento', 'descriptor' => 'descriptores'];
    /** Campo de la lista en Historial para cada vocabulario. */
    public const CAMPO = ['tipo_documento' => 'tipos', 'descriptor' => 'descriptores'];

    public function __construct(private readonly \PDO $db)
    {
    }

    public static function otro(string $tipo): string
    {
        return $tipo === 'descriptor' ? 'tipo_documento' : 'descriptor';
    }

    /** @return array<string,mixed>|null */
    public function fila(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM vocabulario WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Sigue fusionado_en hasta el término vigente. @return array<string,mixed> */
    public function terminal(array $fila): array
    {
        $vistos = [];
        while ($fila['fusionado_en'] !== null) {
            $vistos[(int)$fila['id']] = true;
            $sig = $this->fila((int)$fila['fusionado_en']);
            if ($sig === null || isset($vistos[(int)$sig['id']])) {
                break; // destino inexistente o ciclo: se queda en el último válido
            }
            $fila = $sig;
        }
        return $fila;
    }

    /**
     * @return array{inicio:array, final:array, via:string, regla:?string}|null
     *   via: 'termino' (clave en esa columna), 'alias' (grafía anterior), 'eliminado' (término dado de baja con esa clave)
     *   regla: null (es el mismo término), 'fusion', 'movido' (terminó en el otro vocabulario), 'renombre'
     */
    public function resolver(string $tipo, string $nombre): ?array
    {
        $clave = Texto::clave($nombre);
        $st = $this->db->prepare('SELECT * FROM vocabulario WHERE tipo = ? AND clave = ?');
        $st->execute([$tipo, $clave]);
        $fila = $st->fetch() ?: null;
        $via = 'termino';
        if ($fila === null || $fila['eliminado_en'] !== null) {
            $a = $this->db->prepare('SELECT vocabulario_id FROM vocabulario_alias WHERE tipo = ? AND clave = ? AND eliminado_en IS NULL');
            $a->execute([$tipo, $clave]);
            $vid = $a->fetchColumn();
            if ($vid !== false && ($alias = $this->fila((int)$vid))) {
                $fila = $alias;
                $via = 'alias';
            } elseif ($fila !== null) {
                $via = 'eliminado';
            } else {
                return null;
            }
        }
        $final = $this->terminal($fila);
        $regla = null;
        if ($final['tipo'] !== $tipo) {
            $regla = 'movido';
        } elseif ($via === 'alias') {
            $regla = 'renombre';
        } elseif ((int)$final['id'] !== (int)$fila['id']) {
            $regla = 'fusion';
        }
        return ['inicio' => $fila, 'final' => $final, 'via' => $via, 'regla' => $regla];
    }

    /**
     * Id del término vigente para escribir en una lista del mismo vocabulario (historial, ficha).
     * null si ya no corresponde a ese vocabulario, está descartado o no existe.
     */
    public function idParaLista(string $tipo, string $nombre): ?int
    {
        $r = $this->resolver($tipo, $nombre);
        if ($r === null || $r['via'] === 'eliminado' || $r['final']['tipo'] !== $tipo || $r['final']['estado'] === 'descartado') {
            return null;
        }
        return (int)$r['final']['id'];
    }

    /** Explica por qué un nombre no se puede volver a asignar en una lista. */
    public function motivoNoAsignable(string $tipo, string $nombre): string
    {
        $r = $this->resolver($tipo, $nombre);
        if ($r === null || $r['via'] === 'eliminado') {
            return "“{$nombre}” ya no existe como " . self::NOMBRES[$tipo];
        }
        if ($r['final']['tipo'] !== $tipo) {
            return "“{$nombre}” se movió a " . self::PLURALES[$r['final']['tipo']] . " (“{$r['final']['nombre']}”)";
        }
        return "“{$r['final']['nombre']}” está descartado";
    }

    /** @return list<array{id:int,nombre:string}> alias vigentes de un término */
    public function alias(int $id): array
    {
        $st = $this->db->prepare('SELECT id, nombre FROM vocabulario_alias WHERE vocabulario_id = ? AND eliminado_en IS NULL ORDER BY nombre');
        $st->execute([$id]);
        return $st->fetchAll();
    }
}
