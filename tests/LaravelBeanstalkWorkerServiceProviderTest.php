<?php
use Illuminate\Queue\Worker;
use Illuminate\Queue\SqsQueue;

it('registers the Worker', function () {
    expect(app(Worker::class))->toBeInstanceOf(Worker::class);
});

it('registers the SqsQueue', function () {
    expect(app(SqsQueue::class))->toBeInstanceOf(SqsQueue::class);
});
