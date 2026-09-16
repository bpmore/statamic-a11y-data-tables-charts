<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Chart;

/**
 * The value axis: a range that always includes zero, rounded out to a tick
 * step a person would choose (1, 2, 5, or those times a power of ten), and
 * the mapping from a value to a position.
 */
final class Scale
{
    public readonly float $min;

    public readonly float $max;

    public readonly float $step;

    public function __construct(float $low, float $high, private readonly float $from, private readonly float $to, int $ticks = 5)
    {
        $low = min(0.0, $low);
        $high = max(0.0, $high);

        if ($high - $low <= 0) {
            $high = $low + 1;
        }

        $rough = ($high - $low) / max(1, $ticks);
        $magnitude = 10 ** floor(log10($rough));
        $normalised = $rough / $magnitude;
        $nice = $normalised <= 1 ? 1 : ($normalised <= 2 ? 2 : ($normalised <= 5 ? 5 : 10));

        $this->step = $nice * $magnitude;
        $this->min = floor($low / $this->step) * $this->step;
        $this->max = ceil($high / $this->step) * $this->step;
    }

    /** Position along the axis for a value. */
    public function at(float $value): float
    {
        $t = ($value - $this->min) / ($this->max - $this->min);

        return $this->from + ($this->to - $this->from) * $t;
    }

    /** @return list<float> every tick value, min to max */
    public function ticks(): array
    {
        $ticks = [];
        for ($v = $this->min; $v <= $this->max + $this->step / 1000; $v += $this->step) {
            $ticks[] = round($v, 10);
        }

        return $ticks;
    }

    /** A tick as text: no trailing zeros, thousands separated. */
    public static function label(float $value): string
    {
        $decimals = 0;
        for ($d = 0; $d <= 6; $d++) {
            if (abs(round($value, $d) - $value) < 1e-9) {
                $decimals = $d;
                break;
            }
        }

        return number_format($value, $decimals, '.', ',');
    }
}
