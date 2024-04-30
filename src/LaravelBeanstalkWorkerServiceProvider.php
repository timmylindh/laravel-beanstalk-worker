<?php

namespace Timmylindh\LaravelBeanstalkWorker;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Facades\Facade;

class LaravelBeanstalkWorkerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/worker.php', 'worker');

        if (!config('worker.is_worker')) {
            return;
        }

        $this->bindWorker();

        $this->app->singleton(
            SqsQueue::class,
            fn($app) => $app->make(QueueFactory::class)->connection('sqs'),
        );
    }

    public function boot(): void
    {
        $this->publishes(
            [
                __DIR__ . '/../config/worker.php' => config_path('worker.php'),
            ],
            'laravel-beanstalk-worker-config',
        );

        if (config('worker.is_worker')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/routes.php');
        }
    }

    protected function bindWorker()
    {
        $this->app->singleton(SQSWorker::class, function ($app) {
            $resetScope = function () use ($app) {
                $app['log']->flushSharedContext();

                if (method_exists($app['log'], 'withoutContext')) {
                    $app['log']->withoutContext();
                }

                if (method_exists($app['db'], 'getConnections')) {
                    foreach ($app['db']->getConnections() as $connection) {
                        $connection->resetTotalQueryDuration();
                        $connection->allowQueryDurationHandlersToRunAgain();
                    }
                }

                $app->forgetScopedInstances();

                Facade::clearResolvedInstances();
            };

            return new SQSWorker(
                $app['queue'],
                $app['events'],
                $app[ExceptionHandler::class],
                fn() => $this->app->isDownForMaintenance(),
                $resetScope,
            );
        });
    }
}
