<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Table;

use Bpmore\A11yDataTablesCharts\Dataset\Dataset;
use Bpmore\A11yDataTablesCharts\Dataset\Grid;
use Bpmore\A11yDataTablesCharts\Dataset\Placed;
use Bpmore\A11yDataTablesCharts\Support\Html;

/**
 * A dataset as an HTML table with its headers correctly associated.
 *
 * Simple tables (one header row at the top, at most one plain header
 * column, nothing spanning) get `scope`, which is all they need. Anything
 * more gets an `id` on every header cell and a `headers` attribute on every
 * cell naming exactly the headers that apply to it, because once a header
 * spans, `scope` alone no longer tells a screen reader which header goes
 * with which cell. That pairing is the reason this renderer exists.
 *
 * Pure PHP: an array in, a string out.
 */
final class TableRenderer
{
    /**
     * @param  array<string, mixed>|Dataset  $data
     * `labelledby` adds ids before the caption's in the region's name, for
     * a table that sits inside something already named (a chart's data
     * table reads "Data table: <caption>", so a page that shows the chart
     * and the table itself has two regions with different names).
     *
     * @param  array{id?: string|null, class?: string|null, labelledby?: string|null}  $options
     */
    public function render(array|Dataset $data, array $options = []): string
    {
        $dataset = Dataset::from($data);
        $grid = Grid::of($dataset);
        $id = Html::id($options['id'] ?? 'a11y-data-'.substr(sha1(json_encode($dataset->toArray()) ?: ''), 0, 8));
        $complex = $this->isComplex($dataset, $grid);
        $leading = $this->leadingHeaderRows($dataset);

        $rows = [];
        foreach ($dataset->rows as $r => $row) {
            $cells = '';
            foreach ($grid->rowCells($r) as $placed) {
                $cells .= $this->cell($placed, $dataset, $grid, $id, $complex);
            }
            $rows[$r] = '<tr>'.$cells.'</tr>';
        }

        $thead = $leading > 0 ? '<thead>'.implode('', array_slice($rows, 0, $leading)).'</thead>' : '';
        $tbody = count($rows) > $leading ? '<tbody>'.implode('', array_slice($rows, $leading)).'</tbody>' : '';

        $class = trim('a11y-data-table '.(string) ($options['class'] ?? ''));

        // The scroll region: overflow-x on the wrapper, and tabindex so a
        // keyboard can reach it when it does overflow. Named by the caption
        // so what is announced on focus is what the table is.
        return '<div'.Html::attrs([
            'class' => $class,
            'role' => 'region',
            'aria-labelledby' => trim((string) ($options['labelledby'] ?? '').' '.$id.'-caption'),
            'tabindex' => '0',
        ]).'>'
            .'<table'.Html::attrs(['id' => $id, 'class' => 'a11y-data-table__table']).'>'
            .'<caption'.Html::attrs(['id' => $id.'-caption']).'>'.Html::e($dataset->caption).'</caption>'
            .$thead.$tbody
            .'</table></div>';
    }

    /**
     * Whether `scope` is enough. It is not once there is more than one
     * header row, a header row below data, more than one header column, or
     * any header cell that spans.
     */
    private function isComplex(Dataset $dataset, Grid $grid): bool
    {
        $headerRows = 0;
        foreach ($dataset->rows as $row) {
            $headerRows += $row->header ? 1 : 0;
        }
        if ($headerRows > 1 || ($headerRows > 0 && $this->leadingHeaderRows($dataset) !== $headerRows)) {
            return true;
        }

        $headerColumns = 0;
        foreach ($dataset->columns as $column) {
            $headerColumns += $column->header ? 1 : 0;
        }
        if ($headerColumns > 1) {
            return true;
        }

        foreach ($grid->placed() as $placed) {
            if ($this->isHeader($placed, $dataset) && ($placed->cell->colspan > 1 || $placed->cell->rowspan > 1)) {
                return true;
            }
        }

        return false;
    }

    /** How many header rows sit at the top, which is what goes in <thead>. */
    private function leadingHeaderRows(Dataset $dataset): int
    {
        $n = 0;
        foreach ($dataset->rows as $row) {
            if (! $row->header) {
                break;
            }
            $n++;
        }

        return $n;
    }

    private function inHeaderRow(Placed $placed, Dataset $dataset): bool
    {
        return $dataset->rows[$placed->row]->header;
    }

    private function inHeaderColumn(Placed $placed, Dataset $dataset): bool
    {
        return $dataset->columns[$placed->col]->header ?? false;
    }

    /**
     * A cell is a header when it is in a header row or a header column,
     * except the empty corner, which names nothing and is a plain cell.
     */
    private function isHeader(Placed $placed, Dataset $dataset): bool
    {
        if (! $this->inHeaderRow($placed, $dataset) && ! $this->inHeaderColumn($placed, $dataset)) {
            return false;
        }

        return ! ($this->inHeaderRow($placed, $dataset) && $this->inHeaderColumn($placed, $dataset) && $placed->cell->isEmpty());
    }

    private function headerId(Placed $placed, string $id): string
    {
        return $id.'-r'.$placed->row.'c'.$placed->col;
    }

    /**
     * The headers that apply to a cell, in a complex table: every header
     * row cell above it covering any of its columns, and every header
     * column cell to its left covering any of its rows. A header row cell
     * sitting in a header column is a column header for that column, not a
     * row header for its row, and is only ever collected from above.
     *
     * @return list<string>
     */
    private function headersFor(Placed $placed, Dataset $dataset, Grid $grid, string $id): array
    {
        $ids = [];

        // From above: header rows, any column this cell covers. A header
        // spanning down from an earlier header row is met again in the
        // later row and collected once, at its origin.
        for ($r = 0; $r < $placed->row; $r++) {
            if (! $dataset->rows[$r]->header) {
                continue;
            }
            for ($c = $placed->col; $c < $placed->col + $placed->cell->colspan; $c++) {
                $h = $grid->cellAt($r, $c);
                if ($h !== null && $h->row === $r && $this->isHeader($h, $dataset)) {
                    $ids[] = $this->headerId($h, $id);
                }
            }
        }

        // From the left: header columns, any row this cell covers, skipping
        // cells that are in header rows (those are column headers).
        for ($c = 0; $c < $placed->col; $c++) {
            if (! ($dataset->columns[$c]->header ?? false)) {
                continue;
            }
            for ($r = $placed->row; $r < $placed->row + $placed->cell->rowspan; $r++) {
                $h = $grid->cellAt($r, $c);
                if ($h !== null && $h->col === $c && ! $this->inHeaderRow($h, $dataset) && $this->isHeader($h, $dataset)) {
                    $ids[] = $this->headerId($h, $id);
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function cell(Placed $placed, Dataset $dataset, Grid $grid, string $id, bool $complex): string
    {
        $cell = $placed->cell;
        $column = $dataset->columns[$placed->col] ?? null;
        $header = $this->isHeader($placed, $dataset);

        $attributes = [
            'class' => 'a11y-data-cell a11y-data-cell--'.($column?->type ?? 'text'),
            'colspan' => $cell->colspan > 1 ? $cell->colspan : null,
            'rowspan' => $cell->rowspan > 1 ? $cell->rowspan : null,
        ];

        if ($header) {
            $spansColumns = $cell->colspan > 1;
            $spansRows = $cell->rowspan > 1;

            $attributes['scope'] = $this->inHeaderRow($placed, $dataset)
                ? ($spansColumns ? 'colgroup' : 'col')
                : ($spansRows ? 'rowgroup' : 'row');
            $attributes['abbr'] = $cell->abbr;

            if ($complex) {
                $attributes['id'] = $this->headerId($placed, $id);
            }
        }

        if ($complex) {
            $headers = $this->headersFor($placed, $dataset, $grid, $id);
            $attributes['headers'] = $headers === [] ? null : implode(' ', $headers);
        }

        $tag = $header ? 'th' : 'td';

        return '<'.$tag.Html::attrs($attributes).'>'.Html::e($cell->value).'</'.$tag.'>';
    }
}
