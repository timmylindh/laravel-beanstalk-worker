<?php

namespace Timmylindh\LaravelBeanstalkWorker;

use Illuminate\Queue\SqsQueue;
use Illuminate\Support\InteractsWithTime;

class SQSQueueModifier
{
    use InteractsWithTime;

    public function __construct(private SqsQueue $queue)
    {
    }

    public function sendArray(
        array $payload,
        string $queue,
        \DateTimeInterface|\DateInterval|int $delay,
    ) {
        $payload = json_encode($payload);

        return $this->queue->getSqs()->sendMessage([
            'QueueUrl' => $this->queue->getQueue($queue),
            'MessageBody' => $payload,
            'DelaySeconds' => $this->secondsUntil($delay),
        ]);
    }
}
