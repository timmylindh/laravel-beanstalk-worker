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
        $this->startTimeoutHandler($job, $options);
        parent::process($connectionName, $job, $options);
    }

    /**
     * Register the worker timeout handler. This will on timeout reached fail the job.
     * Due the the built-in Laravel shutdown function throwing an error on timeout
     * we need to buffer the output and return an empty string to avoid the SQS daemon
     * requiung the job.
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

        ob_start(function ($str) use ($job, $options) {
            if (connection_status() !== CONNECTION_TIMEOUT) {
                return $str;
            }

            $this->markJobAsFailedIfWillExceedMaxAttempts(
                $job->getConnectionName(),
                $job,
                (int) $options->maxTries,
                $e = $this->timeoutExceededException($job),
            );

            $this->markJobAsFailedIfWillExceedMaxExceptions(
                $job->getConnectionName(),
                $job,
                $e,
            );

            $this->markJobAsFailedIfItShouldFailOnTimeout(
                $job->getConnectionName(),
                $job,
                $e,
            );

            header('HTTP/1.1 204 No Content');

            report($e);
            $this->events->dispatch(
                new JobTimedOut($job->getConnectionName(), $job),
            );

            return '';
        });

        register_shutdown_function(function () {
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
        });
    }
}
