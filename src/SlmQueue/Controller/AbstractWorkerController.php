<?php

namespace SlmQueue\Controller;

use SlmQueue\Controller\Exception\WorkerProcessException;
use SlmQueue\Exception\ExceptionInterface;
use SlmQueue\Worker\WorkerInterface;
use SlmQueue\Queue\QueuePluginManager;
use Zend\Mvc\Controller\AbstractActionController;

/**
 * AbstractController
 */
abstract class AbstractWorkerController extends AbstractActionController
{
    /**
     * @var WorkerInterface
     */
    protected $worker;

    /**
     * @var QueuePluginManager
     */
    protected $queuePluginManager;

    /**
     * @param WorkerInterface    $worker
     * @param QueuePluginManager $queuePluginManager
     */
    public function __construct(WorkerInterface $worker, QueuePluginManager $queuePluginManager)
    {
        $this->worker             = $worker;
        $this->queuePluginManager = $queuePluginManager;
    }

    /**
     * Process a queue
     *
     * @return string
     * @throws WorkerProcessException
     */
    public function processAction()
    {
        $options = $this->params()->fromRoute();
        $name    = $options['queue'];
        $queue   = $this->queuePluginManager->get($name);

        try {
            $result = $this->worker->processQueue($queue, $options);
        } catch (ExceptionInterface $e) {
            throw new WorkerProcessException(
                'Caught exception while processing queue',
                $e->getCode(),
                $e
            );
        }

        return sprintf(
            "Finished worker for queue '%s' with %s jobs\n",
            $name,
            $result
        );
    }
}
