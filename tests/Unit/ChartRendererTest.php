<?php

declare(strict_types=1);

use Bpmore\Furrow\Chart\ChartRenderer;
use Bpmore\Furrow\Chart\Palette;
use Bpmore\Furrow\Chart\PaletteFails;
use Bpmore\Furrow\Chart\UnknownChartType;
use Bpmore\Furrow\Dataset\MissingCaption;

/** Five series over four categories. */
function fiveSeries(): array
{
    return table(
        'Enrolment by campus',
        ['Year', 'North', 'South', 'East', 'West', 'Online'],
        [
            ['Year', 'North', 'South', 'East', 'West', 'Online'],
            ['2021', 120, 80, 60, 40, 20],
            ['2022', 130, 70, 65, 45, 35],
            ['2023', 125, 90, 55, 50, 60],
            ['2024', 140, 85, 70, 55, 90],
        ],
        headerRows: [0],
        headerColumns: [0],
    );
}

/**
 * Discriminating test 4, bars: five series get five distinct fill
 * patterns, each referenced by the bars of exactly that series.
 */
it('renders five distinct fill patterns for a five-series grouped bar chart', function () {
    $html = (new ChartRenderer)->render(fiveSeries(), ['type' => 'grouped_bar', 'id' => 'g']);

    $x = dom($html);

    $patterns = nodes($x, '//svg//defs//pattern[@id]');
    expect($patterns)->toHaveCount(5);

    $ids = array_map(fn ($p) => $p->getAttribute('id'), $patterns);
    expect(array_unique($ids))->toHaveCount(5);

    // The patterns differ in what they draw, not only in what they are
    // called: a pattern's children, serialised, are unique across the five.
    $shapes = array_map(fn ($p) => preg_replace('/\s+/', '', preg_replace('/\b(id|fill|stroke)="[^"]*"/', '', $p->ownerDocument->saveHTML($p))), $patterns);
    expect(array_unique($shapes))->toHaveCount(5, 'two patterns draw the same thing');

    // Every series' bars reference that series' pattern: four bars (one per
    // category) and the one legend swatch, and nothing else.
    foreach ($ids as $id) {
        expect(nodes($x, "//svg//rect[@class='furrow-bar'][contains(@fill, 'url(#$id)')]"))->toHaveCount(4, "pattern $id is not on four bars");
        expect(nodes($x, "//svg//rect[@class='furrow-legend__swatch'][contains(@fill, 'url(#$id)')]"))->toHaveCount(1, "pattern $id is not in the legend");
        expect(nodes($x, "//svg//*[contains(@fill, 'url(#$id)')]"))->toHaveCount(5);
    }
});

/**
 * Discriminating test 4, lines: five series get five distinct dash arrays
 * and five distinct marker shapes.
 */
it('renders five distinct dash arrays and marker shapes for a five-series line chart', function () {
    $html = (new ChartRenderer)->render(fiveSeries(), ['type' => 'line', 'id' => 'l']);

    $x = dom($html);

    $lines = nodes($x, "//svg//*[contains(concat(' ', normalize-space(@class), ' '), ' furrow-line ')]");
    expect($lines)->toHaveCount(5);

    $dashes = array_map(fn ($l) => trim($l->getAttribute('stroke-dasharray')), $lines);
    expect(array_unique($dashes))->toHaveCount(5, 'two lines share a dash array: '.implode(' | ', $dashes));

    // One marker shape per series, four markers per series.
    $markers = nodes($x, "//svg//*[contains(concat(' ', normalize-space(@class), ' '), ' furrow-marker ')]");
    expect($markers)->toHaveCount(20);

    $shapesBySeries = [];
    foreach ($markers as $m) {
        $series = $m->getAttribute('data-series');
        $shape = $m->getAttribute('data-shape');
        expect($shape)->not->toBe('');
        $shapesBySeries[$series][] = $shape;
    }
    expect($shapesBySeries)->toHaveCount(5);
    $distinct = array_map(fn ($shapes) => array_values(array_unique($shapes))[0], $shapesBySeries);
    expect(array_unique($distinct))->toHaveCount(5, 'two series share a marker shape');
    foreach ($shapesBySeries as $shapes) {
        expect(array_unique($shapes))->toHaveCount(1, 'a series changes marker shape mid-line');
    }
});

/**
 * Discriminating test 5: the data table is always present. Every value a
 * caller could put in the table option, including the ones that read like
 * "off", still yields a real <table> with a <caption>.
 */
it('always renders the data table whatever the options say', function (array $options) {
    $html = (new ChartRenderer)->render(fiveSeries(), $options + ['type' => 'bar']);

    $x = dom($html);

    expect(nodes($x, '//table'))->toHaveCount(1, 'no table for options '.json_encode($options));
    expect(nodes($x, '//table/caption'))->toHaveCount(1);
    expect(nodes($x, '//table//td'))->toHaveCount(20);
    // Inside the figure, not merely somewhere in the output.
    expect(nodes($x, '//figure//table'))->toHaveCount(1);
})->with([
    'default' => [[]],
    'open' => [['table' => 'open']],
    'collapsed' => [['table' => 'collapsed']],
    'none string' => [['table' => 'none']],
    'hidden string' => [['table' => 'hidden']],
    'false' => [['table' => false]],
    'null' => [['table' => null]],
    'zero' => [['table' => 0]],
    'empty string' => [['table' => '']],
    'array' => [['table' => []]],
    'show_table false' => [['show_table' => false]],
    'data_table false' => [['data_table' => false]],
    'line' => [['type' => 'line']],
    'grouped' => [['type' => 'grouped_bar']],
    'tiny' => [['width' => 10, 'height' => 10]],
    'custom summary' => [['summary' => 'Up and to the right.']],
]);

it('names the data table region so it differs from the table rendered on its own', function () {
    $x = dom((new ChartRenderer)->render(fiveSeries(), ['type' => 'bar', 'id' => 'r']));

    $region = nodes($x, "//*[@role='region']")[0];
    $ids = explode(' ', $region->getAttribute('aria-labelledby'));
    $name = implode(' ', array_map(fn ($id) => trim(nodes($x, "//*[@id='$id']")[0]->textContent), $ids));

    expect($name)->toBe('Data table: Enrolment by campus');
});

it('collapses the data table only when asked and leaves it expanded by default', function () {
    $open = dom((new ChartRenderer)->render(fiveSeries(), ['type' => 'bar']));
    expect(nodes($open, '//details[@open]'))->toHaveCount(1);

    $collapsed = dom((new ChartRenderer)->render(fiveSeries(), ['type' => 'bar', 'table' => 'collapsed']));
    expect(nodes($collapsed, '//details'))->toHaveCount(1);
    expect(nodes($collapsed, '//details[@open]'))->toHaveCount(0);
    expect(nodes($collapsed, '//details//table'))->toHaveCount(1);
});

/**
 * Discriminating test 6: zero JavaScript. No script element and no event
 * handler attribute anywhere in the output, for every chart type.
 */
it('emits no script and no event handler attributes', function (string $type) {
    $html = (new ChartRenderer)->render(fiveSeries(), ['type' => $type]);

    $x = dom($html);

    expect(nodes($x, '//script'))->toHaveCount(0);
    expect(nodes($x, '//*[@*[starts-with(name(), "on")]]'))->toHaveCount(0);
    expect(nodes($x, '//*[@href[starts-with(normalize-space(.), "javascript:")]]'))->toHaveCount(0);
    expect(nodes($x, '//iframe | //object | //embed'))->toHaveCount(0);
})->with(['bar', 'grouped_bar', 'line']);

it('names and describes the SVG through a title and a desc', function () {
    $html = (new ChartRenderer)->render(fiveSeries(), ['type' => 'bar', 'id' => 'n', 'summary' => 'Online enrolment grew every year.']);

    $x = dom($html);

    $svg = nodes($x, "//svg[@role='img']");
    expect($svg)->toHaveCount(1);

    $titleId = $svg[0]->getAttribute('aria-labelledby');
    $descId = $svg[0]->getAttribute('aria-describedby');

    $title = nodes($x, "//svg/title[@id='$titleId']");
    expect($title)->toHaveCount(1);
    expect(trim($title[0]->textContent))->toBe('Enrolment by campus');

    $desc = nodes($x, "//svg/desc[@id='$descId']");
    expect($desc)->toHaveCount(1);
    expect(trim($desc[0]->textContent))->toBe('Online enrolment grew every year.');

    // The title and desc are the first children, so a reader meets them first.
    expect($svg[0]->firstElementChild->nodeName)->toBe('title');
});

it('writes a one-sentence summary of the trend when none is given', function () {
    $html = (new ChartRenderer)->render(fiveSeries(), ['type' => 'line', 'id' => 'd']);

    $x = dom($html);

    $desc = trim(nodes($x, '//svg/desc')[0]->textContent);
    expect($desc)->not->toBe('');
    expect(substr_count($desc, '.'))->toBe(1);
    expect($desc)->toEndWith('.');
    // Names the extremes, which is what a summary of a chart is for.
    expect($desc)->toContain('140');
    expect($desc)->toContain('20');
});

it('draws text as text elements, never as paths', function () {
    $html = (new ChartRenderer)->render(fiveSeries(), ['type' => 'bar', 'id' => 'x']);

    $x = dom($html);

    // Every category label and every series name is a <text> node.
    foreach (['2021', '2022', '2023', '2024', 'North', 'South', 'East', 'West', 'Online'] as $label) {
        expect(nodes($x, "//svg//text[normalize-space(.)='$label']"))->not->toBeEmpty("'$label' is not a <text> element");
    }

    // The only paths are lines and markers, which carry a class saying so.
    foreach (nodes($x, '//svg//path') as $path) {
        expect($path->getAttribute('class'))->toMatch('/furrow-(line|marker|axis|pattern)/');
    }
});

it('paints with currentColor so forced colours and print are honoured', function () {
    $html = (new ChartRenderer)->render(fiveSeries(), ['type' => 'line', 'id' => 'f']);

    expect($html)->toContain('currentColor');
    expect($html)->toContain('forced-colors: active');
    // Every <text> is currentColor.
    foreach (nodes(dom($html), '//svg//text') as $text) {
        expect($text->getAttribute('fill'))->toBe('currentColor');
    }
});

it('has no tooltips and no title on individual marks', function () {
    $html = (new ChartRenderer)->render(fiveSeries(), ['type' => 'bar']);

    $x = dom($html);

    expect(nodes($x, '//svg//rect/title | //svg//path/title | //svg//g/title'))->toHaveCount(0);
    expect(nodes($x, '//svg//*[@title]'))->toHaveCount(0);
});

it('refuses a chart type it does not have', function () {
    (new ChartRenderer)->render(fiveSeries(), ['type' => 'pie']);
})->throws(UnknownChartType::class);

it('refuses to render with a palette that fails contrast', function () {
    (new ChartRenderer)->render(fiveSeries(), ['type' => 'bar', 'palette' => new Palette('#ffffff', ['#03a8b1'])]);
})->throws(PaletteFails::class);

it('refuses a dataset without a caption', function () {
    $data = fiveSeries();
    $data['caption'] = '   ';

    (new ChartRenderer)->render($data, ['type' => 'bar']);
})->throws(MissingCaption::class);

it('renders a single-series bar chart with direct value labels', function () {
    $html = (new ChartRenderer)->render(table(
        'Apples per year',
        ['Year', 'Apples'],
        [['Year', 'Apples'], ['2023', 12], ['2024', 15]],
        headerRows: [0],
        headerColumns: [0],
    ), ['type' => 'bar', 'id' => 'b']);

    $x = dom($html);

    expect(nodes($x, "//svg//rect[contains(concat(' ', normalize-space(@class), ' '), ' furrow-bar ')]"))->toHaveCount(2);
    expect(nodes($x, "//svg//text[@class='furrow-value'][normalize-space(.)='12']"))->toHaveCount(1);
    expect(nodes($x, "//svg//text[@class='furrow-value'][normalize-space(.)='15']"))->toHaveCount(1);
});

it('copes with an empty dataset by rendering the table and an empty plot', function () {
    $html = (new ChartRenderer)->render(table('Nothing yet', ['Year', 'Value'], [['Year', 'Value']], headerRows: [0]), ['type' => 'line']);

    $x = dom($html);

    expect(nodes($x, '//svg'))->toHaveCount(1);
    expect(nodes($x, '//table/caption'))->toHaveCount(1);
});

it('cycles patterns and markers when there are more series than shapes', function () {
    $columns = array_merge(['Year'], array_map(fn ($i) => "S$i", range(1, 12)));
    $rows = [$columns, array_merge(['2024'], range(1, 12))];

    $html = (new ChartRenderer)->render(table('Twelve series', $columns, $rows, headerRows: [0], headerColumns: [0]), [
        'type' => 'grouped_bar',
        'palette' => new Palette('#ffffff', ['#000000', '#00ab39']),
    ]);

    $x = dom($html);

    expect(nodes($x, "//svg//rect[contains(concat(' ', normalize-space(@class), ' '), ' furrow-bar ')]"))->toHaveCount(12);
    expect(nodes($x, '//svg//defs//pattern'))->toHaveCount(12);
});
