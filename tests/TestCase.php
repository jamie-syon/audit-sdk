<?php

namespace Syon\AuditSdk\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Syon\AuditSdk\AuditSdkServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [AuditSdkServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('audit-sdk.base_url', 'https://platform.test');
        $app['config']->set('audit-sdk.project_id', 'proj_123');
        $app['config']->set('audit-sdk.push_secret', 'test-secret-key');
    }
}
