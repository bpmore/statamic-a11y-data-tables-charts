<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Rules;

use Bpmore\A11yDataTablesCharts\Chart\ChartRenderer;
use Bpmore\A11yDataTablesCharts\Chart\Palette;
use Bpmore\A11yDataTablesCharts\Chart\PaletteCheck;
use Bpmore\A11yDataTablesCharts\Statamic\Normalise;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

/**
 * A chart field saves only when it can be drawn: a type this addon has, a
 * data source that exists (a table field on this entry with a caption, or
 * inline data with one), and a palette that passes contrast.
 *
 * The referenced table is looked up in the values being saved, which is
 * what DataAwareRule provides, so a chart pointing at a field that has
 * just been emptied is refused in the same request.
 */
final class ValidChart implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private readonly ?Palette $palette, private readonly ?string $paletteError = null) {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (! is_array($value)) {
            $fail('The chart is not in a shape this field can read.');

            return;
        }

        $chart = Normalise::chart($value);

        if (! in_array($chart['type'], ChartRenderer::TYPES, true)) {
            $fail(sprintf('"%s" is not a chart type. Choose one of: %s.', $chart['type'], implode(', ', ChartRenderer::TYPES)));
        }

        if ($chart['source'] === 'inline') {
            if (($chart['data']['caption'] ?? '') === '') {
                $fail('The chart data needs a caption. It names the chart and its data table.');
            }
        } else {
            $this->validateReference($attribute, $chart['table'], $fail);
        }

        if ($this->paletteError !== null) {
            $fail($this->paletteError);
        } elseif ($this->palette !== null) {
            $check = PaletteCheck::check($this->palette);
            if (! $check->passes()) {
                $fail('The chart palette fails non-text contrast, so the chart cannot be drawn. '.$check->message());
            }
        }
    }

    private function validateReference(string $attribute, ?string $handle, Closure $fail): void
    {
        if ($handle === null) {
            $fail('Choose the table field this chart draws.');

            return;
        }

        // A sibling: same prefix as this field, so a chart inside a set
        // finds the table in the same set.
        $prefix = str_contains($attribute, '.') ? substr($attribute, 0, strrpos($attribute, '.') + 1) : '';
        $sibling = $this->data;
        foreach (array_filter(explode('.', $prefix.$handle), fn ($s) => $s !== '') as $segment) {
            if (! is_array($sibling) || ! array_key_exists($segment, $sibling)) {
                $fail(sprintf('The table field "%s" is not on this entry, so the chart has nothing to draw.', $handle));

                return;
            }
            $sibling = $sibling[$segment];
        }

        if (! is_array($sibling) || Normalise::table($sibling)['caption'] === '') {
            $fail(sprintf('The table field "%s" is empty or has no caption, so the chart has nothing to draw.', $handle));
        }
    }

    /** Built from a field's config, with a palette that will not parse turned into a message rather than an exception. */
    public static function forPalette(callable $resolve): self
    {
        try {
            return new self($resolve());
        } catch (InvalidArgumentException $e) {
            return new self(null, 'The chart palette cannot be read: '.$e->getMessage());
        }
    }
}
