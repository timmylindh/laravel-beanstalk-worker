<?php

namespace Timmylindh\LaravelBeanstalkWorker;

use Illuminate\Queue\Jobs\Job;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Container\Container;

class SQSJob extends Job implements JobContract
{
    /**
     * The Amazon SQS job instance.
     *
     * @var array
     */
    protected $job;
    protected array $payload;
    protected SQSQueueModifier $queueModifier;

    public function __construct(
        Container $container,
        SQSQueueModifier $queueModifier,
        string $queue,
        array $job,
    ) {
        $this->job = $job;
        $this->queue = $queue;
        $this->queueModifier = $queueModifier;
        $this->container = $container;
        $this->payload = parent::payload();
    }

    /**
     * Mark the job as deleted and let the AWS daemon delete it
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();
    }

    /**
     * To release the job we queue a copy of the job with an increased attempts count.
     * The original job is then deleted automatically by the sucessful return to the daemon.
     *
     * @param  int   $delay
     * @return void
     */
    public function release($delay = 0)
    {
        parent::release($delay);

        $payload = $this->payload();
        $payload['attempts'] = $this->attempts();

        $this->queueModifier->sendArray($payload, $this->queue, $delay);
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        return (int) (($this->payload()['attempts'] ?? 0) +
            $this->getSqsReceiveCount());
    }

    /**
     * Get the number of times the job has been received.
     *
     * @return int
     */
    public function getSqsReceiveCount()
    {
        return $this->job['receiveCount'];
    }

    /**
     * Determine if the job should has timedout and should fail.
     *
     * @return bool
     */
    public function hasTimedoutAndShouldFail()
    {
        return $this->getSqsReceiveCount() > 1 && $this->shouldFailOnTimeout();
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->job['id'];
    }

    /**
     * Get the raw body string
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->job['body'];
    }

    /**
     * Get the decoded body of the job.
     *
     * @return array
     */
    public function payload()
    {
        return $this->payload;
    }
}
