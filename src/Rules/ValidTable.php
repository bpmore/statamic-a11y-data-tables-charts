<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Rules;

use Bpmore\A11yDataTablesCharts\Statamic\Normalise;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A table field will not save without a caption. It is the accessible
 * name of the table, the single highest-value constraint in this addon,
 * and there is no field setting that turns it off.
 */
final class ValidTable implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (! is_array($value)) {
            $fail('The table is not in a shape this field can read.');

            return;
        }

        $table = Normalise::table($value);

        if ($table['caption'] === '') {
            $fail('The table needs a caption. It is the name a screen reader gives the table.');
        }
    }
}
