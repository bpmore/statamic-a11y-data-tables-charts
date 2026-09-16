<?php

declare(strict_types=1);

namespace Bpmore\Furrow\Tags;

use Bpmore\Furrow\Chart\ChartRenderer;
use Bpmore\Furrow\Statamic\Normalise;
use Bpmore\Furrow\Statamic\Palettes;
use Bpmore\Furrow\Statamic\Rendered;
use Bpmore\Furrow\Table\TableRenderer;
use Statamic\Tags\Tags;

/**
 * `{{ furrow:styles }}`: the addon's stylesheet, inline, once per page.
 * `{{ furrow:table field="handle" }}` and `{{ furrow:chart field="handle" }}`:
 * a field of the current context, rendered. Either also takes `:data` with
 * a dataset array, for a template that builds one itself.
 *
 * A field renders itself when printed (`{{ my_table }}`), so the tag is
 * for the cases where a template wants to be explicit about it.
 */
final class Furrow extends Tags
{
    public function styles(): string
    {
        $css = (string) file_get_contents(__DIR__.'/../../resources/css/furrow.css');

        return '<style>'.$css.'</style>';
    }

    public function table(): string
    {
        $data = $this->params->get('data');

        if (is_array($data)) {
            return (new TableRenderer)->render(Normalise::table($data), ['id' => $this->params->get('id'), 'class' => $this->params->get('class')]);
        }

        return $this->fromField();
    }

    public function chart(): string
    {
        $data = $this->params->get('data');

        if (is_array($data)) {
            return (new ChartRenderer)->render(Normalise::table($data), [
                'type' => (string) $this->params->get('type', 'bar'),
                'id' => $this->params->get('id'),
                'class' => $this->params->get('class'),
                'summary' => $this->params->get('summary'),
                'table' => $this->params->get('table', config('statamic-furrow.data_table', 'open')),
                'palette' => Palettes::configured(),
            ]);
        }

        return $this->fromField();
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
