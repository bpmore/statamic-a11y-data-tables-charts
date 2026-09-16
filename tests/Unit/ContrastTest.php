<?php

declare(strict_types=1);

use Bpmore\A11yDataTablesCharts\Chart\Contrast;
use Bpmore\A11yDataTablesCharts\Chart\Palette;
use Bpmore\A11yDataTablesCharts\Chart\PaletteCheck;

/**
 * Discriminating test 3: contrast rejection, against values computed by
 * hand from the WCAG 2.x definition of relative luminance, not from this
 * implementation.
 *
 * #03a8b1: R=3   -> 3/255   = 0.01176, at or below 0.03928, so /12.92 = 0.000911
 *          G=168 -> 168/255 = 0.65882 -> ((0.65882+0.055)/1.055)^2.4 = 0.391572
 *          B=177 -> 177/255 = 0.69412 -> ((0.69412+0.055)/1.055)^2.4 = 0.439657
 *          L = 0.2126*0.000911 + 0.7152*0.391572 + 0.0722*0.439657 = 0.311989
 *          against white (L=1): (1+0.05)/(0.311989+0.05) = 2.9006
 *
 * #00ab39: R=0   -> 0
 *          G=171 -> 171/255 = 0.67059 -> ((0.67059+0.055)/1.055)^2.4 = 0.407240
 *          B=57  -> 57/255  = 0.22353 -> ((0.22353+0.055)/1.055)^2.4 = 0.040915
 *          L = 0.7152*0.407240 + 0.0722*0.040915 = 0.294212
 *          against white: 1.05/(0.294212+0.05) = 3.0504
 */
it('computes relative luminance as WCAG defines it', function () {
    expect(round(Contrast::luminance('#03a8b1'), 5))->toBe(0.31199);
    expect(round(Contrast::luminance('#00ab39'), 5))->toBe(0.29421);
    expect(Contrast::luminance('#ffffff'))->toBe(1.0);
    expect(Contrast::luminance('#000000'))->toBe(0.0);
});

it('computes the contrast ratio as WCAG defines it', function () {
    expect(round(Contrast::ratio('#03a8b1', '#ffffff'), 3))->toBe(2.901);
    expect(round(Contrast::ratio('#00ab39', '#ffffff'), 3))->toBe(3.05);
    expect(Contrast::ratio('#ffffff', '#000000'))->toBe(21.0);
    // Order does not matter.
    expect(Contrast::ratio('#ffffff', '#03a8b1'))->toBe(Contrast::ratio('#03a8b1', '#ffffff'));
});

it('rejects a palette with a 2.9:1 series colour and accepts one at 3.05:1', function () {
    $failing = PaletteCheck::check(new Palette('#ffffff', ['#000000', '#03a8b1']));
    expect($failing->passes())->toBeFalse();
    expect($failing->failures())->toHaveCount(1);
    expect($failing->failures()[0]['color'])->toBe('#03a8b1');
    expect($failing->failures()[0]['against'])->toBe('#ffffff');
    expect(round($failing->failures()[0]['ratio'], 2))->toBe(2.9);

    $passing = PaletteCheck::check(new Palette('#ffffff', ['#000000', '#00ab39']));
    expect($passing->passes())->toBeTrue();
    expect($passing->failures())->toBe([]);
});

it('checks every series colour against the background, not only the first', function () {
    $result = PaletteCheck::check(new Palette('#ffffff', ['#000000', '#000000', '#000000', '#03a8b1']));

    expect($result->passes())->toBeFalse();
    expect($result->failures()[0]['series'])->toBe(3);
});

it('accepts short hex and upper case', function () {
    expect(Contrast::luminance('#FFF'))->toBe(1.0);
    expect(Contrast::luminance('#00AB39'))->toBe(Contrast::luminance('#00ab39'));
});

it('refuses a colour it cannot read rather than guessing', function () {
    Contrast::luminance('blue');
})->throws(InvalidArgumentException::class);

it('reads a palette from a plain array as the config file stores it', function () {
    $palette = Palette::fromArray(['background' => '#FFF', 'series' => ['#000', '#00ab39']]);

    expect($palette->background)->toBe('#ffffff');
    expect($palette->series)->toBe(['#000000', '#00ab39']);
});

it('needs at least one series colour', function () {
    new Palette('#ffffff', []);
})->throws(InvalidArgumentException::class);
