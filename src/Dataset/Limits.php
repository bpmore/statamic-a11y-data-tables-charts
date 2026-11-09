<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Dataset;

/**
 * How big a table can be. A span is a number the stored shape carries, and
 * the grid makes rowspan times colspan slots for every cell, so with no
 * ceiling one cell spanning a million rows and a million columns exhausts
 * memory on every render of the entry: the page, augmentation, live
 * preview, the tag. These ceilings sit far past any table a page should
 * carry and well inside what the grid lays out in a few milliseconds.
 *
 * Three places hold them. Cell clamps a span as it is read, so a renderer
 * never sees more. Grid refuses to lay out more rows or columns than this,
 * so no path can allocate more than ROWS by COLUMNS slots. The validation
 * rules refuse to save a table past any of them, with a message, so an
 * author is told rather than silently trimmed.
 */
final class Limits
{
    /** The most rows or columns one cell can span. */
    public const SPAN = 100;

    public const ROWS = 1000;

    public const COLUMNS = 100;

    /**
     * Why a stored table is too big, or null when it is not. Reads the raw
     * shape before anything is padded or laid out, so the check costs
     * nothing in proportion to what it refuses.
     *
     * @param  array<string, mixed>  $table
     */
    public static function check(array $table): ?string
    {
        $rows = is_array($table['rows'] ?? null) ? $table['rows'] : [];
        if (count($rows) > self::ROWS) {
            return TableTooLarge::rows(count($rows))->getMessage();
        }

        $columns = is_array($table['columns'] ?? null) ? $table['columns'] : [];
        if (count($columns) > self::COLUMNS) {
            return TableTooLarge::columns()->getMessage();
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cells = array_key_exists('cells', $row) ? $row['cells'] : (array_is_list($row) ? $row : []);
            foreach (is_array($cells) ? $cells : [] as $cell) {
                if (! is_array($cell)) {
                    continue;
                }
                if ((int) ($cell['colspan'] ?? 1) > self::SPAN) {
                    return sprintf('A cell spans %d columns. The most a cell can span is %d.', (int) $cell['colspan'], self::SPAN);
                }
                if ((int) ($cell['rowspan'] ?? 1) > self::SPAN) {
                    return sprintf('A cell spans %d rows. The most a cell can span is %d.', (int) $cell['rowspan'], self::SPAN);
                }
            }
        }

        // Every span is within bounds, so laying the rows out is cheap, and
        // the grid is what knows whether a row reaches past the last column.
        try {
            Grid::widthOf(array_map(fn ($row) => Row::fromArray($row), array_values(array_filter($rows, 'is_array'))));
        } catch (TableTooLarge $e) {
            return $e->getMessage();
        }

        return null;
    }
}
