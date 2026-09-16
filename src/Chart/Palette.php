<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Chart;

use InvalidArgumentException;

/**
 * The colours a chart may use: the background it is drawn on and one
 * colour per series. Every series colour is checked against the
 * background at 3:1 before a chart renders, and at save time in the
 * control panel, by PaletteCheck.
 */
final class Palette
{
    public readonly string $background;

    /** @var list<string> */
    public readonly array $series;

    /**
     * Eight colours that clear 3:1 on white by a margin, so a site that
     * never opens the config file still passes. Verified in the suite.
     */
    public const DEFAULT = [
        'background' => '#ffffff',
        'series' => ['#0b5fa5', '#c2410c', '#15803d', '#7e22ce', '#b91c1c', '#0f766e', '#a16207', '#4b5563'],
    ];

    /** @param  list<string>  $series */
    public function __construct(string $background, array $series)
    {
        if ($series === []) {
            throw new InvalidArgumentException('A palette needs at least one series colour.');
        }

        $this->background = Contrast::normalize($background);
        $this->series = array_values(array_map(fn ($c) => Contrast::normalize((string) $c), $series));
    }

    public static function default(): self
    {
        return self::fromArray(self::DEFAULT);
    }

    /** @param  array{background?: string, series?: list<string>}|self  $palette */
    public static function from(array|self $palette): self
    {
        return $palette instanceof self ? $palette : self::fromArray($palette);
    }

    /** @param  array{background?: string, series?: list<string>}  $palette  as the config file stores it */
    public static function fromArray(array $palette): self
    {
        return new self(
            (string) ($palette['background'] ?? self::DEFAULT['background']),
            array_values((array) ($palette['series'] ?? self::DEFAULT['series'])),
        );
    }

    /** The colour for a series, cycling when there are more series than colours. */
    public function colour(int $series): string
    {
        return $this->series[$series % count($this->series)];
    }

    /** @return array{background: string, series: list<string>} */
    public function toArray(): array
    {
        return ['background' => $this->background, 'series' => $this->series];
    }
}
