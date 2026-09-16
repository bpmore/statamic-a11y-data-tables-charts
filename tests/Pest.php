<?php

declare(strict_types=1);

use Bpmore\Furrow\Tests\TestCase;

uses(TestCase::class)->in('Feature');

/**
 * A dataset in the shape the brief fixes, with the parts a test does not
 * care about filled in. Header rows and columns are opted into per test.
 *
 * @param  array<int, array<int, mixed>>  $rows  plain values, one array per row
 * @param  array<int, string>  $columns  labels
 * @param  array<int, int>  $headerRows  indexes of rows that are header rows
 */
function table(string $caption, array $columns, array $rows, array $headerRows = [], array $headerColumns = []): array
{
    return [
        'caption' => $caption,
        'columns' => array_map(fn ($label, $i) => [
            'label' => $label,
            'type' => $i === 0 ? 'text' : 'number',
            'header' => in_array($i, $headerColumns, true),
        ], $columns, array_keys($columns)),
        'rows' => array_map(fn ($cells, $i) => [
            'header' => in_array($i, $headerRows, true),
            'cells' => array_map(fn ($value) => is_array($value) ? $value : ['value' => $value], $cells),
        ], $rows, array_keys($rows)),
    ];
}

/**
 * Markup parsed so a test can ask questions of the DOM rather than of a
 * string. Rendered fragments are wrapped in a document; the renderer's own
 * output is what is parsed, never something a browser has repaired.
 */
function dom(string $html): DOMXPath
{
    $doc = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body>'.$html.'</body></html>');
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($doc);
}

/** Every element matching an XPath query, as an array so Pest can count it. */
function nodes(DOMXPath $xpath, string $query): array
{
    return iterator_to_array($xpath->query($query));
}
