<?php

declare(strict_types=1);

namespace Bpmore\A11yDataTablesCharts;

use Statamic\Providers\AddonServiceProvider;

/**
 * The addon: two fieldtypes, one tag, a config file and a stylesheet.
 *
 * The renderers under Table and Chart are pure PHP and know nothing of
 * this class. Fieldtypes under Fieldtypes and the tag under Tags register
 * themselves by folder; the control panel editors are the Vue components
 * Vite builds from resources/js.
 *
 * There is no licence check here and there must never be one. Statamic
 * resolves the licence and shows its own banner.
 */
class ServiceProvider extends AddonServiceProvider
{
    public const PACKAGE = 'bpmore/statamic-a11y-data-tables-charts';

    protected $config = true;

    protected $vite = [
        'input' => ['resources/js/cp.js'],
        'publicDirectory' => 'resources/dist',
    ];

    /**
     * The front-end stylesheet, published to public/vendor/statamic-a11y-data-tables-charts/css.
     * `{{ a11y_data:styles }}` inlines the same file, which is the route most
     * sites should take: one fewer request, and nothing to publish.
     */
    protected $publishables = [
        __DIR__.'/../resources/css' => 'css',
    ];

    protected $viewNamespace = 'a11y-data';
}
