<?php

namespace SlmQueue\Worker;

use SlmQueue\Job\JobInterface;
use SlmQueue\Queue\QueueInterface;
use Zend\EventManager\Event;

/**
 * WorkerEvent
 */
class WorkerEvent extends Event
{
    /**
     * Various events you can listen to
     */
    const EVENT_PROCESS_QUEUE_PRE  = 'processQueue.pre';
    const EVENT_PROCESS_QUEUE_POST = 'processQueue.post';
    const EVENT_PROCESS_JOB_PRE    = 'processJob.pre';
    const EVENT_PROCESS_JOB_POST   = 'processJob.post';
    const EVENT_FORK_WORKER_PRE    = 'forkWorker.pre';
    const EVENT_FORK_WORKER_POST   = 'forkWorker.post';

    /**
     * Status for unstarted jobs
     */
    const JOB_STATUS_UNKNOWN             = 0;

    /**
     * Status for successfully finished job
     */
    const JOB_STATUS_SUCCESS             = 1;
 
    /**
     * Status for job that has failed and cannot be processed again
     */
    const JOB_STATUS_FAILURE             = 2;
 
    /**
     * Status for job that has failed but can be processed again
     */
    const JOB_STATUS_FAILURE_RECOVERABLE = 4;

    /**
     * @var QueueInterface
     */
    protected $queue;

    /**
     * @var JobInterface|null
     */
    protected $job;

    /**
     * Result of the processed job.
     * @var int
     */
    protected $result;

    /**
     * @param QueueInterface $queue
     */
    public function __construct(QueueInterface $queue)
    {
        $this->queue = $queue;
    }

    /**
     * @param  JobInterface $job
     * @return void
     */
    public function setJob(JobInterface $job)
    {
        $this->job = $job;
    }

    /**
     * @return JobInterface|null
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     * @return QueueInterface
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @param int $result
     */
    public function setResult($result)
    {
        $this->result = $result;
    }

    /**
     * @return int|null
     */
    public function getResult()
    {
        return $this->result;
    }
}
