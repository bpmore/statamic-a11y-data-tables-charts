<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Dataset;

/** One cell as the author entered it: its text and how many cells it covers. */
final class Cell
{
    public function __construct(
        public readonly string $value,
        public readonly int $colspan = 1,
        public readonly int $rowspan = 1,
        public readonly ?string $abbr = null,
    ) {}

    /**
     * @param  array<string, mixed>|scalar|null  $cell  the stored shape, or a bare value
     */
    public static function fromArray(mixed $cell): self
    {
        if (! is_array($cell)) {
            $cell = ['value' => $cell];
        }

        $abbr = $cell['abbr'] ?? null;
        $abbr = is_scalar($abbr) ? trim((string) $abbr) : '';

        return new self(
            self::text($cell['value'] ?? null),
            max(1, (int) ($cell['colspan'] ?? 1)),
            max(1, (int) ($cell['rowspan'] ?? 1)),
            $abbr === '' ? null : $abbr,
        );
    }

    public function isEmpty(): bool
    {
        return trim($this->value) === '';
    }

    /**
     * The numeric reading of the value, or null when there is none. Commas,
     * spaces, a currency sign or a percent sign around a number are noise a
     * pasted spreadsheet leaves behind, not a reason to drop the point.
     */
    public function number(): ?float
    {
        $clean = preg_replace('/[\s,%$£€]/u', '', $this->value) ?? '';

        return is_numeric($clean) ? (float) $clean : null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = ['value' => $this->value];

        if ($this->colspan > 1) {
            $out['colspan'] = $this->colspan;
        }
        if ($this->rowspan > 1) {
            $out['rowspan'] = $this->rowspan;
        }
        if ($this->abbr !== null) {
            $out['abbr'] = $this->abbr;
        }

        return $out;
    }

    private static function text(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }
        if ($value === true) {
            return '1';
        }
        if (is_float($value)) {
            // 12.0 is "12" to a reader, and 1.0E+25 is nobody's number.
            return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
