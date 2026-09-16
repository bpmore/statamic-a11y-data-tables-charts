<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Chart;

/**
 * What tells one series from another without colour (WCAG 1.4.1): a fill
 * pattern for bars, a dash array and a marker shape for lines. Eight of
 * each; a chart with more series than that cycles, and its data table is
 * the reliable reading.
 */
final class Shapes
{
    /**
     * Pattern marks on an 8x8 tile, drawn in the background colour over the
     * series colour. The first is solid, so with two series one is plain.
     *
     * @return list<array{name: string, marks: string}>
     */
    public const PATTERNS = [
        ['name' => 'solid', 'marks' => ''],
        ['name' => 'diagonal', 'marks' => '<path class="a11y-data-pattern__mark" d="M-2 2 L2 -2 M0 8 L8 0 M6 10 L10 6"/>'],
        ['name' => 'dots', 'marks' => '<circle class="a11y-data-pattern__mark" cx="4" cy="4" r="1.6"/>'],
        ['name' => 'horizontal', 'marks' => '<path class="a11y-data-pattern__mark" d="M0 4 H8"/>'],
        ['name' => 'vertical', 'marks' => '<path class="a11y-data-pattern__mark" d="M4 0 V8"/>'],
        ['name' => 'crosshatch', 'marks' => '<path class="a11y-data-pattern__mark" d="M0 0 L8 8 M8 0 L0 8"/>'],
        ['name' => 'reverse-diagonal', 'marks' => '<path class="a11y-data-pattern__mark" d="M-2 6 L2 10 M0 0 L8 8 M6 -2 L10 2"/>'],
        ['name' => 'grid', 'marks' => '<path class="a11y-data-pattern__mark" d="M0 4 H8 M4 0 V8"/>'],
    ];

    /** @var list<string> */
    public const DASHES = ['none', '9 5', '2 4', '12 4 3 4', '16 6', '5 5', '12 3 3 3 3 3', '7 3 2 3'];

    /**
     * Marker paths centred on 0,0, about 12 units across.
     *
     * @var list<array{name: string, d: string}>
     */
    public const MARKERS = [
        ['name' => 'circle', 'd' => 'M-5 0 a5 5 0 1 0 10 0 a5 5 0 1 0 -10 0'],
        ['name' => 'square', 'd' => 'M-5 -5 h10 v10 h-10 z'],
        ['name' => 'triangle', 'd' => 'M0 -6 L6 5 L-6 5 z'],
        ['name' => 'diamond', 'd' => 'M0 -6.5 L6.5 0 L0 6.5 L-6.5 0 z'],
        ['name' => 'cross', 'd' => 'M-5 -5 L5 5 M5 -5 L-5 5'],
        ['name' => 'plus', 'd' => 'M0 -6 V6 M-6 0 H6'],
        ['name' => 'triangle-down', 'd' => 'M0 6 L6 -5 L-6 -5 z'],
        ['name' => 'star', 'd' => 'M0 -6 L1.8 -2.4 L5.7 -1.9 L2.9 0.9 L3.5 4.9 L0 3 L-3.5 4.9 L-2.9 0.9 L-5.7 -1.9 L-1.8 -2.4 z'],
    ];

    /** @return array{name: string, marks: string} */
    public static function pattern(int $series): array
    {
        return self::PATTERNS[$series % count(self::PATTERNS)];
    }

    public static function dash(int $series): string
    {
        return self::DASHES[$series % count(self::DASHES)];
    }

    /** @return array{name: string, d: string} */
    public static function marker(int $series): array
    {
        return self::MARKERS[$series % count(self::MARKERS)];
    }
}
