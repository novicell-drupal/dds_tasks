<?php

namespace Drupal\dds_tasks;

use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;

/**
 * Provides the services and methods needed to process a queue worker.
 *
 * @package Drupal\hede_subscription
 */
trait ProcessQueueTrait {

  /**
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueManager;

  /**
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Processes a queue worker with the given name.
   *
   * @param string $queue_name
   *   The name of the queue worker to process.
   * @param int $lease_time
   *   The lease time of the queue worker.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @see \Drupal\Core\Cron::processQueues()
   */
  protected function processQueueByName($queue_name, $lease_time = 60) {
    $queue_info = $this->getQueueManager()->getDefinition($queue_name);
    // Create the queue.
    $this->getQueueFactory()->get($queue_name)->createQueue();
    $queue_worker = $this->getQueueManager()->createInstance($queue_name);
    // Set run time of this queue:
    $end = time() + $lease_time;
    $queue = $this->queueFactory->get($queue_name);
    while (time() < $end && ($item = $queue->claimItem($lease_time))) {
      try {
        $queue_worker->processItem($item->data);
        $queue->deleteItem($item);
      }
      catch (RequeueException $e) {
        // The worker requested the task be immediately requeued.
        $queue->releaseItem($item);
      }
      catch (SuspendQueueException $e) {
        // If the worker indicates there is a problem with the whole queue,
        // release the item and skip to the next queue.
        $queue->releaseItem($item);

        watchdog_exception('dds_tasks', $e);

        // Since we're only working on one queue type, we can't skip to next
        // queue, so stop processing altogether.
        return;
      }
      catch (\Exception $e) {
        // In case of any other kind of exception, log it and leave the item
        // in the queue to be processed again later.
        watchdog_exception('dds_tasks', $e);
      }
    }
  }

  /**
   * Gets the queue manager plugin service.
   *
   * @return \Drupal\Core\Queue\QueueWorkerManagerInterface
   *   The queue manager plugin service.
   */
  protected function getQueueManager() {
    if (!$this->queueManager) {
      $this->queueManager = \Drupal::service('plugin.manager.queue_worker');
    }

    return $this->queueManager;
  }

  /**
   * Gets the queue factory service.
   *
   * @return \Drupal\Core\Queue\QueueFactory
   *   The queue factory service.
   */
  protected function getQueueFactory() {
    if (!$this->queueFactory) {
      $this->queueFactory = \Drupal::service('queue');
    }

    return $this->queueFactory;
  }

}
