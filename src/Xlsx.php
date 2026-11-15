<?php
declare(strict_types=1);

namespace Aida;

/**
 * Lector mínimo de .xlsx sin dependencias (ZipArchive + XMLReader).
 * Devuelve celdas como string|int|float|bool|null. Las fechas se devuelven
 * como string d/m/Y cuando la celda tiene formato de fecha.
 */
final class Xlsx
{
    private \ZipArchive $zip;
    /** @var string[] */
    private array $shared = [];
    /** @var array<int,bool> índice de estilo => es fecha */
    private array $dateStyles = [];
    /** @var array<string,string> nombre de hoja => ruta xml */
    private array $sheets = [];

    public function __construct(string $path)
    {
        $this->zip = new \ZipArchive();
        if ($this->zip->open($path) !== true) {
            throw new \RuntimeException("No se pudo abrir el Excel: $path");
        }
        $this->loadSharedStrings();
        $this->loadStyles();
        $this->loadSheets();
    }

    /** @return string[] */
    public function sheetNames(): array
    {
        return array_keys($this->sheets);
    }

    /**
     * Filas de una hoja. Cada fila es un array indexado por columna (0 = A).
     * @return array<int,array<int,mixed>>
     */
    public function rows(string $sheet): array
    {
        $xml = $this->parte($this->sheets[$sheet] ?? '');
        if ($xml === false) {
            throw new \RuntimeException("Hoja inexistente: $sheet");
        }
        $r = new \XMLReader();
        $r->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT);
        $rows = [];
        $row = null;
        $rowIdx = -1;
        while ($r->read()) {
            if ($r->nodeType === \XMLReader::ELEMENT && $r->localName === 'row') {
                $rowIdx = (int)$r->getAttribute('r') - 1;
                $row = [];
                if ($r->isEmptyElement) {
                    $rows[$rowIdx] = $row;
                    $row = null;
                }
            } elseif ($r->nodeType === \XMLReader::END_ELEMENT && $r->localName === 'row') {
                $rows[$rowIdx] = $row ?? [];
                $row = null;
            } elseif ($r->nodeType === \XMLReader::ELEMENT && $r->localName === 'c' && $row !== null) {
                $ref = (string)$r->getAttribute('r');
                $type = (string)$r->getAttribute('t');
                $style = (int)$r->getAttribute('s');
                $col = self::colIndex($ref);
                $node = new \SimpleXMLElement($r->readOuterXml());
                $row[$col] = $this->cellValue($node, $type, $style);
            }
        }
        ksort($rows);
        return $rows;
    }

    private function cellValue(\SimpleXMLElement $c, string $type, int $style): mixed
    {
        $c->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $v = isset($c->v) ? (string)$c->v : null;
        switch ($type) {
            case 's':
                return $v === null ? null : ($this->shared[(int)$v] ?? null);
            case 'inlineStr':
                return self::richText($c->is);
            case 'b':
                return $v === null ? null : $v === '1';
            case 'str':
            case 'e':
                return $v;
        }
        if ($v === null || $v === '') {
            return null;
        }
        if (!is_numeric($v)) {
            return $v;
        }
        if (!empty($this->dateStyles[$style])) {
            return self::excelDate((float)$v);
        }
        $f = (float)$v;
        return (floor($f) === $f && abs($f) < PHP_INT_MAX) ? (int)$f : $f;
    }

    private static function excelDate(float $serial): string
    {
        $days = (int)floor($serial);
        $ts = ($days - 25569) * 86400; // 1970-01-01
        return gmdate('d/m/Y', $ts);
    }

    private static function richText(?\SimpleXMLElement $node): ?string
    {
        if ($node === null) {
            return null;
        }
        if (isset($node->t)) {
            return (string)$node->t;
        }
        $s = '';
        if (!isset($node->r)) {
            return $s; // celda de texto vacía (la escriben así openpyxl y otras herramientas)
        }
        foreach ($node->r as $run) {
            $s .= (string)$run->t;
        }
        return $s;
    }

    private static function colIndex(string $ref): int
    {
        $letters = preg_replace('/\d+/', '', $ref);
        $n = 0;
        foreach (str_split((string)$letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }

    /** Lee una parte del zip y verifica que el tamaño leído coincida con el declarado (zip con tamaños falseados). */
    private function parte(string $nombre): string|false
    {
        $st = $this->zip->statName($nombre);
        if ($st === false) {
            return false;
        }
        $dato = $this->zip->getFromName($nombre);
        if ($dato === false || strlen($dato) !== (int)$st['size']) {
            throw new \RuntimeException("El Excel está dañado: la parte «{$nombre}» no se pudo leer completa.");
        }
        return $dato;
    }

    private function loadSharedStrings(): void
    {
        $xml = $this->parte('xl/sharedStrings.xml');
        if ($xml === false) {
            return;
        }
        $r = new \XMLReader();
        $r->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT);
        while ($r->read()) {
            if ($r->nodeType === \XMLReader::ELEMENT && $r->localName === 'si') {
                $node = new \SimpleXMLElement($r->readOuterXml());
                $this->shared[] = (string)self::richText($node);
            }
        }
    }

    private function loadStyles(): void
    {
        $xml = $this->parte('xl/styles.xml');
        if ($xml === false) {
            return;
        }
        $doc = new \SimpleXMLElement($xml);
        $custom = [];
        if (isset($doc->numFmts)) {
            foreach ($doc->numFmts->numFmt as $f) {
                $custom[(int)$f['numFmtId']] = (string)$f['formatCode'];
            }
        }
        $builtinDates = array_merge(range(14, 22), range(45, 47));
        $i = 0;
        if (isset($doc->cellXfs)) {
            foreach ($doc->cellXfs->xf as $xf) {
                $id = (int)$xf['numFmtId'];
                $isDate = in_array($id, $builtinDates, true);
                if (!$isDate && isset($custom[$id])) {
                    $code = preg_replace('/"[^"]*"|\[[^\]]*\]/', '', $custom[$id]);
                    $isDate = (bool)preg_match('/[dmy]/i', (string)$code) && !preg_match('/[#0]/', (string)$code);
                }
                $this->dateStyles[$i++] = $isDate;
            }
        }
    }

    private function loadSheets(): void
    {
        $wb = new \SimpleXMLElement((string)$this->parte('xl/workbook.xml'));
        $rels = new \SimpleXMLElement((string)$this->parte('xl/_rels/workbook.xml.rels'));
        $targets = [];
        foreach ($rels->Relationship as $rel) {
            $targets[(string)$rel['Id']] = (string)$rel['Target'];
        }
        foreach ($wb->sheets->sheet as $sh) {
            $rid = (string)$sh->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $t = $targets[$rid] ?? '';
            $t = str_starts_with($t, '/') ? ltrim($t, '/') : 'xl/' . $t;
            $this->sheets[(string)$sh['name']] = $t;
        }
    }
}
