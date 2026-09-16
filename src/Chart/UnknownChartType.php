<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Chart;

use InvalidArgumentException;

final class UnknownChartType extends InvalidArgumentException
{
    public static function named(string $type): self
    {
        return new self(sprintf('"%s" is not a chart type. The chart types are %s.', $type, implode(', ', ChartRenderer::TYPES)));
    }
}
