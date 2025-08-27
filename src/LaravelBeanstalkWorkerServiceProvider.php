<?php

namespace Timmylindh\LaravelBeanstalkWorker;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Facades\Event;
use Timmylindh\LaravelBeanstalkWorker\Listeners\LogFailedJob;

class LaravelBeanstalkWorkerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/worker.php', 'worker');

        if (!config('worker.is_worker')) {
            return;
        }

        $this->app->singleton(SQSWorker::class, function ($app) {
            return new SQSWorker(
                $app['queue'],
                $app['events'],
                $app[ExceptionHandler::class],
                fn() => $this->app->isDownForMaintenance(),
            );
        });

        $this->app->singleton(
            SqsQueue::class,
            fn($app) => $app['queue']->connection('sqs'),
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

        // Publish Elastic Beanstalk deployment hooks and Nginx snippets
        $this->publishes(
            [
                __DIR__ .
                '/../resources/eb/.platform/hooks/postdeploy/20-php-fpm-timeout.sh' => base_path(
                    '.platform/hooks/postdeploy/20-php-fpm-timeout.sh',
                ),
                __DIR__ .
                '/../resources/eb/.platform/hooks/postdeploy/30-nginx-fastcgi-timeout.sh' => base_path(
                    '.platform/hooks/postdeploy/30-nginx-fastcgi-timeout.sh',
                ),
                __DIR__ .
                '/../resources/eb/.platform/hooks/postdeploy/40-sqsd-inactivity-timeout.sh' => base_path(
                    '.platform/hooks/postdeploy/40-sqsd-inactivity-timeout.sh',
                ),
                __DIR__ .
                '/../resources/eb/.platform/hooks/postdeploy/50-sqs-visibility-timeout.sh' => base_path(
                    '.platform/hooks/postdeploy/50-sqs-visibility-timeout.sh',
                ),
            ],
            'laravel-beanstalk-worker-deploy',
        );

        if (!config('worker.is_worker')) {
            return;
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/routes.php');
        Event::listen(JobFailed::class, LogFailedJob::class);
    }
}
