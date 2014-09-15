<?php

namespace SlmQueue\Worker;

use SlmQueue\Exception;
use SlmQueue\Job\JobInterface;
use SlmQueue\Options\WorkerOptions;
use SlmQueue\Queue\QueueInterface;
use SlmQueue\Queue\QueueAwareInterface;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerInterface;

/**
 * AbstractWorker
 */
abstract class AbstractWorker implements WorkerInterface, EventManagerAwareInterface
{
    const PCNTL_FORK_NOT_SUPPORTED = -1;

    /**
     * @var EventManagerInterface
     */
    protected $eventManager;

    /**
     * @var bool
     */
    protected $stopped = false;

    /**
     * @var WorkerOptions
     */
    protected $options;

    /**
     * @var int Process ID of child worker processes.
     */
    protected $child = null;

    /**
     * Constructor
     *
     * @param WorkerOptions $options
     */
    public function __construct(WorkerOptions $options)
    {
        $this->options = $options;

        // Listen to the signals SIGTERM and SIGINT so that the worker can be killed properly. Note that
        // because pcntl_signal may not be available on Windows, we needed to check for the existence of the function
        if (function_exists('pcntl_signal')) {
            declare(ticks = 1);
            pcntl_signal(SIGTERM, array($this, 'handleSignal'));
            pcntl_signal(SIGINT, array($this, 'handleSignal'));
        }


    }

    /**
     * {@inheritDoc}
     */
    public function processQueue(QueueInterface $queue, array $options = array())
    {
        $eventManager = $this->getEventManager();
        $count        = 0;

        $workerEvent = new WorkerEvent($queue);
        $eventManager->trigger(WorkerEvent::EVENT_PROCESS_QUEUE_PRE, $workerEvent);

        while (true) {
            // Check for external stop condition
            if ($this->isStopped()) {
                break;
            }

            $job = $queue->pop($options);

            // The queue may return null, for instance if a timeout was set
            if (!$job instanceof JobInterface) {
                // Check for internal stop condition
                if ($this->isMaxMemoryExceeded()) {
                    break;
                }
                continue;
            }

            $workerEvent->setJob($job);
            $workerEvent->setResult(WorkerEvent::JOB_STATUS_UNKNOWN);

            $eventManager->trigger(WorkerEvent::EVENT_FORK_WORKER_PRE, $workerEvent);
            $this->child = $this->fork();

            if($this->child === 0 || $this->child === self::PCNTL_FORK_NOT_SUPPORTED) {
                // child process or unforked
                $workerEvent->setParam('exception', null);
                $eventManager->trigger(WorkerEvent::EVENT_PROCESS_JOB_PRE, $workerEvent);

                $exception = $this->processJob($job, $queue);

                $workerEvent->setParam('exception', $exception);
                $eventManager->trigger(WorkerEvent::EVENT_PROCESS_JOB_POST, $workerEvent);

                if($this->child === 0) {
                    // Exit forked child process
                    exit((int) $result);
                }
            }
            else {
                // Parent process
                pcntl_wait($status);
                $exitStatus = pcntl_wexitstatus($status);
                $workerEvent->setParam('exitStatus', $exitStatus);
            }

            $eventManager->trigger(WorkerEvent::EVENT_FORK_WORKER_POST, $workerEvent);
            $this->child = null;

            $count++;
            $workerEvent->setParam('count', $count);

            // Check for internal stop condition
            if ($this->isMaxRunsReached($count) || $this->isMaxMemoryExceeded()) {
                break;
            }
        }

        $eventManager->trigger(WorkerEvent::EVENT_PROCESS_QUEUE_POST, $workerEvent);

        return $count;
    }

    /**
     * {@inheritDoc}
     */
    public function setEventManager(EventManagerInterface $eventManager)
    {
        $eventManager->setIdentifiers(array(
            get_called_class(),
            'SlmQueue\Worker\WorkerInterface'
        ));

        $this->eventManager = $eventManager;
    }

    /**
     * {@inheritDoc}
     */
    public function getEventManager()
    {
        if (null === $this->eventManager) {
            $this->setEventManager(new EventManager());
        }

        return $this->eventManager;
    }

    /**
     * Check if the script has been stopped from a signal
     *
     * @return bool
     */
    public function isStopped()
    {
        return $this->stopped;
    }

    /**
     * Did worker exceed the threshold for memory usage?
     *
     * @return bool
     */
    public function isMaxMemoryExceeded()
    {
        return memory_get_usage() > $this->options->getMaxMemory();
    }

    /**
     * Is the worker about to exceed the threshold for the number of jobs allowed to run?
     *
     * @param $count current count of executed jobs
     * @return bool
     */
    public function isMaxRunsReached($count)
    {
        return $count >= $this->options->getMaxRuns();
    }

    /**
     * Handle the signal
     *
     * @param int $signo
     */
    public function handleSignal($signo)
    {
        switch($signo) {
            case SIGTERM:
            case SIGINT:
                $this->stopped = true;
                break;
        }
    }

    /**
     * Fork the current process
     *
     * @return int 0 for child, pid for parent, -1 if error
     */
    protected function fork()
    {
        if(!function_exists('pcntl_fork')) {
            return self::PCNTL_FORK_NOT_SUPPORTED;
        }

        $pid = pcntl_fork();
        if($pid === -1) {
            throw new Exception\RuntimeException('Unable to fork child worker.');
        }

        return $pid;
    }
}
