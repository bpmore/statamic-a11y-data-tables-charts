<?php

declare(strict_types=1);

namespace Bpmore\Furrow\Support;

/** Escaping and attribute assembly for the two renderers. Pure PHP; no framework helpers. */
final class Html
{
    public static function e(string|int|float|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /**
     * Attributes as a string, leading space included when there are any.
     * Null, false and the empty string are left out; true is a bare attribute.
     *
     * @param  array<string, string|int|float|bool|null>  $attributes
     */
    public static function attrs(array $attributes): string
    {
        $out = '';

        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false || $value === '') {
                continue;
            }

            $out .= ' '.$name.($value === true ? '' : '="'.self::e($value).'"');
        }

        return $out;
    }

    /**
     * An id safe to put in an attribute and reference from another: what
     * came in with everything but letters, digits, hyphens and underscores
     * replaced, starting with a letter.
     */
    public static function id(string $id): string
    {
        $clean = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $id), '-');

        if ($clean === '' || ! ctype_alpha($clean[0])) {
            $clean = 'furrow-'.$clean;
        }

        return rtrim($clean, '-');
    }
}
