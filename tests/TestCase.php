<?php

namespace Timmylindh\LaravelBeanstalkWorker\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Timmylindh\LaravelBeanstalkWorker\LaravelBeanstalkWorkerServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        config()->set('worker.is_worker', true);
        return [LaravelBeanstalkWorkerServiceProvider::class];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_laravel-beanstalk-worker_table.php.stub';
        $migration->up();
        */
    }
}
