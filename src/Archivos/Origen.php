<?php
declare(strict_types=1);

namespace Aida\Archivos;

use Aida\Reporte;

/**
 * De dónde salen los archivos digitalizados (docs/decisiones.md, 16/11/2026).
 *
 * Fuera de las implementaciones (Disco, Drive) nadie sabe dónde está el archivo: el escaneo pide `listar()`, la entrega
 * (src/Entrega.php, public/archivo.php) pide `entregar()` y el sitio pregunta `entregable()` y `admiteVisor()`.
 * Se elige en config/config.php (`archivos.origen`) con Origenes::desdeConfig().
 *
 * Las filas `$a` que reciben los métodos son las de archivo_digital (Entrega::buscar o Sitio): id, ruta, nombre_archivo,
 * formato, peso_bytes, modificado_en, drive_id…
 */
interface Origen
{
    /** 'disco' | 'drive' */
    public function nombre(): string;

    /** Para la bitácora del escaneo (proceso.fuente). */
    public function descripcion(): string;

    /**
     * Archivos que hay hoy en el origen, con la ruta relativa de siempre (C14/C14R3.pdf).
     * @return list<array{ruta:string, bytes:?int, mtime:?string}>  (más lo que el origen quiera guardar, ver metadatos())
     */
    public function listar(): array;

    /** Avisos del último listar() (líneas ilegibles de un listado, por ejemplo). @return list<string> */
    public function avisos(): array;

    /**
     * Columnas de archivo_digital propias del origen que el escaneo guarda para una fila de listar().
     * Disco: ninguna. Drive: drive_id y drive_compartido. Lo que no se devuelve no se toca (cambiar de origen no borra nada).
     * @return array<string, scalar|null>
     */
    public function metadatos(array $fila): array;

    /** Controles propios del origen al final del escaneo (dentro de su transacción): agregan incidencias al reporte. */
    public function controles(\PDO $db, Reporte $rep): void;

    /** El archivo se puede pedir (sin tocar el disco ni la red): Drive necesita el id. */
    public function entregable(array $a): bool;

    /** Se puede mostrar en el visor de la ficha; si no, solo se descarga (con aviso del peso). */
    public function admiteVisor(array $a): bool;

    /**
     * Responde el pedido ya autorizado por Entrega::decidir(): manda el archivo o redirige.
     * @return bool|null  null = no está (404); true = se entregó desde el principio (para contar el uso una vez); false = no contar
     */
    public function entregar(array $a, bool $descargar, bool $interno): ?bool;
}
