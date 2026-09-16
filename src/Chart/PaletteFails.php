<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Chart;

use InvalidArgumentException;

/** Thrown rather than rendered: a chart whose palette fails contrast is not drawn at all. */
final class PaletteFails extends InvalidArgumentException
{
    public static function from(PaletteResult $result): self
    {
        return new self('The chart palette fails non-text contrast. '.$result->message());
    }
}
