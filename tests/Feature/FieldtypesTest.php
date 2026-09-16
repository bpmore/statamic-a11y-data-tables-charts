<?php

declare(strict_types=1);

use Bpmore\A11yDataTablesCharts\Fieldtypes\A11yChart;
use Bpmore\A11yDataTablesCharts\Fieldtypes\A11yTable;
use Bpmore\A11yDataTablesCharts\Statamic\Rendered;
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
