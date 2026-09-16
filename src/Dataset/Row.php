<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Dataset;

/** One row: whether it is a header row, and its cells in order. */
final class Row
{
    /** @param  list<Cell>  $cells */
    public function __construct(
        public readonly bool $header,
        public readonly array $cells,
    ) {}

    /** @param  array<string, mixed>|list<mixed>  $row  the stored shape, or a bare list of values */
    public static function fromArray(array $row): self
    {
        $cells = array_key_exists('cells', $row) ? $row['cells'] : (array_is_list($row) ? $row : []);

        return new self(
            (bool) ($row['header'] ?? false),
            array_map(fn ($cell) => Cell::fromArray($cell), array_values(is_array($cells) ? $cells : [])),
        );
    }

    /** @return array{header: bool, cells: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return ['header' => $this->header, 'cells' => array_map(fn (Cell $c) => $c->toArray(), $this->cells)];
    }
}
