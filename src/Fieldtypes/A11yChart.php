<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Fieldtypes;

use Bpmore\A11yDataTablesCharts\Chart\ChartRenderer;
use Bpmore\A11yDataTablesCharts\Dataset\Dataset;
use Bpmore\A11yDataTablesCharts\Rules\ValidChart;
use Bpmore\A11yDataTablesCharts\Statamic\Normalise;
use Bpmore\A11yDataTablesCharts\Statamic\Palettes;
use Bpmore\A11yDataTablesCharts\Statamic\Rendered;
use Bpmore\A11yDataTablesCharts\Support\Html;
use InvalidArgumentException;
use Statamic\Fields\Fieldtype;

/**
 * A chart drawn from a table on the same entry, or from data of its own.
 *
 * Referencing a table field is the default, because one dataset with two
 * renderings cannot drift. The editor is the Vue component registered as
 * `a11y_chart-fieldtype`; it lists the table fields on the same form.
 *
 * The chart is rendered at augmentation, from the table's value at that
 * moment, so it follows the table. When the table is gone, or the palette
 * cannot be used, what renders is a visible notice, never an exception
 * and never a silent blank.
 */
final class A11yChart extends Fieldtype
{
    public const HANDLE = 'a11y_chart';

    protected static $handle = self::HANDLE;

    protected static $title = 'A11y data chart';

    protected $categories = ['structured', 'media'];

    protected $icon = 'chart';

    protected $keywords = ['a11y', 'accessible', 'chart', 'graph', 'bar', 'line', 'svg', 'accessible'];

    protected $selectableInForms = false;

    protected function configFieldItems(): array
    {
        return [
            [
                'display' => __('Data table'),
                'fields' => [
                    'data_table' => [
                        'display' => __('Data table beneath the chart'),
                        'instructions' => __('Every chart renders its data table. Open shows it expanded; collapsed shows only a summary line until the reader opens it. Collapsed tables are not printed by most browsers.'),
                        'type' => 'select',
                        'options' => [
                            'open' => __('Open (default from config)'),
                            'collapsed' => __('Collapsed'),
                        ],
                        'clearable' => true,
                        'width' => 50,
                    ],
                ],
            ],
            [
                'display' => __('Palette'),
                'instructions' => __('Leave blank to use the site palette from config/statamic-a11y-data-tables-charts.php. Every series colour must reach 3:1 against the background (WCAG 1.4.11); a palette that does not will not save.'),
                'fields' => [
                    'palette_background' => [
                        'display' => __('Background'),
                        'instructions' => __('The colour the chart sits on, as a hex value like #ffffff. Contrast is checked against this.'),
                        'type' => 'text',
                        'width' => 50,
                        'validate' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
                    ],
                    'palette_series' => [
                        'display' => __('Series colours'),
                        'instructions' => __('One hex colour per series, in order. Series are also told apart by pattern, dash and marker, so colour is never the only difference.'),
                        'type' => 'list',
                        'width' => 50,
                        'validate' => ['nullable', 'new \\Bpmore\\A11yDataTablesCharts\\Rules\\PaletteContrast'],
                    ],
                ],
            ],
        ];
    }

    public function preProcess($data): array
    {
        return Normalise::chart($data);
    }

    public function process($data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        $chart = Normalise::chart($data);

        if ($chart['source'] === 'field' && $chart['table'] === null) {
            return null;
        }
        if ($chart['source'] === 'inline' && Normalise::tableIsEmpty($chart['data'] ?? [])) {
            return null;
        }

        return $chart;
    }

    public function preProcessIndex($data): ?string
    {
        if (! is_array($data)) {
            return null;
        }

        $chart = Normalise::chart($data);

        return str_replace('_', ' ', $chart['type']).' chart'.($chart['source'] === 'field' ? ' of '.$chart['table'] : ': '.($chart['data']['caption'] ?? ''));
    }

    public function rules(): array
    {
        return [ValidChart::forPalette(fn () => Palettes::for((array) $this->field()?->config()))];
    }

    public function preload(): array
    {
        return [
            'types' => ChartRenderer::TYPES,
            'tableFieldtype' => A11yTable::HANDLE,
        ];
    }

    /**
     * The chart markup, or a visible notice saying why there is none.
     */
    public function augment($value): ?Rendered
    {
        if (! is_array($value)) {
            return null;
        }

        $chart = Normalise::chart($value);
        $id = $this->elementId();

        $data = $chart['source'] === 'inline' ? $chart['data'] : $this->referencedTable($chart['table']);

        if ($data === null || ! is_array($data) || Normalise::tableIsEmpty(Normalise::table($data))) {
            return $this->notice($id, $chart, sprintf(
                'This chart cannot be drawn: its data table (the "%s" field) is no longer on this entry.',
                (string) $chart['table'],
            ));
        }

        try {
            $dataset = Dataset::fromArray(Normalise::table($data));
            $html = (new ChartRenderer)->render($dataset, [
                'type' => $chart['type'],
                'id' => $id,
                'palette' => Palettes::for((array) $this->field()?->config()),
                'summary' => $chart['summary'],
                'table' => $this->config('data_table') ?: config('statamic-a11y-data-tables-charts.data_table', 'open'),
            ]);
        } catch (InvalidArgumentException $e) {
            // A missing caption, a palette that fails, a type that is not
            // one of ours: shown, not thrown, and not blank.
            return $this->notice($id, $chart, 'This chart cannot be drawn: '.$e->getMessage());
        }

        return Rendered::make($html, [
            'type' => $chart['type'],
            'source' => $chart['source'],
            'table' => $chart['table'],
            'summary' => $chart['summary'],
        ] + $dataset->toArray());
    }

    /** The raw stored value of the table field this chart names, from the entry being augmented. */
    private function referencedTable(?string $handle): mixed
    {
        if ($handle === null) {
            return null;
        }

        $parent = $this->field()?->parent();

        // Inside a set, the sibling values are on the parent field's value
        // rather than the entry; the entry is the common case.
        if (is_object($parent) && method_exists($parent, 'get')) {
            return $parent->get($handle);
        }

        return is_array($parent) ? ($parent[$handle] ?? null) : null;
    }

    /** @param  array<string, mixed>  $chart */
    private function notice(string $id, array $chart, string $message): Rendered
    {
        $html = '<p'.Html::attrs(['class' => 'a11y-data-chart a11y-data-chart--missing', 'id' => $id]).'>'.Html::e($message).'</p>';

        return Rendered::make($html, ['type' => $chart['type'], 'source' => $chart['source'], 'table' => $chart['table'], 'summary' => $chart['summary'], 'missing' => true]);
    }

    private function elementId(): string
    {
        $parent = $this->field()?->parent();
        $owner = is_object($parent) && method_exists($parent, 'id') ? (string) $parent->id() : '';

        return 'a11y-data-'.($this->field()?->handle() ?? 'chart').($owner !== '' ? '-'.substr(sha1($owner), 0, 6) : '');
    }
}
