<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Chart;

use InvalidArgumentException;

/**
 * WCAG 2.x relative luminance and contrast ratio, from the definitions in
 * the standard and nothing else. Colours are hex only: a chart palette is
 * a list of hex strings in a config file, and accepting names or rgb()
 * would mean carrying a colour parser whose mistakes would be contrast
 * mistakes.
 */
final class Contrast
{
    /**
     * L = 0.2126 R + 0.7152 G + 0.0722 B, each channel linearised as the
     * standard says: c/12.92 at or below 0.03928, else ((c+0.055)/1.055)^2.4.
     */
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::channels($hex);

        return 0.2126 * self::linear($r) + 0.7152 * self::linear($g) + 0.0722 * self::linear($b);
    }

    /** (L1 + 0.05) / (L2 + 0.05), lighter over darker. */
    public static function ratio(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** `#abc` or `#aabbcc`, any case, to lower-case six digit form. */
    public static function normalize(string $hex): string
    {
        $h = ltrim(trim($hex), '#');

        if (preg_match('/^[0-9a-f]{3}$/i', $h)) {
            $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
        }

        if (! preg_match('/^[0-9a-f]{6}$/i', $h)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a hex colour. Use the form #rrggbb.', $hex));
        }

        return '#'.strtolower($h);
    }

    /** @return array{int, int, int} */
    private static function channels(string $hex): array
    {
        $h = substr(self::normalize($hex), 1);

        return [(int) hexdec(substr($h, 0, 2)), (int) hexdec(substr($h, 2, 2)), (int) hexdec(substr($h, 4, 2))];
    }

    private static function linear(int $channel): float
    {
        $c = $channel / 255;

        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }
}
