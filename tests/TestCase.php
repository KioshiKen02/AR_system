<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $sqlite = $app['config']->get('database.connections.sqlite');

        $app['config']->set('database.connections.tenant', $sqlite);
        $app['config']->set('database.default', 'tenant');

        $app['config']->set('auth.providers.users.model', \App\Models\MasterfileModels\TenantUser::class);
    }
}
