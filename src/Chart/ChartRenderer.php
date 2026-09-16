<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Chart;

use Bpmore\A11yDataTablesCharts\Dataset\Dataset;
use Bpmore\A11yDataTablesCharts\Support\Html;
use Bpmore\A11yDataTablesCharts\Table\TableRenderer;

/**
 * A dataset as a server-rendered inline SVG with its data table beside it.
 *
 * No script, no tooltips, no text as paths. The SVG is an image named by
 * its <title> and described by its <desc>; every value is in the table
 * beneath, rendered by TableRenderer, and no option removes that table.
 * Series are told apart by pattern, dash and marker as well as colour, and
 * the palette is refused outright when a series colour falls under 3:1
 * against the background.
 *
 * Pure PHP: an array in, a string out.
 */
final class ChartRenderer
{
    public const TYPES = ['bar', 'grouped_bar', 'line'];

    /** Drawn in viewBox units and scaled by the page; these are proportions, not pixels. */
    private const WIDTH = 640;

    private const HEIGHT = 400;

    private const FONT = 12;

    /**
     * @param  array<string, mixed>|Dataset  $data
     * @param  array{
     *     type?: string,
     *     id?: string|null,
     *     palette?: Palette|array{background?: string, series?: list<string>}|null,
     *     summary?: string|null,
     *     table?: mixed,
     *     width?: int|float|null,
     *     height?: int|float|null,
     *     class?: string|null,
     * }  $options
     */
    public function render(array|Dataset $data, array $options = []): string
    {
        $dataset = Dataset::from($data);
        $type = (string) ($options['type'] ?? 'bar');
        if (! in_array($type, self::TYPES, true)) {
            throw UnknownChartType::named($type);
        }

        $palette = Palette::from($options['palette'] ?? Palette::DEFAULT);
        $check = PaletteCheck::check($palette);
        if (! $check->passes()) {
            throw PaletteFails::from($check);
        }

        $id = Html::id($options['id'] ?? 'a11y-data-chart-'.substr(sha1(json_encode($dataset->toArray()) ?: ''), 0, 8));
        $chart = ChartData::from($dataset);
        $summary = trim((string) ($options['summary'] ?? ''));
        $summary = $summary === '' ? Summary::of($chart) : $summary;

        $width = max(1, (float) ($options['width'] ?? self::WIDTH));
        $height = max(1, (float) ($options['height'] ?? self::HEIGHT));

        $svg = $this->svg($chart, $dataset->caption, $summary, $type, $palette, $id, $width, $height);

        // The table. Collapsed only on an explicit ask; anything else,
        // including every value that reads like "off", leaves it open.
        $collapsed = ($options['table'] ?? null) === 'collapsed';
        $table = (new TableRenderer)->render($dataset, ['id' => $id.'-table', 'class' => 'a11y-data-chart__table', 'labelledby' => $id.'-data-label']);

        $class = trim('a11y-data-chart a11y-data-chart--'.$type.' '.(string) ($options['class'] ?? ''));

        return '<figure'.Html::attrs(['class' => $class, 'id' => $id]).'>'
            .$svg
            .'<details'.Html::attrs(['class' => 'a11y-data-chart__data', 'open' => ! $collapsed]).'>'
            .'<summary><span'.Html::attrs(['id' => $id.'-data-label']).'>'.Html::e('Data table:').'</span> '.Html::e($dataset->caption).'</summary>'
            .$table
            .'</details>'
            .'</figure>';
    }

    private function svg(ChartData $chart, string $caption, string $summary, string $type, Palette $palette, string $id, float $width, float $height): string
    {
        $series = $chart->series;
        $categories = $chart->categories;
        $n = count($series);
        $isLine = $type === 'line';

        // Legend, when there is more than one series to tell apart.
        $legend = $n > 1 ? $this->legendLayout($series, $width) : ['height' => 0, 'items' => []];

        $scale = new Scale($chart->min(), $chart->max(), 0, 1);
        $tickLabels = array_map(fn ($t) => Scale::label($t), $scale->ticks());
        $tickWidth = max(array_map('strlen', $tickLabels ?: ['0'])) * self::FONT * 0.6;

        $axisTitle = $n === 1 ? $series[0]->name : '';
        $left = 12 + $tickWidth + 10 + ($axisTitle !== '' ? self::FONT + 8 : 0);
        $right = 16;
        $top = 12 + $legend['height'];
        $bottom = 12 + self::FONT + 20;
        $plotW = max(1, $width - $left - $right);
        $plotH = max(1, $height - $top - $bottom);

        $scale = new Scale($chart->min(), $chart->max(), $top + $plotH, $top);

        $out = '';

        // Defs: one pattern per series, for bars.
        if (! $isLine && $n > 0) {
            $out .= '<defs>';
            foreach ($series as $i => $s) {
                $pattern = Shapes::pattern($i);
                $out .= '<pattern'.Html::attrs(['id' => $id.'-p'.$i, 'class' => 'a11y-data-pattern a11y-data-pattern--'.$pattern['name'], 'patternUnits' => 'userSpaceOnUse', 'width' => 8, 'height' => 8]).'>'
                    .'<rect'.Html::attrs(['class' => 'a11y-data-pattern__fill', 'width' => 8, 'height' => 8, 'fill' => $palette->colour($i)]).'/>'
                    .($pattern['marks'] === '' ? '' : '<g'.Html::attrs(['class' => 'a11y-data-pattern__mark', 'fill' => $palette->background, 'stroke' => $palette->background, 'stroke-width' => 1.4]).'>'.$pattern['marks'].'</g>')
                    .'</pattern>';
            }
            $out .= '</defs>';
        }

        // Gridlines and the value axis.
        $out .= '<g class="a11y-data-axis-group">';
        foreach ($scale->ticks() as $tick) {
            $y = $this->n($scale->at($tick));
            $isZero = abs($tick) < 1e-9;
            $out .= '<line'.Html::attrs([
                'class' => $isZero ? 'a11y-data-axis a11y-data-axis--baseline' : 'a11y-data-grid',
                'x1' => $this->n($left), 'x2' => $this->n($left + $plotW), 'y1' => $y, 'y2' => $y,
                'stroke' => 'currentColor', 'stroke-opacity' => $isZero ? null : '0.2', 'stroke-width' => $isZero ? 1.5 : 1,
            ]).'/>';
            $out .= $this->text(Scale::label($tick), $left - 10, $scale->at($tick) + self::FONT * 0.35, 'end', 'a11y-data-tick');
        }
        $out .= '<line'.Html::attrs([
            'class' => 'a11y-data-axis a11y-data-axis--value',
            'x1' => $this->n($left), 'x2' => $this->n($left), 'y1' => $this->n($top), 'y2' => $this->n($top + $plotH),
            'stroke' => 'currentColor', 'stroke-width' => 1.5,
        ]).'/>';
        if ($axisTitle !== '') {
            $cx = 12 + self::FONT * 0.8;
            $cy = $top + $plotH / 2;
            $out .= '<text'.Html::attrs([
                'class' => 'a11y-data-axis-title', 'x' => $this->n($cx), 'y' => $this->n($cy), 'fill' => 'currentColor',
                'font-size' => self::FONT, 'text-anchor' => 'middle', 'transform' => 'rotate(-90 '.$this->n($cx).' '.$this->n($cy).')',
            ]).'>'.Html::e($axisTitle).'</text>';
        }
        $out .= '</g>';

        // Category labels along the bottom. Every one when they fit; when
        // they cannot, every nth, and the table has the rest.
        $count = max(1, count($categories));
        $slot = $plotW / $count;
        $longest = max(array_map(fn ($c) => mb_strlen($c), $categories ?: ['']));
        $every = max(1, (int) ceil(($longest * self::FONT * 0.6 + 8) / $slot));
        $out .= '<g class="a11y-data-categories">';
        foreach ($categories as $i => $category) {
            if ($i % $every !== 0) {
                continue;
            }
            $out .= $this->text($category, $left + $slot * ($i + 0.5), $top + $plotH + self::FONT + 8, 'middle', 'a11y-data-category');
        }
        $out .= '</g>';

        // The marks.
        $out .= $isLine
            ? $this->lines($series, $scale, $palette, $left, $slot)
            : $this->bars($series, $scale, $palette, $id, $left, $slot);

        // The legend, above the plot.
        if ($legend['items'] !== []) {
            $out .= '<g class="a11y-data-legend">';
            foreach ($legend['items'] as $i => $item) {
                $x = $left + $item['x'];
                $y = 12 + $item['y'];
                if ($isLine) {
                    $marker = Shapes::marker($i);
                    $out .= '<path'.Html::attrs(['class' => 'a11y-data-legend__line', 'd' => 'M'.$this->n($x).' '.$this->n($y + 7).' H'.$this->n($x + 28), 'fill' => 'none', 'stroke' => $palette->colour($i), 'stroke-width' => 2.5, 'stroke-dasharray' => Shapes::dash($i)]).'/>';
                    $out .= '<path'.Html::attrs(['class' => 'a11y-data-legend__marker', 'data-series' => $i, 'data-shape' => $marker['name'], 'd' => $marker['d'], 'transform' => 'translate('.$this->n($x + 14).' '.$this->n($y + 7).')', 'fill' => $palette->background, 'stroke' => $palette->colour($i), 'stroke-width' => 2]).'/>';
                } else {
                    $out .= '<rect'.Html::attrs(['class' => 'a11y-data-legend__swatch', 'x' => $this->n($x), 'y' => $this->n($y), 'width' => 14, 'height' => 14, 'fill' => 'url(#'.$id.'-p'.$i.')', 'stroke' => 'currentColor', 'stroke-width' => 1]).'/>';
                }
                $out .= $this->text($series[$i]->name, $x + ($isLine ? 34 : 20), $y + self::FONT - 1, 'start', 'a11y-data-legend__label');
            }
            $out .= '</g>';
        }

        $style = '<style>'
            .'@media (forced-colors: active){'
            .'.a11y-data-pattern__fill{fill:CanvasText}'
            .'.a11y-data-pattern__mark{fill:Canvas;stroke:Canvas}'
            .'.a11y-data-bar,.a11y-data-legend__swatch{stroke:CanvasText}'
            .'.a11y-data-line,.a11y-data-legend__line{stroke:CanvasText}'
            .'.a11y-data-marker,.a11y-data-legend__marker{fill:Canvas;stroke:CanvasText}'
            .'.a11y-data-grid{stroke:GrayText;stroke-opacity:1}'
            .'}'
            .'</style>';

        return '<svg'.Html::attrs([
            'class' => 'a11y-data-chart__svg',
            'viewBox' => '0 0 '.$this->n($width).' '.$this->n($height),
            'preserveAspectRatio' => 'xMidYMid meet',
            'role' => 'img',
            'aria-labelledby' => $id.'-title',
            'aria-describedby' => $id.'-desc',
            'xmlns' => 'http://www.w3.org/2000/svg',
        ]).'>'
            .'<title'.Html::attrs(['id' => $id.'-title']).'>'.Html::e($caption).'</title>'
            .'<desc'.Html::attrs(['id' => $id.'-desc']).'>'.Html::e($summary).'</desc>'
            .$style
            .$out
            .'</svg>';
    }

    /** @param  list<Series>  $series */
    private function bars(array $series, Scale $scale, Palette $palette, string $id, float $left, float $slot): string
    {
        $n = count($series);
        if ($n === 0) {
            return '';
        }

        $groupW = $slot * 0.72;
        $barW = $groupW / $n;
        $labelled = $barW >= 22;
        $zero = $scale->at(0);

        $out = '<g class="a11y-data-bars">';
        foreach ($series as $i => $s) {
            $out .= '<g'.Html::attrs(['class' => 'a11y-data-series', 'data-series' => $i]).'>';
            foreach ($s->values as $c => $v) {
                if ($v === null) {
                    continue;
                }
                $x = $left + $slot * $c + ($slot - $groupW) / 2 + $barW * $i;
                $y = $scale->at($v);
                $top = min($y, $zero);
                $h = abs($y - $zero);
                $out .= '<rect'.Html::attrs([
                    'class' => 'a11y-data-bar',
                    'x' => $this->n($x), 'y' => $this->n($top), 'width' => $this->n(max(0.5, $barW - 2)), 'height' => $this->n(max(0, $h)),
                    'fill' => 'url(#'.$id.'-p'.$i.')', 'stroke' => 'currentColor', 'stroke-width' => 1,
                ]).'/>';
                if ($labelled) {
                    $out .= $this->text($s->labels[$c], $x + ($barW - 2) / 2, $v >= 0 ? $top - 4 : $top + $h + self::FONT, 'middle', 'a11y-data-value', self::FONT - 1);
                }
            }
            $out .= '</g>';
        }

        return $out.'</g>';
    }

    /** @param  list<Series>  $series */
    private function lines(array $series, Scale $scale, Palette $palette, float $left, float $slot): string
    {
        $out = '<g class="a11y-data-lines">';
        $direct = count($series) <= 3;

        foreach ($series as $i => $s) {
            $d = '';
            $pen = false;
            $markers = '';
            $marker = Shapes::marker($i);
            $last = null;

            foreach ($s->values as $c => $v) {
                if ($v === null) {
                    $pen = false;

                    continue;
                }
                $x = $left + $slot * ($c + 0.5);
                $y = $scale->at($v);
                $d .= ($pen ? ' L' : ($d === '' ? 'M' : ' M')).$this->n($x).' '.$this->n($y);
                $pen = true;
                $last = [$x, $y];
                $markers .= '<path'.Html::attrs([
                    'class' => 'a11y-data-marker', 'data-series' => $i, 'data-shape' => $marker['name'],
                    'd' => $marker['d'], 'transform' => 'translate('.$this->n($x).' '.$this->n($y).')',
                    'fill' => $palette->background, 'stroke' => $palette->colour($i), 'stroke-width' => 2,
                ]).'/>';
            }

            $out .= '<g'.Html::attrs(['class' => 'a11y-data-series', 'data-series' => $i]).'>';
            if ($d !== '') {
                $out .= '<path'.Html::attrs([
                    'class' => 'a11y-data-line', 'd' => $d, 'fill' => 'none',
                    'stroke' => $palette->colour($i), 'stroke-width' => 2.5, 'stroke-dasharray' => Shapes::dash($i),
                    'stroke-linejoin' => 'round', 'stroke-linecap' => 'round',
                ]).'/>';
            }
            $out .= $markers;
            if ($direct && $last !== null && count($series) > 1) {
                $out .= $this->text($s->name, $last[0] + 10, $last[1] + self::FONT * 0.35, 'start', 'a11y-data-line__label');
            }
            $out .= '</g>';
        }

        return $out.'</g>';
    }

    /**
     * Where each legend item sits, wrapped to the plot width.
     *
     * @param  list<Series>  $series
     * @return array{height: float, items: list<array{x: float, y: float}>}
     */
    private function legendLayout(array $series, float $width): array
    {
        $items = [];
        $x = 0.0;
        $y = 0.0;
        $rowH = 20.0;
        $available = $width - 40;

        foreach ($series as $s) {
            $w = 34 + mb_strlen($s->name) * self::FONT * 0.6 + 18;
            if ($x > 0 && $x + $w > $available) {
                $x = 0.0;
                $y += $rowH;
            }
            $items[] = ['x' => $x, 'y' => $y];
            $x += $w;
        }

        return ['height' => $y + $rowH + 6, 'items' => $items];
    }

    private function text(string $value, float $x, float $y, string $anchor, string $class, int $size = self::FONT): string
    {
        return '<text'.Html::attrs([
            'class' => $class, 'x' => $this->n($x), 'y' => $this->n($y),
            'fill' => 'currentColor', 'font-size' => $size, 'text-anchor' => $anchor,
        ]).'>'.Html::e($value).'</text>';
    }

    /** A number as SVG wants it: no exponent, no trailing zeros, at most two decimals. */
    private function n(float $value): string
    {
        $s = number_format($value, 2, '.', '');

        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
    }
}
