<?php

declare(strict_types=1);

use Bpmore\Furrow\Dataset\MissingCaption;
use Bpmore\Furrow\Table\TableRenderer;

/**
 * Discriminating test 1: complex header pairing.
 *
 * Two header rows. "Sales" spans the two number columns in the first row,
 * and the second row names each. Every data cell must name, in its headers
 * attribute, the exact ids of every header that applies to it: the spanning
 * header, the column header beneath it, and the row header to its left.
 * Asserted as pairs, not as "an attribute exists".
 */
it('pairs every data cell with its spanning and direct headers by id', function () {
    $html = (new TableRenderer)->render([
        'caption' => 'Sales by region',
        'columns' => [
            ['label' => 'Region', 'type' => 'text', 'header' => true],
            ['label' => 'Q1', 'type' => 'number', 'header' => false],
            ['label' => 'Q2', 'type' => 'number', 'header' => false],
        ],
        'rows' => [
            ['header' => true, 'cells' => [['value' => ''], ['value' => 'Sales', 'colspan' => 2]]],
            ['header' => true, 'cells' => [['value' => 'Region'], ['value' => 'Q1'], ['value' => 'Q2']]],
            ['header' => false, 'cells' => [['value' => 'North'], ['value' => 10], ['value' => 20]]],
            ['header' => false, 'cells' => [['value' => 'South'], ['value' => 30], ['value' => 40]]],
        ],
    ], ['id' => 't']);

    $x = dom($html);

    $idOf = function (string $text) use ($x): string {
        $th = nodes($x, "//th[normalize-space(.)='$text']");
        expect($th)->toHaveCount(1, "expected exactly one <th> reading '$text'");

        $id = $th[0]->getAttribute('id');
        expect($id)->not->toBe('', "<th> '$text' has no id");

        return $id;
    };

    $sales = $idOf('Sales');
    $q1 = $idOf('Q1');
    $q2 = $idOf('Q2');
    $north = $idOf('North');
    $south = $idOf('South');
    $region = $idOf('Region');

    // The six ids are distinct.
    expect(array_unique([$sales, $q1, $q2, $north, $south, $region]))->toHaveCount(6);

    $headersOf = function (string $text) use ($x): array {
        $cells = nodes($x, "//td[normalize-space(.)='$text']");
        expect($cells)->toHaveCount(1, "expected exactly one <td> reading '$text'");

        $headers = preg_split('/\s+/', trim($cells[0]->getAttribute('headers'))) ?: [];
        sort($headers);

        return $headers;
    };

    $sorted = fn (array $ids) => (function () use ($ids) { sort($ids); return $ids; })();

    expect($headersOf('10'))->toBe($sorted([$sales, $q1, $north]));
    expect($headersOf('20'))->toBe($sorted([$sales, $q2, $north]));
    expect($headersOf('30'))->toBe($sorted([$sales, $q1, $south]));
    expect($headersOf('40'))->toBe($sorted([$sales, $q2, $south]));

    // The second-row headers sit under the spanning one and say so.
    expect($sorted(preg_split('/\s+/', trim(nodes($x, "//th[@id='$q1']")[0]->getAttribute('headers')))))->toBe([$sales]);
    expect($sorted(preg_split('/\s+/', trim(nodes($x, "//th[@id='$q2']")[0]->getAttribute('headers')))))->toBe([$sales]);

    // And the ids every headers attribute names all resolve to a <th> in
    // this table. A dangling reference is silently ignored by browsers, so
    // this is the assertion that catches a typo in the id scheme.
    $known = array_map(fn ($th) => $th->getAttribute('id'), nodes($x, '//th[@id]'));
    foreach (nodes($x, '//*[@headers]') as $cell) {
        foreach (preg_split('/\s+/', trim($cell->getAttribute('headers'))) as $ref) {
            expect(in_array($ref, $known, true))->toBeTrue("headers references '$ref', which no <th> has as its id");
        }
    }
});

/**
 * Discriminating test 2: scope on a simple table with a header row and a
 * header column. Column headers are scope="col", row headers scope="row",
 * and the empty corner is a <td>, not an empty <th>.
 */
it('uses scope on a simple row-header table and makes the empty corner a td', function () {
    $html = (new TableRenderer)->render(table(
        'Rainfall by month',
        ['', 'Rainfall (mm)', 'Days'],
        [
            ['', 'Rainfall (mm)', 'Days'],
            ['January', 80, 12],
            ['February', 65, 10],
        ],
        headerRows: [0],
        headerColumns: [0],
    ));

    $x = dom($html);

    expect(nodes($x, "//thead/tr/th[@scope='col']"))->toHaveCount(2);
    expect(nodes($x, "//tbody/tr/th[@scope='row']"))->toHaveCount(2);
    expect(nodes($x, "//tbody/tr/th[@scope='row'][normalize-space(.)='January']"))->toHaveCount(1);

    // The corner: first cell of the first header row, empty, is a td.
    $corner = nodes($x, '//thead/tr[1]/*[1]');
    expect($corner)->toHaveCount(1);
    expect($corner[0]->nodeName)->toBe('td');
    expect(nodes($x, "//th[normalize-space(.)='']"))->toHaveCount(0, 'an empty <th> was rendered');

    // A simple table needs no id/headers plumbing, and gets none.
    expect(nodes($x, '//*[@headers]'))->toHaveCount(0);
});

it('does not duplicate native semantics with ARIA roles on the table', function () {
    $html = (new TableRenderer)->render(table('Plain', ['A', 'B'], [['A', 'B'], [1, 2]], headerRows: [0]));

    $x = dom($html);

    expect(nodes($x, '//table[@role]'))->toHaveCount(0);
    expect(nodes($x, '//tr[@role] | //td[@role] | //th[@role] | //caption[@role]'))->toHaveCount(0);
});

it('wraps the table in a keyboard-reachable scroll region named by the caption', function () {
    $html = (new TableRenderer)->render(table('Named caption', ['A', 'B'], [['A', 'B'], [1, 2]], headerRows: [0]), ['id' => 'w']);

    $x = dom($html);

    $region = nodes($x, "//*[@role='region']");
    expect($region)->toHaveCount(1);
    expect($region[0]->getAttribute('tabindex'))->toBe('0');

    $labelledBy = $region[0]->getAttribute('aria-labelledby');
    expect($labelledBy)->not->toBe('');

    $caption = nodes($x, "//caption[@id='$labelledBy']");
    expect($caption)->toHaveCount(1);
    expect(trim($caption[0]->textContent))->toBe('Named caption');

    // The table is inside the region, not beside it.
    expect(nodes($x, "//*[@role='region']//table"))->toHaveCount(1);
});

it('can put another id before the caption in the region name', function () {
    $html = (new TableRenderer)->render(table('Named', ['A'], [['A'], [1]], headerRows: [0]), ['id' => 'n', 'labelledby' => 'prefix-id']);

    $region = nodes(dom($html), "//*[@role='region']")[0];
    expect($region->getAttribute('aria-labelledby'))->toBe('prefix-id n-caption');
});

it('aligns by column type through a class and never a style attribute', function () {
    $html = (new TableRenderer)->render(table('Typed', ['Name', 'Count'], [['Name', 'Count'], ['a', 1]], headerRows: [0]));

    $x = dom($html);

    expect(nodes($x, '//*[@style]'))->toHaveCount(0);
    expect(nodes($x, "//tbody//td[contains(concat(' ', normalize-space(@class), ' '), ' furrow-cell--number ')]"))->toHaveCount(1);
    expect(nodes($x, "//tbody//td[contains(concat(' ', normalize-space(@class), ' '), ' furrow-cell--text ')]"))->toHaveCount(1);
});

it('puts abbr on a header cell that has one', function () {
    $html = (new TableRenderer)->render(table(
        'Abbreviated',
        ['Name', 'Population'],
        [['Name', ['value' => 'Population (2020 census estimate)', 'abbr' => 'Pop.']], ['a', 1]],
        headerRows: [0],
    ));

    $x = dom($html);

    expect(nodes($x, "//th[@abbr='Pop.']"))->toHaveCount(1);
    expect(nodes($x, '//td[@abbr]'))->toHaveCount(0);
});

it('refuses to render a table without a caption', function () {
    (new TableRenderer)->render(['caption' => '', 'columns' => [], 'rows' => [['header' => false, 'cells' => [['value' => 1]]]]]);
})->throws(MissingCaption::class);

it('escapes cell content', function () {
    $html = (new TableRenderer)->render(table('Escaped', ['A'], [['<b>bold</b>']]));

    expect($html)->toContain('&lt;b&gt;bold&lt;/b&gt;');
    expect($html)->not->toContain('<b>bold</b>');
});

it('handles rowspan when pairing headers', function () {
    // A row header spanning two rows: both rows' data cells name it.
    $html = (new TableRenderer)->render([
        'caption' => 'Spanning row header',
        'columns' => [
            ['label' => 'Group', 'type' => 'text', 'header' => true],
            ['label' => 'Item', 'type' => 'text', 'header' => true],
            ['label' => 'Value', 'type' => 'number', 'header' => false],
        ],
        'rows' => [
            ['header' => true, 'cells' => [['value' => 'Group'], ['value' => 'Item'], ['value' => 'Value']]],
            ['header' => false, 'cells' => [['value' => 'Fruit', 'rowspan' => 2], ['value' => 'Apple'], ['value' => 1]]],
            ['header' => false, 'cells' => [['value' => 'Pear'], ['value' => 2]]],
        ],
    ], ['id' => 's']);

    $x = dom($html);

    $fruit = nodes($x, "//th[normalize-space(.)='Fruit']")[0]->getAttribute('id');
    $apple = nodes($x, "//th[normalize-space(.)='Apple']")[0]->getAttribute('id');
    $pear = nodes($x, "//th[normalize-space(.)='Pear']")[0]->getAttribute('id');
    $value = nodes($x, "//th[normalize-space(.)='Value']")[0]->getAttribute('id');

    $headersOf = fn (string $text) => (function () use ($x, $text) {
        $h = preg_split('/\s+/', trim(nodes($x, "//td[normalize-space(.)='$text']")[0]->getAttribute('headers')));
        sort($h);

        return $h;
    })();
    $sorted = fn (array $ids) => (function () use ($ids) { sort($ids); return $ids; })();

    expect($headersOf('1'))->toBe($sorted([$fruit, $apple, $value]));
    expect($headersOf('2'))->toBe($sorted([$fruit, $pear, $value]));
});
