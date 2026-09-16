<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Statamic;

use Bpmore\A11yDataTablesCharts\Dataset\Cell;
use Bpmore\A11yDataTablesCharts\Dataset\Column;
use Bpmore\A11yDataTablesCharts\Dataset\Row;

/**
 * The stored shape of a table, with every key present and every value
 * the type the brief fixes. Used on the way into the editor, on the way
 * out of it, and by the tag when handed a bare array. Unlike Dataset it
 * does not require a caption, because an author mid-edit has none yet.
 */
final class Normalise
{
    /**
     * @return array{caption: string, columns: list<array{label: string, type: string, header: bool}>, rows: list<array{header: bool, cells: list<array<string, mixed>>}>}
     */
    public static function table(mixed $data): array
    {
        $data = is_array($data) ? $data : [];

        $caption = $data['caption'] ?? '';
        $rows = array_values(array_filter(array_map(
            fn ($row) => is_array($row) ? Row::fromArray($row)->toArray() : null,
            array_values(is_array($data['rows'] ?? null) ? $data['rows'] : []),
        )));

        $width = 0;
        foreach ($rows as $row) {
            $w = 0;
            foreach ($row['cells'] as $cell) {
                $w += $cell['colspan'] ?? 1;
            }
            $width = max($width, $w);
        }

        $columns = array_map(
            fn ($c) => Column::fromArray(is_array($c) ? $c : ['label' => $c])->toArray(),
            array_values(is_array($data['columns'] ?? null) ? $data['columns'] : []),
        );
        while (count($columns) < $width) {
            $columns[] = (new Column(''))->toArray();
        }

        return [
            'caption' => is_scalar($caption) ? trim((string) $caption) : '',
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /** Whether a normalised table has nothing in it at all: no caption, no cell with text. */
    public static function tableIsEmpty(array $table): bool
    {
        if ($table['caption'] !== '') {
            return false;
        }

        foreach ($table['rows'] as $row) {
            foreach ($row['cells'] as $cell) {
                if (! Cell::fromArray($cell)->isEmpty()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array{type: string, source: string, table: string|null, data: array<string, mixed>|null, summary: string}
     */
    public static function chart(mixed $data): array
    {
        $data = is_array($data) ? $data : [];

        $type = $data['type'] ?? 'bar';
        $source = ($data['source'] ?? 'field') === 'inline' ? 'inline' : 'field';
        $table = $data['table'] ?? null;
        $summary = $data['summary'] ?? '';

        return [
            'type' => is_string($type) && $type !== '' ? $type : 'bar',
            'source' => $source,
            'table' => is_string($table) && trim($table) !== '' ? trim($table) : null,
            'data' => $source === 'inline' ? self::table($data['data'] ?? null) : null,
            'summary' => is_scalar($summary) ? trim((string) $summary) : '',
        ];
    }
}
