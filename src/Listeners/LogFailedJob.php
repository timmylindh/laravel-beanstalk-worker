<?php

namespace Timmylindh\LaravelBeanstalkWorker\Listeners;

use Illuminate\Queue\Events\JobFailed;

class LogFailedJob
{
    public function handle(JobFailed $event)
    {
        app('queue.failer')->log(
            $event->connectionName,
            $event->job->getQueue(),
            $event->job->getRawBody(),
            $event->exception,
        );
    }
}
