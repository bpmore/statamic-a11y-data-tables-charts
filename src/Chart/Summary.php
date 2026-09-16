<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Chart;

/**
 * One sentence about the data for the SVG's <desc>, written from the
 * numbers when the author has not written one: where the values run from
 * and to, and which series and category hold the ends.
 */
final class Summary
{
    public static function of(ChartData $data): string
    {
        $points = [];
        foreach ($data->series as $s) {
            foreach ($s->values as $i => $v) {
                if ($v !== null) {
                    $points[] = ['value' => $v, 'label' => $s->labels[$i], 'series' => $s->name, 'category' => $data->categories[$i] ?? ''];
                }
            }
        }

        if ($points === []) {
            return 'There are no values to show yet.';
        }

        usort($points, fn ($a, $b) => $a['value'] <=> $b['value']);
        $low = $points[0];
        $high = $points[count($points) - 1];

        $several = count($data->series) > 1;
        $where = fn (array $p) => trim(($several ? $p['series'].' ' : '').($p['category'] !== '' ? 'in '.$p['category'] : ''));

        if ($low['value'] === $high['value']) {
            return sprintf('Every value is %s.', self::plain($low['label'], $low['value']));
        }

        return sprintf(
            'Values range from %s (%s) to %s (%s).',
            self::plain($low['label'], $low['value']),
            $where($low),
            self::plain($high['label'], $high['value']),
            $where($high),
        );
    }

    /** The author's own text for the number when it reads as one, else the number. */
    private static function plain(string $label, float $value): string
    {
        return trim($label) !== '' ? trim($label) : Scale::label($value);
    }
}
