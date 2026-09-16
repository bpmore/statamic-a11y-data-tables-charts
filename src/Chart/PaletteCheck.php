<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Chart;

/**
 * Non-text contrast (WCAG 1.4.11): every series colour against the
 * background at 3:1 or better. Runs when a palette is saved in the control
 * panel and again before every render, so a failing palette is refused
 * rather than warned about.
 */
final class PaletteCheck
{
    public const MINIMUM = 3.0;

    public static function check(Palette $palette): PaletteResult
    {
        $failures = [];

        foreach ($palette->series as $i => $colour) {
            $ratio = Contrast::ratio($colour, $palette->background);

            if ($ratio < self::MINIMUM) {
                $failures[] = [
                    'series' => $i,
                    'color' => $colour,
                    'against' => $palette->background,
                    'ratio' => $ratio,
                ];
            }
        }

        return new PaletteResult($failures);
    }
}
