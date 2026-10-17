<?php

namespace Saccharine\Bpmn\Tests\Concerns;

/**
 * Forces `saccharine-bpmn.middleware` to ['web', 'auth'] for a single test file.
 *
 * A Pest file-level `uses()` cannot swap the test case class once the
 * directory-level binding in Pest.php applies, but traits are appended
 * without conflict. The trait's getEnvironmentSetUp takes precedence over
 * the inherited one, and runs before the package's service provider
 * registers its routes — the only moment the middleware config can still
 * influence route registration.
 */
trait ForcesBpmnAuthMiddleware
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('saccharine-bpmn.middleware', ['web', 'auth']);
    }
}
