<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Chart;

/** What PaletteCheck found: nothing, or which colours fall short and by how much. */
final class PaletteResult
{
    /** @param  list<array{series: int, color: string, against: string, ratio: float}>  $failures */
    public function __construct(private readonly array $failures) {}

    public function passes(): bool
    {
        return $this->failures === [];
    }

    /** @return list<array{series: int, color: string, against: string, ratio: float}> */
    public function failures(): array
    {
        return $this->failures;
    }

    /** One line per failure, for a validation message or a console. */
    public function message(): string
    {
        return implode(' ', array_map(
            fn ($f) => sprintf('Series colour %d (%s) is %.2f:1 against %s; the minimum is %s:1.', $f['series'] + 1, $f['color'], $f['ratio'], $f['against'], PaletteCheck::MINIMUM),
            $this->failures,
        ));
    }
}
