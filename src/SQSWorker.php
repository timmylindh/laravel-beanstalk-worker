<?php

namespace Timmylindh\LaravelBeanstalkWorker;

use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Throwable;

class SQSWorker extends Worker
{
    /**
     * Process the given job from the queue.
     *
     * @param  string  $connectionName
     * @param  SQSJob  $job
     * @param  \Illuminate\Queue\WorkerOptions  $options
     * @return void
     *
     * @throws \Throwable
     */
    public function process($connectionName, $job, WorkerOptions $options)
    {
        if ($job->hasTimedoutAndShouldFail()) {
            return $this->markJobAsFailedIfItShouldFailOnTimeout(
                $job->getConnectionName(),
                $job,
                $this->timeoutExceededException($job),
            );
        }

        $this->startTimeoutHandler($job, $options);
        parent::process($connectionName, $job, $options);
    }

    /**
     * Register the worker timeout handler. This will on timeout reached fail the job.
     * Due the the built-in Laravel shutdown function throwing an error on timeout
     * the SQS daemon will not delete the job and it will be retried. Thus we delete it on
     * the next re-queue.
     *
     * @param  \Illuminate\Contracts\Queue\Job|null  $job
     * @param  \Illuminate\Queue\WorkerOptions  $options
     * @return void
     */
    protected function startTimeoutHandler($job, WorkerOptions $options)
    {
        if (!$job) {
            return;
        }

        set_time_limit(max($this->timeoutForJob($job, $options), 0));

        register_shutdown_function(function () use ($job, $options) {
            if (connection_status() !== CONNECTION_TIMEOUT) {
                return;
            }

            $this->events->dispatch(
                new JobTimedOut($job->getConnectionName(), $job),
            );
        });
    }
}
