<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Dataset;

use InvalidArgumentException;

/**
 * A table past the ceilings in Limits is not laid out. Thrown by the grid
 * before it makes a slot, so a stored table that was never validated (one
 * written by hand, or through the API) costs a message, not the memory.
 */
final class TableTooLarge extends InvalidArgumentException
{
    public static function rows(int $rows): self
    {
        return new self(sprintf('The table has %d rows. The most a table can have is %d.', $rows, Limits::ROWS));
    }

    public static function columns(): self
    {
        return new self(sprintf('The table is wider than %d columns, the most a table can have.', Limits::COLUMNS));
    }
}
