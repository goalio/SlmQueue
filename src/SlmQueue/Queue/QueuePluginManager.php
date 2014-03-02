<?php

namespace SlmQueue\Queue;

use Zend\ServiceManager\AbstractPluginManager;
use Zend\ServiceManager\DelegatorFactoryInterface;

/**
 * QueuePluginManager
 */
class QueuePluginManager extends AbstractPluginManager
{
    /**
     * {@inheritDoc}
     */
    public function validatePlugin($plugin)
    {
        if ($plugin instanceof QueueInterface || $plugin instanceof DelegatorFactoryInterface) {
            return; // we're okay!
        }

        throw new Exception\RuntimeException(sprintf(
            'Plugin of type %s is invalid; must implement SlmQueue\Queue\QueueInterface',
            (is_object($plugin) ? get_class($plugin) : gettype($plugin))
        ));
    }
}
