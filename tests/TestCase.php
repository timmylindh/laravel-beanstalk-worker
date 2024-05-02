<?php

namespace Timmylindh\LaravelBeanstalkWorker\Tests;

use Illuminate\Events\Dispatcher;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionFunction;
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

    public function assertListenerIsAttachedToEvent($listener, $event)
    {
        $dispatcher = app(Dispatcher::class);

        foreach (
            $dispatcher->getListeners(
                is_object($event) ? get_class($event) : $event,
            )
            as $listenerClosure
        ) {
            $reflection = new ReflectionFunction($listenerClosure);
            $listenerClass = $reflection->getStaticVariables()['listener'];

            if ($listenerClass === $listener) {
                $this->assertTrue(true);

                return;
            }
        }

        $this->assertTrue(
            false,
            sprintf(
                'Event %s does not have the %s listener attached to it',
                $event,
                $listener,
            ),
        );
    }
}
