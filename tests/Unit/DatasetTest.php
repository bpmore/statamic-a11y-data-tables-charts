<?php

declare(strict_types=1);

use Bpmore\Furrow\Dataset\Dataset;

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
