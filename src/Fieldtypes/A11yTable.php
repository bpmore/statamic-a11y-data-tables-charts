<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Fieldtypes;

use Bpmore\A11yDataTablesCharts\Dataset\Dataset;
use Bpmore\A11yDataTablesCharts\Rules\ValidTable;
use Bpmore\A11yDataTablesCharts\Statamic\Normalise;
use Bpmore\A11yDataTablesCharts\Statamic\Rendered;
use Bpmore\A11yDataTablesCharts\Table\TableRenderer;
use Statamic\Fields\Fieldtype;

/**
 * A data table with a caption, header rows and header columns, that
 * renders with its headers correctly associated.
 *
 * Stores the dataset shape the brief fixes and nothing else. The editor
 * is the Vue component registered as `a11y_table-fieldtype`: a grid
 * with a caption input, header toggles, and row and column controls that
 * all work from the keyboard.
 */
final class A11yTable extends Fieldtype
{
    public const HANDLE = 'a11y_table';

    protected static $handle = self::HANDLE;

    protected static $title = 'A11y data table';

    protected $categories = ['structured'];

    protected $icon = 'table';

    protected $keywords = ['a11y', 'accessible', 'table', 'data', 'grid'];

    protected $selectableInForms = false;

    protected function configFieldItems(): array
    {
        return [
            'first_row_header' => [
                'display' => __('Start with a header row'),
                'instructions' => __('A new table begins with its first row marked as headers. Authors can change it.'),
                'type' => 'toggle',
                'default' => true,
                'width' => 50,
            ],
            'first_column_header' => [
                'display' => __('Start with a header column'),
                'instructions' => __('A new table begins with its first column marked as row headers. Authors can change it.'),
                'type' => 'toggle',
                'default' => false,
                'width' => 50,
            ],
        ];
    }

    /** Stored to editor: every key present, so the component binds to a complete shape. */
    public function preProcess($data): array
    {
        return Normalise::table($data);
    }

    /** Editor to stored: the same shape, and null for a table with nothing in it so `required` can speak. */
    public function process($data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        $table = Normalise::table($data);

        return Normalise::tableIsEmpty($table) ? null : $table;
    }

    public function preProcessIndex($data): ?string
    {
        if (! is_array($data)) {
            return null;
        }

        $table = Normalise::table($data);
        $rows = count(array_filter($table['rows'], fn ($r) => ! $r['header']));

        return $table['caption'].' ('.$rows.' '.($rows === 1 ? 'row' : 'rows').')';
    }

    public function rules(): array
    {
        return [new ValidTable];
    }

    /**
     * What a template gets: the table's markup as a string, and its parts
     * by path. Null for nothing.
     */
    public function augment($value): ?Rendered
    {
        if (! is_array($value)) {
            return null;
        }

        $table = Normalise::table($value);

        if (Normalise::tableIsEmpty($table)) {
            return null;
        }

        $dataset = Dataset::fromArray($table);
        $html = (new TableRenderer)->render($dataset, ['id' => $this->elementId()]);

        return Rendered::make($html, $dataset->toArray());
    }

    /** What the editor is told once: how a new table starts. */
    public function preload(): array
    {
        return [
            'firstRowHeader' => (bool) $this->config('first_row_header', true),
            'firstColumnHeader' => (bool) $this->config('first_column_header', false),
        ];
    }

    /** An id unique to this field on this entry, so two tables on one page never share header ids. */
    private function elementId(): string
    {
        $parent = $this->field()?->parent();
        $owner = is_object($parent) && method_exists($parent, 'id') ? (string) $parent->id() : '';

        return 'a11y-data-'.($this->field()?->handle() ?? 'table').($owner !== '' ? '-'.substr(sha1($owner), 0, 6) : '');
    }
}
