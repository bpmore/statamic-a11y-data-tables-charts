<?php

declare(strict_types=1);

use Bpmore\A11yDataTablesCharts\Dataset\Limits;
use Bpmore\A11yDataTablesCharts\Fieldtypes\A11yChart;
use Bpmore\A11yDataTablesCharts\Fieldtypes\A11yTable;
use Bpmore\A11yDataTablesCharts\Statamic\Rendered;
use Illuminate\Support\Facades\Log;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Fields\FieldtypeRepository;

/** A collection whose entries have a table field and a chart that draws it. */
function makeCollection(array $chartConfig = []): void
{
    // A blueprint read once is remembered for the request; a test that
    // saves a second one must forget the first.
    \Statamic\Facades\Blink::flush();

    Collection::make('reports')->routes('/reports/{slug}')->template('report')->save();

    Blueprint::make('report')->setNamespace('collections.reports')->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'figures', 'field' => ['type' => 'a11y_table', 'display' => 'Figures']],
            ['handle' => 'picture', 'field' => ['type' => 'a11y_chart', 'display' => 'Picture'] + $chartConfig],
        ]]]]],
    ])->save();
}

function figures(): array
{
    return table('Apples per year', ['Year', 'Apples'], [['Year', 'Apples'], ['2023', 12], ['2024', 15]], headerRows: [0], headerColumns: [0]);
}

/** Validation of an entry's values, as saving it from the control panel would run. */
function validationErrors(array $values): array
{
    $fields = Collection::find('reports')->entryBlueprint()->fields()->addValues($values);

    try {
        $fields->validator()->validate();
    } catch (\Illuminate\Validation\ValidationException $e) {
        return $e->errors();
    }

    return [];
}

it('registers both fieldtypes under their handles', function () {
    expect(app(FieldtypeRepository::class)->find('a11y_table'))->toBeInstanceOf(A11yTable::class);
    expect(app(FieldtypeRepository::class)->find('a11y_chart'))->toBeInstanceOf(A11yChart::class);
});

it('refuses a table without a caption and there is no setting that allows one', function () {
    makeCollection();

    $noCaption = figures();
    $noCaption['caption'] = '';

    $errors = validationErrors(['title' => 'x', 'figures' => $noCaption]);
    expect($errors)->toHaveKey('figures');
    expect($errors['figures'][0])->toContain('caption');

    expect(validationErrors(['title' => 'x', 'figures' => figures()]))->not->toHaveKey('figures');

    // The fieldtype has no config field that could loosen this.
    $config = collect((new A11yTable)->configFields()->all())->keys()->all();
    expect($config)->not->toContain('require_caption');
    foreach ($config as $handle) {
        expect($handle)->not->toContain('caption');
    }
});

it('refuses a chart whose table field is not being saved with a caption', function () {
    makeCollection();

    $chart = ['type' => 'bar', 'source' => 'field', 'table' => 'figures'];

    $errors = validationErrors(['title' => 'x', 'picture' => $chart]);
    expect($errors)->toHaveKey('picture');
    expect($errors['picture'][0])->toContain('figures');

    $errors = validationErrors(['title' => 'x', 'figures' => figures(), 'picture' => $chart]);
    expect($errors)->not->toHaveKey('picture');

    $errors = validationErrors(['title' => 'x', 'figures' => figures(), 'picture' => ['type' => 'pie', 'source' => 'field', 'table' => 'figures']]);
    expect($errors['picture'][0])->toContain('pie');
});

it('refuses to save a chart when the site palette fails contrast', function () {
    makeCollection();
    config()->set('statamic-a11y-data-tables-charts.palette', ['background' => '#ffffff', 'series' => ['#03a8b1']]);

    $errors = validationErrors(['title' => 'x', 'figures' => figures(), 'picture' => ['type' => 'bar', 'source' => 'field', 'table' => 'figures']]);

    expect($errors)->toHaveKey('picture');
    expect($errors['picture'][0])->toContain('contrast');
});

/**
 * Discriminating test 7: the chart follows the table it references, and
 * when the table is gone, the page shows a notice rather than an
 * exception or a blank.
 */
it('draws the referenced table and follows it when it changes', function () {
    makeCollection();

    $entry = Entry::make()->collection('reports')->slug('one')->data([
        'title' => 'One',
        'figures' => figures(),
        'picture' => ['type' => 'bar', 'source' => 'field', 'table' => 'figures'],
    ]);
    $entry->save();

    $first = (string) Entry::find($entry->id())->augmentedValue('picture')->value();
    expect($first)->toContain('<svg');
    expect($first)->toContain('Apples per year');
    expect($first)->toContain('>15<');
    expect($first)->not->toContain('>99<');

    $changed = figures();
    $changed['rows'][2]['cells'][1]['value'] = 99;
    $entry->set('figures', $changed)->save();

    $second = (string) Entry::find($entry->id())->augmentedValue('picture')->value();
    expect($second)->toContain('>99<');
    expect($second)->not->toContain('>15<');
});

it('degrades visibly when the referenced table field is removed', function () {
    makeCollection();

    $entry = Entry::make()->collection('reports')->slug('two')->data([
        'title' => 'Two',
        'figures' => figures(),
        'picture' => ['type' => 'bar', 'source' => 'field', 'table' => 'figures'],
    ]);
    $entry->save();

    $entry->remove('figures')->save();

    $value = Entry::find($entry->id())->augmentedValue('picture')->value();
    expect($value)->toBeInstanceOf(Rendered::class);

    $html = (string) $value;
    expect($html)->not->toContain('<svg');
    expect($html)->toContain('figures');
    expect($html)->toContain('cannot be drawn');
    // Visible: a paragraph of text, not a comment or a hidden element.
    expect($html)->toStartWith('<p');
    expect($html)->not->toContain('hidden');
    expect($value['missing'])->toBeTrue();
});

it('renders a table field as its markup and exposes its parts', function () {
    makeCollection();

    $entry = Entry::make()->collection('reports')->slug('three')->data(['title' => 'Three', 'figures' => figures()]);
    $entry->save();

    $value = Entry::find($entry->id())->augmentedValue('figures')->value();

    expect((string) $value)->toContain('<table');
    expect((string) $value)->toContain('<caption');
    expect($value['caption'])->toBe('Apples per year');
    expect($value['rows'])->toHaveCount(3);
    expect($value['html'])->toBe((string) $value);
});

it('renders through Antlers as a string, as a pair, and through the tag', function () {
    makeCollection();

    $entry = Entry::make()->collection('reports')->slug('four')->data([
        'title' => 'Four',
        'figures' => figures(),
        'picture' => ['type' => 'line', 'source' => 'field', 'table' => 'figures'],
    ]);
    $entry->save();

    // A real template on a real request. Antlers parsed from a string is
    // treated as user content and refuses every addon tag, so a string
    // parse could not show the tag working.
    $this->withFakeViews();
    $this->viewShouldReturnRaw('layout', '{{ template_content }}');
    $this->viewShouldReturnRaw('report', '{{ figures }}|{{ figures:caption }}|{{ picture }}|{{ a11y_data:chart field="picture" }}|{{ a11y_data:table field="figures" }}|{{ a11y_data:styles }}|{{ a11y_data:chart :data="figures" type="bar" caption="The same apples, as bars" }}');

    $html = $this->get('/reports/four')->assertOk()->getContent();

    [$table, $caption, $chart, $tagChart, $tagTable, $styles, $again] = explode('|', $html);

    // A table field drawn again by the tag, as another type with its own caption.
    expect($again)->toContain('a11y-data-chart--bar');
    expect($again)->toContain('<title id="a11y-data-chart-');
    expect($again)->toContain('The same apples, as bars');
    expect($again)->toContain('>15<');

    expect($table)->toContain('<table');
    expect($caption)->toBe('Apples per year');
    expect($chart)->toContain('<svg');
    expect($chart)->toContain('a11y-data-chart--line');
    expect($tagChart)->toBe($chart);
    expect($tagTable)->toBe($table);
    expect(trim($styles))->toStartWith('<style>');
    expect($styles)->toContain('.a11y-data-table');
});

it('uses a field palette override and refuses the entry when it fails', function () {
    makeCollection(['palette_background' => '#ffffff', 'palette_series' => ['#00ab39']]);

    $entry = Entry::make()->collection('reports')->slug('five')->data([
        'title' => 'Five',
        'figures' => figures(),
        'picture' => ['type' => 'bar', 'source' => 'field', 'table' => 'figures'],
    ]);
    $entry->save();

    expect((string) Entry::find($entry->id())->augmentedValue('picture')->value())->toContain('#00ab39');

    makeCollection(['palette_background' => '#ffffff', 'palette_series' => ['#03a8b1']]);

    $errors = validationErrors(['title' => 'x', 'figures' => figures(), 'picture' => ['type' => 'bar', 'source' => 'field', 'table' => 'figures']]);
    expect($errors['picture'][0])->toContain('contrast');
});

it('refuses a field palette override that fails contrast when the blueprint field is saved', function () {
    $this->actingAs(\Statamic\Facades\User::make()->makeSuper()->save());

    $post = fn (array $values) => $this->postJson(cp_route('fields.update'), [
        'type' => 'a11y_chart',
        'values' => ['handle' => 'picture', 'display' => 'Picture'] + $values,
        'fields' => [],
    ]);

    $post(['palette_background' => '#ffffff', 'palette_series' => ['#03a8b1']])
        ->assertStatus(422)
        ->assertJsonPath('errors.palette_series.0', fn ($m) => str_contains($m, 'contrast'));

    $post(['palette_background' => '#ffffff', 'palette_series' => ['#00ab39']])
        ->assertOk();

    $post(['palette_background' => 'blue', 'palette_series' => ['#00ab39']])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('palette_background');

    // Nothing set: the site palette applies and there is nothing to refuse.
    $post([])->assertOk();
});

it('stores nothing for an empty table and an unset chart', function () {
    expect((new A11yTable)->process(['caption' => '', 'columns' => [], 'rows' => [['header' => true, 'cells' => [['value' => '']]]]]))->toBeNull();
    expect((new A11yChart)->process(['type' => 'bar', 'source' => 'field', 'table' => '']))->toBeNull();
    expect((new A11yChart)->process(['type' => 'bar', 'source' => 'inline', 'data' => ['caption' => '', 'rows' => []]]))->toBeNull();
});

/**
 * Regression: a saved cell with spans in the millions once took every
 * render of the entry down with memory exhaustion. The rule refuses it,
 * with a message, before anything is padded or laid out.
 */
it('refuses a table whose spans, rows or width are past the limits, and quickly', function () {
    makeCollection();

    $spanning = figures();
    $spanning['rows'][1]['cells'][1] = ['value' => 12, 'colspan' => 1_000_000, 'rowspan' => 1_000_000];

    $start = hrtime(true);
    $errors = validationErrors(['title' => 'x', 'figures' => $spanning]);
    expect((hrtime(true) - $start) / 1e9)->toBeLessThan(2.0);
    expect($errors)->toHaveKey('figures');
    expect($errors['figures'][0])->toContain('span');

    $tall = figures();
    $tall['rows'] = array_merge($tall['rows'], array_fill(0, Limits::ROWS, ['header' => false, 'cells' => [['value' => 'y'], ['value' => 1]]]));
    $errors = validationErrors(['title' => 'x', 'figures' => $tall]);
    expect($errors['figures'][0] ?? '')->toContain('rows');

    $wide = figures();
    $wide['rows'][1]['cells'][1] = ['value' => 12, 'colspan' => Limits::COLUMNS];
    $errors = validationErrors(['title' => 'x', 'figures' => $wide]);
    expect($errors['figures'][0] ?? '')->toContain('wider');

    // A table at the limits is not refused, and an ordinary one is untouched.
    $atLimit = figures();
    $atLimit['rows'][1]['cells'][1] = ['value' => 12, 'colspan' => Limits::COLUMNS - 1];
    expect(validationErrors(['title' => 'x', 'figures' => $atLimit]))->not->toHaveKey('figures');
    expect(validationErrors(['title' => 'x', 'figures' => figures()]))->not->toHaveKey('figures');
});

it('refuses inline chart data past the limits', function () {
    makeCollection();

    $data = figures();
    $data['rows'][1]['cells'][1] = ['value' => 12, 'rowspan' => 1_000_000];
    $errors = validationErrors(['title' => 'x', 'picture' => ['type' => 'bar', 'source' => 'inline', 'data' => $data]]);
    expect($errors)->toHaveKey('picture');
    expect($errors['picture'][0])->toContain('span');

    expect(validationErrors(['title' => 'x', 'picture' => ['type' => 'bar', 'source' => 'inline', 'data' => figures()]]))->not->toHaveKey('picture');
});

/**
 * Stored data that never met the rule (written by hand, or through the
 * API) renders with the span clamped rather than exhausting memory, and
 * a chart of a table the grid refuses shows a notice, as any other chart
 * that cannot be drawn does.
 */
it('renders a stored table with outsized spans clamped, and a chart of one too wide as a notice', function () {
    makeCollection();

    // On the last row, so the clamped rowspan reaches past the end and
    // pushes nothing beneath it past the last column.
    $stored = figures();
    $stored['rows'][2]['cells'] = [['value' => '2024', 'colspan' => 1_000_000, 'rowspan' => 1_000_000]];

    $entry = Entry::make()->collection('reports')->slug('big')->data([
        'title' => 'Big',
        'figures' => $stored,
        'picture' => ['type' => 'bar', 'source' => 'field', 'table' => 'figures'],
    ]);
    $entry->save();

    $start = hrtime(true);
    $table = (string) Entry::find($entry->id())->augmentedValue('figures')->value();
    expect((hrtime(true) - $start) / 1e9)->toBeLessThan(2.0);
    expect($table)->toContain('colspan="'.Limits::SPAN.'"');
    expect($table)->not->toContain('1000000');

    $wide = figures();
    $wide['rows'][1]['cells'][1] = ['value' => 12, 'colspan' => Limits::COLUMNS];
    $entry->set('figures', $wide)->save();

    $chart = (string) Entry::find($entry->id())->augmentedValue('picture')->value();
    expect($chart)->toStartWith('<p');
    expect($chart)->toContain('cannot be drawn');
    expect($chart)->toContain('wider than');
});

/**
 * Regression: a stored table past the limits (written to the entry file by
 * hand, so the rule never saw it) made its entry's page a 500, because
 * TableTooLarge left the table's augmentation uncaught while a chart's
 * caught it. The page is served, with a notice in the table's place that
 * says what is wrong and where to fix it, and one line in the log.
 */
it('serves the page of an entry whose stored table is past the limits, with a notice in its place', function () {
    makeCollection();

    $wide = figures();
    $wide['rows'][1]['cells'][1] = ['value' => 12, 'colspan' => Limits::COLUMNS];

    $entry = Entry::make()->collection('reports')->slug('wide')->data([
        'title' => 'Wide',
        'figures' => $wide,
        'picture' => ['type' => 'bar', 'source' => 'field', 'table' => 'figures'],
    ]);
    $entry->save();

    Log::spy();

    $this->withFakeViews();
    $this->viewShouldReturnRaw('layout', '{{ template_content }}');
    $this->viewShouldReturnRaw('report', '{{ figures }}|{{ figures:caption }}|{{ if figures:missing }}missing{{ /if }}|{{ a11y_data:table field="figures" }}|{{ a11y_data:table :data="figures" id="again" }}|{{ picture }}');

    $start = hrtime(true);
    $html = $this->get('/reports/wide')->assertOk()->getContent();
    expect((hrtime(true) - $start) / 1e9)->toBeLessThan(2.0);

    [$table, $caption, $missing, $tagTable, $tagData, $chart] = explode('|', $html);

    // The field, printed: a paragraph, not a table, saying what and where.
    expect($table)->toStartWith('<p');
    expect($table)->toContain('a11y-data-table--missing');
    expect($table)->toContain('This table cannot be displayed');
    expect($table)->toContain('wider than '.Limits::COLUMNS);
    expect($table)->toContain('control panel');
    expect($table)->not->toContain('<table');
    expect($table)->not->toContain('<svg');

    // Its parts are still there by path.
    expect($caption)->toBe('Apples per year');
    expect($missing)->toBe('missing');

    // The tag, from the field and from the data: the same notice.
    expect($tagTable)->toBe($table);
    expect($tagData)->toContain('a11y-data-table--missing');
    expect($tagData)->toContain('id="again"');
    expect($tagData)->toContain('wider than '.Limits::COLUMNS);

    // The chart of it is a notice too, as before.
    expect($chart)->toContain('a11y-data-chart--missing');
    expect($chart)->toContain('cannot be drawn');

    // Three renderings of one problem, one line in the log for the table
    // (the chart's notice is its own line), at warning.
    Log::shouldHaveReceived('warning')
        ->with(\Mockery::pattern('/^A11y data table not rendered: This table cannot be displayed: .*wider than/'), \Mockery::type('array'))
        ->once();
    Log::shouldHaveReceived('warning')->twice();
});

it('shows a notice for a stored table past the limits in augmentation alone, with the caption kept', function () {
    makeCollection();

    $tall = figures();
    $tall['rows'] = array_merge($tall['rows'], array_fill(0, Limits::ROWS, ['header' => false, 'cells' => [['value' => 'y'], ['value' => 1]]]));

    $entry = Entry::make()->collection('reports')->slug('tall')->data(['title' => 'Tall', 'figures' => $tall]);
    $entry->save();

    $start = hrtime(true);
    $value = Entry::find($entry->id())->augmentedValue('figures')->value();
    expect((hrtime(true) - $start) / 1e9)->toBeLessThan(2.0);

    expect($value)->toBeInstanceOf(Rendered::class);
    expect($value->html())->toStartWith('<p');
    expect($value->html())->toContain('The table has '.(Limits::ROWS + 3).' rows');
    expect($value->html())->toContain('at most '.Limits::ROWS.' rows');
    expect($value['missing'])->toBeTrue();
    expect($value['caption'])->toBe('Apples per year');
});
