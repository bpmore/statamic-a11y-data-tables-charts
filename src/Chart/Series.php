<?php

declare(strict_types=1);

namespace Bpmore\Furrow\Chart;

/** One series: its name and one value per category, null where the cell was not a number. */
final class Series
{
    /**
     * @param  list<float|null>  $values
     * @param  list<string>  $labels  the values as the author wrote them, for labels and the summary
     */
    public function __construct(
        public readonly string $name,
        public readonly array $values,
        public readonly array $labels,
    ) {}

    public function isEmpty(): bool
    {
        return array_filter($this->values, fn ($v) => $v !== null) === [];
    }
}
