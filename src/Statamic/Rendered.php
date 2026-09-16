<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Statamic;

use Statamic\Fields\ArrayableString;

/**
 * What a template gets for a table or chart field: the markup when used
 * as a string (`{{ my_table }}`), and the parts when used as a pair or a
 * path (`{{ my_table:caption }}`). Statamic's own ArrayableString does
 * exactly this, so it is one here.
 */
final class Rendered extends ArrayableString
{
    /** @param  array<string, mixed>  $extra */
    public static function make(string $html, array $extra = []): self
    {
        return new self($html, ['html' => $html] + $extra);
    }

    public function html(): string
    {
        return (string) $this->value;
    }
}
