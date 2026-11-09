<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Dataset;

/**
 * Where every cell lands once colspan and rowspan are applied: the layout
 * a browser computes for a table, computed here so headers can be paired
 * with the cells they actually sit above and beside.
 *
 * The rule is HTML's own: cells fill left to right, skipping any slot a
 * span from an earlier row already occupies.
 */
final class Grid
{
    /**
     * @param  array<int, array<int, Placed|null>>  $slots  by row then column
     * @param  list<Placed>  $placed  every cell once, in document order
     */
    private function __construct(
        private readonly array $slots,
        private readonly array $placed,
        public readonly int $width,
        public readonly int $height,
    ) {}

    public static function of(Dataset $dataset): self
    {
        return self::layout($dataset->rows, $dataset->width());
    }

    /** @param  list<Row>  $rows */
    public static function widthOf(array $rows): int
    {
        return self::layout($rows, 0)->width;
    }

    /**
     * Never more than Limits::ROWS by Limits::COLUMNS slots, whatever the
     * rows say: a table past either is refused before a slot is made.
     *
     * @param  list<Row>  $rows
     *
     * @throws TableTooLarge
     */
    private static function layout(array $rows, int $minWidth): self
    {
        $height = count($rows);

        if ($height > Limits::ROWS) {
            throw TableTooLarge::rows($height);
        }
        if ($minWidth > Limits::COLUMNS) {
            throw TableTooLarge::columns();
        }

        $slots = [];
        $placed = [];
        $width = $minWidth;

        foreach ($rows as $r => $row) {
            $c = 0;
            foreach ($row->cells as $cell) {
                while (isset($slots[$r][$c])) {
                    $c++;
                }

                if ($c + $cell->colspan > Limits::COLUMNS) {
                    throw TableTooLarge::columns();
                }

                $p = new Placed($cell, $r, $c);
                $placed[] = $p;

                // A rowspan can reach past the last row; those slots are
                // not made, as a browser does not make them.
                $rowspan = min($cell->rowspan, $height - $r);

                for ($dr = 0; $dr < $rowspan; $dr++) {
                    for ($dc = 0; $dc < $cell->colspan; $dc++) {
                        $slots[$r + $dr][$c + $dc] = $p;
                    }
                }

                $c += $cell->colspan;
                $width = max($width, $c);
            }
        }

        return new self($slots, $placed, $width, $height);
    }

    /** The cell covering a slot, whether it starts there or spans into it. */
    public function cellAt(int $row, int $col): ?Placed
    {
        return $this->slots[$row][$col] ?? null;
    }

    /** @return list<Placed> */
    public function placed(): array
    {
        return $this->placed;
    }

    /** @return list<Placed> the cells of one row, in order, each once */
    public function rowCells(int $row): array
    {
        return array_values(array_filter($this->placed, fn (Placed $p) => $p->row === $row));
    }
}
