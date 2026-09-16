<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Dataset;

/** A cell at its place in the grid: which row and column it starts in, and how far it reaches. */
final class Placed
{
    public function __construct(
        public readonly Cell $cell,
        public readonly int $row,
        public readonly int $col,
    ) {}

    public function coversRow(int $r): bool
    {
        return $r >= $this->row && $r < $this->row + $this->cell->rowspan;
    }

    public function coversCol(int $c): bool
    {
        return $c >= $this->col && $c < $this->col + $this->cell->colspan;
    }
}
