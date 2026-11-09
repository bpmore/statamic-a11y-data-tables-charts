<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts\Statamic;

use Bpmore\A11yDataTablesCharts\Dataset\Limits;
use Bpmore\A11yDataTablesCharts\Dataset\TableTooLarge;
use Bpmore\A11yDataTablesCharts\Support\Html;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Statamic\Facades\Blink;

/**
 * What a page shows in place of a table or chart that cannot be rendered:
 * a paragraph saying so and what to do about it, in the element's place,
 * with a class a site can style. Nothing is thrown and nothing is
 * silently blank, because a stored value the rules never saw (a file
 * edited by hand, an API write) must not take the page down with it.
 *
 * Each notice is logged once per request at warning level, so the person
 * who can fix it hears about it without the log filling up for a table
 * printed in three places.
 */
final class Notice
{
    public const TABLE = 'table';

    public const CHART = 'chart';

    /** Why a table is not rendered, for the reader, with the way to fix it. */
    public static function tableMessage(InvalidArgumentException $reason): string
    {
        $fix = $reason instanceof TableTooLarge
            ? sprintf('To fix it, open this entry in the control panel and make the table smaller, or split it: a table can have at most %d rows and %d columns.', Limits::ROWS, Limits::COLUMNS)
            : 'To fix it, open this entry in the control panel and correct the table.';

        return 'This table cannot be displayed: '.$reason->getMessage().' '.$fix;
    }

    /** Why a chart is not drawn, for the reader. */
    public static function chartMessage(string $reason): string
    {
        return 'This chart cannot be drawn: '.$reason;
    }

    /**
     * The notice as a field's augmented value: the markup as a string, and
     * `missing` true by path, with whatever parts the template can still
     * have (a table's caption and stored rows, a chart's type and source).
     *
     * @param  array<string, mixed>  $parts
     */
    public static function rendered(string $kind, ?string $id, string $message, array $parts = [], array $context = []): Rendered
    {
        return Rendered::make(self::html($kind, $id, $message, $context), $parts + ['missing' => true]);
    }

    /**
     * The notice as markup, for the tag. Logs it on the way out.
     *
     * @param  array<string, mixed>  $context  what the log needs to point at the field
     */
    public static function html(string $kind, ?string $id, string $message, array $context = []): string
    {
        self::log($kind, $message, $context + ['id' => $id]);

        return '<p'.Html::attrs([
            'class' => 'a11y-data-'.$kind.' a11y-data-'.$kind.'--missing',
            'id' => $id,
        ]).'>'.Html::e($message).'</p>';
    }

    /** @param  array<string, mixed>  $context */
    private static function log(string $kind, string $message, array $context): void
    {
        $key = 'a11y-data.notice.'.sha1($kind.'|'.$message);

        if (Blink::has($key)) {
            return;
        }
        Blink::put($key, true);

        Log::warning('A11y data '.$kind.' not rendered: '.$message, array_filter($context, fn ($v) => $v !== null && $v !== ''));
    }
}
