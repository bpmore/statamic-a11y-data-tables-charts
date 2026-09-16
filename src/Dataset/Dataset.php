<?php

declare(strict_types=1);

namespace Bpmore\Furrow\Dataset;

/**
 * The one shape both renderers read: a caption, what is true of each
 * column, and the rows. Built from the stored array, normalised once, so
 * the renderers never see a missing key or a colspan of "2".
 *
 * A column with no label takes the text of the lowest header row cell over
 * it, so a chart's series are named by the table's own headers and an
 * author never types a name twice.
 */
final class Dataset
{
    /**
     * @param  list<Column>  $columns
     * @param  list<Row>  $rows
     */
    private function __construct(
        public readonly string $caption,
        public readonly array $columns,
        public readonly array $rows,
    ) {}

    /** @param  array<string, mixed>|self  $data */
    public static function from(array|self $data): self
    {
        return $data instanceof self ? $data : self::fromArray($data);
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $caption = $data['caption'] ?? '';
        $caption = is_scalar($caption) ? trim((string) $caption) : '';

        if ($caption === '') {
            throw MissingCaption::make();
        }

        $rows = array_values(array_filter(
            array_map(fn ($row) => is_array($row) ? Row::fromArray($row) : null, array_values((array) ($data['rows'] ?? []))),
        ));

        $columns = array_map(fn ($c) => Column::fromArray(is_array($c) ? $c : ['label' => $c]), array_values((array) ($data['columns'] ?? [])));

        // As many columns as the widest row needs, so a pasted row that is
        // one cell longer than the column list still has a column to sit in.
        $width = max(count($columns), Grid::widthOf($rows));
        while (count($columns) < $width) {
            $columns[] = new Column('');
        }

        $dataset = new self($caption, $columns, $rows);

        return $dataset->withDerivedLabels();
    }

    /** Whether any row is a header row. */
    public function hasHeaderRow(): bool
    {
        foreach ($this->rows as $row) {
            if ($row->header) {
                return true;
            }
        }

        return false;
    }

    /** Index of the first column that names the rows, or null when none does. */
    public function headerColumn(): ?int
    {
        foreach ($this->columns as $i => $column) {
            if ($column->header) {
                return $i;
            }
        }

        return null;
    }

    /** @return list<Row> the rows that hold data rather than headers */
    public function bodyRows(): array
    {
        return array_values(array_filter($this->rows, fn (Row $r) => ! $r->header));
    }

    public function width(): int
    {
        return count($this->columns);
    }

    /** @return array{caption: string, columns: list<array<string, mixed>>, rows: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return [
            'caption' => $this->caption,
            'columns' => array_map(fn (Column $c) => $c->toArray(), $this->columns),
            'rows' => array_map(fn (Row $r) => $r->toArray(), $this->rows),
        ];
    }

    /**
     * Column labels from the header rows, for columns that have none: the
     * text of every header row cell over the column, top to bottom, so a
     * two-row header ("2024" spanning "H1" and "H2") names the series
     * "2024 H1" and not "H1" twice. A header's abbr is used when it has
     * one, since a legend is where a short form earns its keep.
     */
    private function withDerivedLabels(): self
    {
        if (! $this->hasHeaderRow()) {
            return $this;
        }

        $grid = Grid::of($this);
        $columns = $this->columns;

        foreach ($columns as $c => $column) {
            if ($column->label !== '') {
                continue;
            }

            $parts = [];
            foreach ($this->rows as $r => $row) {
                if (! $row->header) {
                    continue;
                }
                $cell = $grid->cellAt($r, $c)?->cell;
                if ($cell === null || $cell->isEmpty()) {
                    continue;
                }
                $text = $cell->abbr ?? trim($cell->value);
                if ($text !== '' && ($parts === [] || end($parts) !== $text)) {
                    $parts[] = $text;
                }
            }

            if ($parts !== []) {
                $columns[$c] = $column->withLabel(implode(' ', $parts));
            }
        }

        return new self($this->caption, $columns, $this->rows);
    }
}
