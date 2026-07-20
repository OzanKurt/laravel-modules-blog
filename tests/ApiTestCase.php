<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Tests;

use Illuminate\Foundation\Application;

/**
 * Test case for the REST API suite. Flips `blog.http.mode` to `api` in
 * defineEnvironment (before the providers boot) so BlogServiceProvider's
 * registerModuleApi() actually registers routes/api.php for these tests.
 */
abstract class ApiTestCase extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('blog.http.mode', 'api');
    }
}
