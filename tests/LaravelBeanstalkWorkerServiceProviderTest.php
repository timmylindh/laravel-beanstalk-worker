<?php
use Illuminate\Queue\SqsQueue;
use Timmylindh\LaravelBeanstalkWorker\SQSWorker;

it('registers the SQSWorker', function () {
    expect(app(SQSWorker::class))->toBeInstanceOf(SQSWorker::class);
});

it('registers the SqsQueue', function () {
    expect(app(SqsQueue::class))->toBeInstanceOf(SqsQueue::class);
});
