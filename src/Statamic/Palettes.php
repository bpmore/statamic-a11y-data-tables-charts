<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Statamic;

use Bpmore\A11yDataTablesCharts\Chart\Palette;

/**
 * Which palette a chart field draws with: the field's own override when it
 * has one, otherwise the config file's, otherwise the built-in default.
 */
final class Palettes
{
    /**
     * @param  array<string, mixed>  $fieldConfig  the chart field's blueprint config
     */
    public static function for(array $fieldConfig): Palette
    {
        $background = $fieldConfig['palette_background'] ?? null;
        $series = $fieldConfig['palette_series'] ?? null;
        $series = is_array($series) ? array_values(array_filter(array_map(fn ($c) => is_string($c) ? trim($c) : '', $series), fn ($c) => $c !== '')) : [];

        $base = self::configured();

        if ($series === [] && ! is_string($background)) {
            return $base;
        }

        // An override that will not parse, or fails contrast, is thrown
        // here and shown in place of the chart: a broken palette is a
        // problem to see, not one to paper over with the site's colours.
        return new Palette(
            is_string($background) && trim($background) !== '' ? $background : $base->background,
            $series === [] ? $base->series : $series,
        );
    }

    /** The config file's palette, or the built-in one when the file names none. */
    public static function configured(): Palette
    {
        $config = config('statamic-a11y-data-tables-charts.palette');

        return is_array($config) ? Palette::fromArray($config) : Palette::default();
    }
}
