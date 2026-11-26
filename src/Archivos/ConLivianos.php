<?php
declare(strict_types=1);

namespace Aida\Archivos;

use Aida\Reporte;

/**
 * Envuelve el origen de los originales (Drive o disco) y, si existe, entrega la versión liviana guardada en el servidor.
 *
 * - Versión liviana: `<raiz livianos>/<ruta>` (C1/C1R9.pdf), PDF a 150 dpi generado por bin/livianos.php.
 *   Se usa para el visor y para el botón «Descargar» de la ficha.
 * - Original: el pedido con `original=1` (botón «Descargar original») va al origen de siempre.
 * Si un archivo no tiene versión liviana, todo funciona como antes.
 *
 * config.php: 'archivos' => ['livianos' => ['raiz' => '/home/…/archivo-livianos']]
 */
final class ConLivianos implements Origen
{
    private Disco $livianos;

    public function __construct(private Origen $base, string $raiz, private int $visorMaxBytes)
    {
        $this->livianos = new Disco($raiz);
    }

    public function base(): Origen
    {
        return $this->base;
    }

    /** Ruta de la versión liviana, o null si no hay. */
    public function liviano(array $a): ?string
    {
        return $this->livianos->rutaSegura($a);
    }

    public static function pideOriginal(): bool
    {
        return !empty($_GET['original']);
    }

    public function nombre(): string
    {
        return $this->base->nombre();
    }

    public function descripcion(): string
    {
        return $this->base->descripcion() . ' (con versiones livianas)';
    }

    public function listar(): array
    {
        return $this->base->listar();
    }

    public function avisos(): array
    {
        return $this->base->avisos();
    }

    public function metadatos(array $fila): array
    {
        return $this->base->metadatos($fila);
    }

    public function controles(\PDO $db, Reporte $rep): void
    {
        $this->base->controles($db, $rep);
    }

    public function entregable(array $a): bool
    {
        return $this->liviano($a) !== null || $this->base->entregable($a);
    }

    public function admiteVisor(array $a): bool
    {
        $l = $this->liviano($a);
        if ($l !== null) {
            return (int)filesize($l) <= $this->visorMaxBytes;
        }
        return $this->base->admiteVisor($a);
    }

    public function entregar(array $a, bool $descargar, bool $interno): ?bool
    {
        if (!self::pideOriginal() && $this->liviano($a) !== null) {
            return $this->livianos->entregar($a, $descargar, $interno);
        }
        return $this->base->entregar($a, $descargar, $interno);
    }
}
