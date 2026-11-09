<?php

declare(strict_types=1);

use Bpmore\A11yDataTablesCharts\Dataset\Cell;
use Bpmore\A11yDataTablesCharts\Dataset\Dataset;
use Bpmore\A11yDataTablesCharts\Dataset\Grid;
use Bpmore\A11yDataTablesCharts\Dataset\Limits;
use Bpmore\A11yDataTablesCharts\Dataset\Row;
use Bpmore\A11yDataTablesCharts\Dataset\TableTooLarge;

it('names columns from every header row over them, using abbr where there is one', function () {
    $dataset = Dataset::fromArray([
        'caption' => 'Two header rows',
        'columns' => [['header' => true], ['type' => 'number'], ['type' => 'number'], ['type' => 'number']],
        'rows' => [
            ['header' => true, 'cells' => [['value' => ''], ['value' => '2024', 'colspan' => 2], ['value' => '2025']]],
            ['header' => true, 'cells' => [['value' => 'Clinic'], ['value' => 'January to June', 'abbr' => 'H1'], ['value' => 'July to December', 'abbr' => 'H2'], ['value' => 'Total']]],
            ['cells' => [['value' => 'Sherwood'], ['value' => 1], ['value' => 2], ['value' => 3]]],
        ],
    ]);

    expect(array_map(fn ($c) => $c->label, $dataset->columns))->toBe(['Clinic', '2024 H1', '2024 H2', '2025 Total']);
});

it('keeps a label the author gave and takes the header text for a single header row', function () {
    $dataset = Dataset::fromArray([
        'caption' => 'One header row',
        'columns' => [['label' => 'Given'], []],
        'rows' => [
            ['header' => true, 'cells' => [['value' => 'Year'], ['value' => 'Apples']]],
            ['cells' => [['value' => '2024'], ['value' => 1]]],
        ],
    ]);

    expect(array_map(fn ($c) => $c->label, $dataset->columns))->toBe(['Given', 'Apples']);
});

/**
 * A span is a number the stored shape carries, and the grid makes a slot
 * for every row and column a cell covers. With no ceiling, one cell saying
 * it spans a million of each asked for a million million slots on every
 * render. The ceiling is applied where the span is read.
 */
it('clamps a span to the limit as a cell is read, and leaves ordinary spans alone', function () {
    $huge = Cell::fromArray(['value' => 'a', 'colspan' => 1_000_000, 'rowspan' => PHP_INT_MAX]);
    expect($huge->colspan)->toBe(Limits::SPAN);
    expect($huge->rowspan)->toBe(Limits::SPAN);

    $plain = Cell::fromArray(['value' => 'a', 'colspan' => 2, 'rowspan' => '3']);
    expect($plain->colspan)->toBe(2);
    expect($plain->rowspan)->toBe(3);

    $none = Cell::fromArray(['value' => 'a', 'colspan' => 0, 'rowspan' => -5]);
    expect($none->colspan)->toBe(1);
    expect($none->rowspan)->toBe(1);
});

it('refuses to lay out a grid with more rows than the limit', function () {
    Grid::widthOf(array_fill(0, Limits::ROWS + 1, Row::fromArray([['value' => 'a']])));
})->throws(TableTooLarge::class);

it('refuses to lay out a grid wider than the limit, before making a slot', function () {
    Grid::widthOf([Row::fromArray(array_fill(0, Limits::COLUMNS + 1, ['value' => 'a']))]);
})->throws(TableTooLarge::class);

it('lays out a grid at the limits, and a rowspan reaching past the last row makes no slots', function () {
    $full = array_fill(0, Limits::ROWS, Row::fromArray(array_fill(0, Limits::COLUMNS, ['value' => 'a'])));
    expect(Grid::widthOf($full))->toBe(Limits::COLUMNS);

    $grid = Grid::of(Dataset::fromArray([
        'caption' => 'One cell, spanning down past the end',
        'rows' => [['cells' => [['value' => 'a', 'rowspan' => Limits::SPAN]]]],
    ]));
    expect($grid->height)->toBe(1);
    expect($grid->cellAt(0, 0)?->cell->value)->toBe('a');
    expect($grid->cellAt(1, 0))->toBeNull();
});

it('says why a stored table is too big, from the raw shape, and nothing for one that is not', function () {
    expect(Limits::check(['rows' => [[['value' => 'a', 'colspan' => 1_000_000]]]]))->toContain('spans 1000000 columns');
    expect(Limits::check(['rows' => [['cells' => [['value' => 'a', 'rowspan' => '101']]]]]))->toContain('spans 101 rows');
    expect(Limits::check(['rows' => array_fill(0, Limits::ROWS + 1, [['value' => 'a']])]))->toContain('rows');
    expect(Limits::check(['columns' => array_fill(0, Limits::COLUMNS + 1, ['label' => 'x'])]))->toContain('columns');

    // Two spans each within the limit that together reach past the last column.
    expect(Limits::check(['rows' => [[['value' => 'a', 'colspan' => 60], ['value' => 'b', 'colspan' => 60]]]]))->toContain('wider than');
    expect(Limits::check(['rows' => [[['value' => 'a', 'colspan' => 50], ['value' => 'b', 'colspan' => 50]]]]))->toBeNull();

    expect(Limits::check(['rows' => [[['value' => 'a', 'colspan' => Limits::SPAN, 'rowspan' => Limits::SPAN]]]]))->toBeNull();
    expect(Limits::check([]))->toBeNull();
});
