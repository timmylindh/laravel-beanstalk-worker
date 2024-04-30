<?php

namespace Timmylindh\LaravelBeanstalkWorker\Http\Controllers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
use Timmylindh\LaravelBeanstalkWorker\SQSJob;
use Timmylindh\LaravelBeanstalkWorker\SQSQueueModifier;
use Timmylindh\LaravelBeanstalkWorker\SQSWorker;

class WorkerController
{
    /**
     * Execute the queued job.
     * Note that this method will never fail, as it will catch any exceptions and report them.
     * This way we control retries on our end.
     *
     * @param Container $container
     * @param Request $request
     * @param Worker $worker
     * @param SQSQueueModifier $queueModifier
     * @return void
     */
    public function queue(
        Container $container,
        Request $request,
        SQSWorker $worker,
        SQSQueueModifier $queueModifier,
    ) {
        $queue = $request->header('X-Aws-Sqsd-Queue');

        $jobData = [
            'body' => $request->getContent(),
            'id' => $request->header('X-Aws-Sqsd-Msgid'),
            'receiveCount' => $request->header('X-Aws-Sqsd-Receive-Count'),
        ];

        $job = new SQSJob($container, $queueModifier, $queue, $jobData);

        if ($job->hasTimedoutAndShouldFail()) {
            return response()->json([
                'status' => 'timeout-should-fail',
                'id' => $job->getJobId(),
            ]);
        }

        try {
            $worker->process(
                $queue,
                $job,
                new WorkerOptions(
                    backoff: config('worker.backoff'),
                    maxTries: config('worker.max_tries'),
                    timeout: config('worker.timeout'),
                ),
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['status' => 'ok', 'id' => $job->getJobId()]);
    }

    public function cron(Request $request)
    {
        $task = $request->headers->get('X-Aws-Sqsd-Taskname');

        if ($task !== 'cron') {
            return response()->json(['status' => 'ok']);
        }

        Artisan::call('schedule:run', [], new BufferedOutput());

        return response()->json(['status' => 'ok']);
    }
}
