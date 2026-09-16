<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Dataset;

use InvalidArgumentException;

/**
 * A dataset with no caption cannot be rendered as a table or a chart. The
 * caption is the accessible name of both, and the one constraint in this
 * addon that nothing can switch off.
 */
final class MissingCaption extends InvalidArgumentException
{
    public static function make(): self
    {
        return new self('A table needs a caption. It is the name a screen reader gives the table, and the one thing this addon will not render without.');
    }
}
