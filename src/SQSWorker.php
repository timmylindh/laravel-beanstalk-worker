<?php

namespace Timmylindh\LaravelBeanstalkWorker;

use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Throwable;
use Timmylindh\LaravelBeanstalkWorker\Exceptions\DidTimeoutAndFailException;

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
            throw new DidTimeoutAndFailException();
        }

        $this->startTimeoutHandler($job, $options);
        parent::process($connectionName, $job, $options);
    }

    /**
     * Register the worker timeout handler. This will on timeout reached fail the job.
     * Due the the built-in Laravel shutdown function throwing an error, the job will be
     * released back to the queue and retried.
     *
     * We will catch the error in the call to the /worker/queue endpoint and either
     * delete the job there, or retry it depending on failOnTimeout config.
     *
     *
     * @param  \Illuminate\Contracts\Queue\Job|null  $job
     * @param  \Illuminate\Queue\WorkerOptions  $options
     * @return void
     */
    protected function startTimeoutHandler($job, WorkerOptions $options)
    {
        set_time_limit(max($this->timeoutForJob($job, $options), 0));

        register_shutdown_function(function () use ($job, $options) {
            if (!$job) {
                return;
            }

            if (connection_status() !== CONNECTION_TIMEOUT) {
                return;
            }

            $this->markJobAsFailedIfItShouldFailOnTimeout(
                $job->getConnectionName(),
                $job,
                $this->timeoutExceededException($job),
            );

            $this->events->dispatch(
                new JobTimedOut($job->getConnectionName(), $job),
            );
        });
    }
}
