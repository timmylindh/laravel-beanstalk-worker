<?php

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\SqsQueue;
use Timmylindh\LaravelBeanstalkWorker\Listeners\LogFailedJob;
use Timmylindh\LaravelBeanstalkWorker\SQSWorker;

it('registers the SQSWorker', function () {
    expect(app(SQSWorker::class))->toBeInstanceOf(SQSWorker::class);
});

it('registers the SqsQueue', function () {
    expect(app(SqsQueue::class))->toBeInstanceOf(SqsQueue::class);
});

it('registers the JobFailed event listener', function () {
    $this->assertListenerIsAttachedToEvent(
        LogFailedJob::class,
        JobFailed::class,
    );
});
