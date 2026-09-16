<?php

declare(strict_types=1);

/**
 * Renders the fixture pages the front-end check reads: every chart type,
 * a complex table, negatives and gaps, a collapsed data table, and a
 * many-series chart, each in a plain page with the addon's stylesheet
 * inlined the way `{{ a11y_data:styles }}` inlines it.
 *
 *   php tests/a11y/render-fixtures.php
 */

require __DIR__.'/../../vendor/autoload.php';

use Bpmore\A11yDataTablesCharts\Chart\ChartRenderer;
use Bpmore\A11yDataTablesCharts\Table\TableRenderer;

$out = __DIR__.'/fixtures';
if (! is_dir($out)) {
    mkdir($out, 0755, true);
}

$five = [
    'caption' => 'Enrolment by campus',
    'columns' => [['type' => 'text', 'header' => true], ['type' => 'number'], ['type' => 'number'], ['type' => 'number'], ['type' => 'number'], ['type' => 'number']],
    'rows' => [
        ['header' => true, 'cells' => [['value' => 'Year'], ['value' => 'North'], ['value' => 'South'], ['value' => 'East'], ['value' => 'West'], ['value' => 'Online']]],
        ['cells' => [['value' => '2021'], ['value' => 120], ['value' => 80], ['value' => 60], ['value' => 40], ['value' => 20]]],
        ['cells' => [['value' => '2022'], ['value' => 130], ['value' => 70], ['value' => 65], ['value' => 45], ['value' => 35]]],
        ['cells' => [['value' => '2023'], ['value' => 125], ['value' => 90], ['value' => 55], ['value' => 50], ['value' => 60]]],
        ['cells' => [['value' => '2024'], ['value' => 140], ['value' => 85], ['value' => 70], ['value' => 55], ['value' => 90]]],
    ],
];
$one = ['caption' => 'Apples harvested per year', 'columns' => [['type' => 'text', 'header' => true], ['type' => 'number']], 'rows' => [
    ['header' => true, 'cells' => [['value' => 'Year'], ['value' => 'Apples (kg)']]],
    ['cells' => [['value' => '2021'], ['value' => '1,200']]], ['cells' => [['value' => '2022'], ['value' => '1,550']]], ['cells' => [['value' => '2023'], ['value' => '980']]], ['cells' => [['value' => '2024'], ['value' => '1,730']]],
]];
$neg = ['caption' => 'Net change by quarter', 'columns' => [['type' => 'text', 'header' => true], ['type' => 'number'], ['type' => 'number']], 'rows' => [
    ['header' => true, 'cells' => [['value' => 'Quarter'], ['value' => 'Revenue'], ['value' => 'Costs']]],
    ['cells' => [['value' => 'Q1'], ['value' => -12.5], ['value' => 4]]], ['cells' => [['value' => 'Q2'], ['value' => 8], ['value' => -3]]], ['cells' => [['value' => 'Q3'], ['value' => 15], ['value' => '']]],
]];
$complex = [
    'caption' => 'Sales by region and quarter',
    'columns' => [['type' => 'text', 'header' => true], ['type' => 'number'], ['type' => 'number'], ['type' => 'number'], ['type' => 'number']],
    'rows' => [
        ['header' => true, 'cells' => [['value' => ''], ['value' => 'First half', 'colspan' => 2], ['value' => 'Second half', 'colspan' => 2]]],
        ['header' => true, 'cells' => [['value' => 'Region'], ['value' => 'Q1'], ['value' => 'Q2'], ['value' => 'Q3'], ['value' => 'Q4']]],
        ['cells' => [['value' => 'North'], ['value' => 10], ['value' => 20], ['value' => 30], ['value' => 40]]],
        ['cells' => [['value' => 'South'], ['value' => 30], ['value' => 40], ['value' => 50], ['value' => 60]]],
    ],
];
$wide = [
    'caption' => 'A wide table that must scroll rather than reflow',
    'columns' => array_merge([['type' => 'text', 'header' => true]], array_fill(0, 11, ['type' => 'number'])),
    'rows' => array_merge(
        [['header' => true, 'cells' => array_map(fn ($m) => ['value' => $m], ['Month', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'])]],
        array_map(fn ($y) => ['cells' => array_map(fn ($v) => ['value' => $v], array_merge([$y], range(100, 1100, 100)))], ['2022', '2023', '2024']),
    ),
];
$twelve = [
    'caption' => 'Twelve series, which cycles patterns and colours',
    'columns' => array_merge([['type' => 'text', 'header' => true]], array_fill(0, 12, ['type' => 'number'])),
    'rows' => array_merge(
        [['header' => true, 'cells' => array_map(fn ($i) => ['value' => $i === 0 ? 'Year' : 'Series '.$i], range(0, 12))]],
        array_map(fn ($y) => ['cells' => array_map(fn ($i) => ['value' => $i === 0 ? $y : $i * 3 + (int) $y % 7], range(0, 12))], ['2023', '2024']),
    ),
];

$c = new ChartRenderer;
$t = new TableRenderer;
$css = file_get_contents(__DIR__.'/../../resources/css/a11y-data.css');

$page = fn (string $title, string $body) => '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'.htmlspecialchars($title).'</title><style>body{font-family:system-ui,sans-serif;max-width:60rem;margin:0 auto;padding:1rem;color:#1a1a1a;background:#fff;line-height:1.5}</style><style>'.$css.'</style></head><body><main><h1>'.htmlspecialchars($title).'</h1>'.$body.'</main></body></html>';

$fixtures = [
    'grouped-bar' => ['Grouped bar chart', $c->render($five, ['type' => 'grouped_bar', 'id' => 'chart'])],
    'line' => ['Line chart', $c->render($five, ['type' => 'line', 'id' => 'chart'])],
    'bar' => ['Bar chart, one series', $c->render($one, ['type' => 'bar', 'id' => 'chart'])],
    // Two charts of one dataset on one page need two captions: a page that
    // repeats a caption repeats a landmark name, which axe rightly flags.
    'negatives' => ['Negatives and a gap', $c->render($neg, ['type' => 'line', 'id' => 'chart']).$c->render(['caption' => 'Net change by quarter, as bars'] + $neg, ['type' => 'bar', 'id' => 'chart2'])],
    'collapsed' => ['Collapsed data table', $c->render($one, ['type' => 'bar', 'id' => 'chart', 'table' => 'collapsed'])],
    'complex-table' => ['Complex table', $t->render($complex, ['id' => 'table'])],
    'wide-table' => ['Wide table', $t->render($wide, ['id' => 'table'])],
    'twelve' => ['Twelve series', $c->render($twelve, ['type' => 'grouped_bar', 'id' => 'chart']).$c->render(['caption' => 'Twelve series, as lines'] + $twelve, ['type' => 'line', 'id' => 'chart2'])],
];

foreach ($fixtures as $name => [$title, $body]) {
    file_put_contents("$out/$name.html", $page($title, $body));
    echo "  wrote tests/a11y/fixtures/$name.html\n";
}
