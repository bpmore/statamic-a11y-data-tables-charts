<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Chart;

use Bpmore\A11yDataTablesCharts\Dataset\Dataset;
use Bpmore\A11yDataTablesCharts\Dataset\Grid;

/**
 * The dataset read as categories and series. The column that names the
 * rows (the header column, or the first column when none is marked) gives
 * the categories; every other column typed as a number is a series, or
 * every other column at all when none is typed, since a pasted table
 * arrives with no types yet. Header rows are not data.
 */
final class ChartData
{
    /**
     * @param  list<string>  $categories
     * @param  list<Series>  $series
     */
    private function __construct(
        public readonly array $categories,
        public readonly array $series,
    ) {}

    public static function from(Dataset $dataset): self
    {
        $grid = Grid::of($dataset);
        $categoryColumn = $dataset->headerColumn() ?? 0;

        $seriesColumns = [];
        foreach ($dataset->columns as $c => $column) {
            if ($c !== $categoryColumn && $column->type === 'number') {
                $seriesColumns[] = $c;
            }
        }
        if ($seriesColumns === []) {
            foreach ($dataset->columns as $c => $column) {
                if ($c !== $categoryColumn) {
                    $seriesColumns[] = $c;
                }
            }
        }

        $bodyRows = [];
        foreach ($dataset->rows as $r => $row) {
            if (! $row->header) {
                $bodyRows[] = $r;
            }
        }

        $categories = [];
        foreach ($bodyRows as $r) {
            $categories[] = $grid->cellAt($r, $categoryColumn)?->cell->value ?? '';
        }

        $series = [];
        foreach ($seriesColumns as $c) {
            $values = [];
            $labels = [];
            foreach ($bodyRows as $r) {
                $cell = $grid->cellAt($r, $c)?->cell;
                $values[] = $cell?->number();
                $labels[] = $cell?->value ?? '';
            }
            $series[] = new Series($dataset->columns[$c]->label !== '' ? $dataset->columns[$c]->label : 'Column '.($c + 1), $values, $labels);
        }

        return new self($categories, $series);
    }

    public function min(): float
    {
        $all = $this->all();

        return $all === [] ? 0.0 : min($all);
    }

    public function max(): float
    {
        $all = $this->all();

        return $all === [] ? 0.0 : max($all);
    }

    /** @return list<float> */
    private function all(): array
    {
        $all = [];
        foreach ($this->series as $s) {
            foreach ($s->values as $v) {
                if ($v !== null) {
                    $all[] = $v;
                }
            }
        }

        return $all;
    }
}
