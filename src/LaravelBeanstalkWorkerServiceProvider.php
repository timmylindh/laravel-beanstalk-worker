<?php

namespace Timmylindh\LaravelBeanstalkWorker;

use Illuminate\Queue\Worker;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Queue\SqsQueue;

class LaravelBeanstalkWorkerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/worker.php', 'worker');

        if (!config('worker.is_worker')) {
            return;
        }

        $this->app->singleton(
            Worker::class,
            fn($app) => $app->make('queue.worker'),
        );

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
}
