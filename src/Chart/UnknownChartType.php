<?php

declare(strict_types=1);

namespace Bpmore\Furrow\Chart;

use InvalidArgumentException;

final class UnknownChartType extends InvalidArgumentException
{
    public static function named(string $type): self
    {
        return new self(sprintf('"%s" is not a chart type. Furrow draws %s.', $type, implode(', ', ChartRenderer::TYPES)));
    }
}
