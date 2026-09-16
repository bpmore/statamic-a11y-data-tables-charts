<?php

declare(strict_types=1);

namespace Bpmore\Furrow\Rules;

use Bpmore\Furrow\Chart\Palette;
use Bpmore\Furrow\Chart\PaletteCheck;
use Bpmore\Furrow\Statamic\Palettes;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

/**
 * The blueprint-side check: a chart field's palette override is refused
 * when the blueprint is saved if any series colour is under 3:1 against
 * the background, or any colour is not a hex colour. Attached to the
 * series list, and reads the background from the same form.
 */
final class PaletteContrast implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $background = $this->data['palette_background'] ?? null;
        $series = is_array($value) ? array_values(array_filter(array_map(fn ($c) => is_string($c) ? trim($c) : '', $value), fn ($c) => $c !== '')) : [];

        if ($series === [] && (! is_string($background) || trim($background) === '')) {
            return;
        }

        $base = Palettes::configured();

        try {
            $palette = new Palette(
                is_string($background) && trim($background) !== '' ? $background : $base->background,
                $series === [] ? $base->series : $series,
            );
        } catch (InvalidArgumentException $e) {
            $fail($e->getMessage());

            return;
        }

        $check = PaletteCheck::check($palette);

        if (! $check->passes()) {
            $fail('This palette fails non-text contrast (WCAG 1.4.11) and cannot be saved. '.$check->message());
        }
    }
}
