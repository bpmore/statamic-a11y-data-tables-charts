<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Tags;

use Bpmore\A11yDataTablesCharts\Chart\ChartRenderer;
use Bpmore\A11yDataTablesCharts\Statamic\Normalise;
use Bpmore\A11yDataTablesCharts\Statamic\Palettes;
use Bpmore\A11yDataTablesCharts\Statamic\Rendered;
use Bpmore\A11yDataTablesCharts\Table\TableRenderer;
use Illuminate\Contracts\Support\Arrayable;
use Statamic\Tags\Tags;

/**
 * `{{ a11y_data:styles }}`: the addon's stylesheet, inline, once per page.
 * `{{ a11y_data:table field="handle" }}` and `{{ a11y_data:chart field="handle" }}`:
 * a field of the current context, rendered. Either also takes `:data` with
 * a dataset: an array a template built, or a table field's value, so one
 * table can be drawn again as a different chart. `caption` replaces the
 * dataset's caption for that rendering, which a page showing the same data
 * twice needs, because two regions with one name is a finding.
 *
 * A field renders itself when printed (`{{ my_table }}`), so the tag is
 * for the cases where a template wants to be explicit about it.
 */
final class A11yData extends Tags
{
    public function styles(): string
    {
        $css = (string) file_get_contents(__DIR__.'/../../resources/css/a11y-data.css');

        return '<style>'.$css.'</style>';
    }

    public function table(): string
    {
        $data = $this->dataset();

        if ($data !== null) {
            return (new TableRenderer)->render($data, ['id' => $this->params->get('id'), 'class' => $this->params->get('class')]);
        }

        return $this->fromField();
    }

    public function chart(): string
    {
        $data = $this->dataset();

        if ($data !== null) {
            return (new ChartRenderer)->render($data, [
                'type' => (string) $this->params->get('type', 'bar'),
                'id' => $this->params->get('id'),
                'class' => $this->params->get('class'),
                'summary' => $this->params->get('summary'),
                'table' => $this->params->get('table', config('statamic-a11y-data-tables-charts.data_table', 'open')),
                'palette' => Palettes::configured(),
            ]);
        }

        return $this->fromField();
    }

    /**
     * The `data` parameter as a normalised table, or null when there is none.
     * A rendered field arrives as its augmented value; its parts are the
     * dataset.
     *
     * @return array<string, mixed>|null
     */
    private function dataset(): ?array
    {
        $data = $this->params->get('data');
        $data = $data instanceof \Statamic\Fields\Value ? $data->value() : $data;

        if ($data instanceof Arrayable) {
            $data = $data->toArray();
        }

        if (! is_array($data) || $data === []) {
            return null;
        }

        $table = Normalise::table($data);

        $caption = $this->params->get('caption');
        if (is_string($caption) && trim($caption) !== '') {
            $table['caption'] = trim($caption);
        }

        return $table;
    }

    private function fromField(): string
    {
        $handle = (string) $this->params->get('field', '');

        if ($handle === '') {
            return '';
        }

        $value = $this->context->get($handle);
        $value = $value instanceof \Statamic\Fields\Value ? $value->value() : $value;

        return $value instanceof Rendered ? $value->html() : (is_string($value) ? $value : '');
    }
}
