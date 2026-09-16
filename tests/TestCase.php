<?php

declare(strict_types=1);

namespace Bpmore\Furrow\Tests;

use Bpmore\Furrow\ServiceProvider;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\FakesViews;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

/**
 * A booted Statamic with the addon in it. Only the feature tests use this:
 * the table and chart renderers take an array and return markup, and their
 * tests boot nothing, which is what keeps them from growing a dependency on
 * something booted.
 */
abstract class TestCase extends AddonTestCase
{
    use FakesViews;
    use PreventsSavingStacheItemsToDisk;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // Testbench ships no app key, and Statamic's boot goes through the
        // session middleware, which encrypts.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }
}
