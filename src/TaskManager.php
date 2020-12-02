<?php

namespace Drupal\dds_tasks;

use Cron\CronExpression;
use Drupal\Core\State\State;
use Drupal\dds_tasks\Event\TaskEvent;
use Drupal\dds_tasks\Event\TaskEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TaskManager {

  protected $taskPluginManager;

  protected $request_time;

  protected $state;

  protected $eventDispatcher;

  /**
   * Scheduler constructor.
   *
   * @param \Drupal\dds_tasks\TaskPluginManager                         $taskPluginManager
   * @param \Drupal\Core\State\State                                    $state
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   */
  public function __construct(TaskPluginManager $taskPluginManager, State $state, EventDispatcherInterface $eventDispatcher) {
    $this->taskPluginManager = $taskPluginManager;
    $this->request_time = \Drupal::time()->getRequestTime();
    $this->state = $state;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Run all tasks that is due.
   */
  public function run() {

    $tasks = $this->taskPluginManager->getTasks();

    if (!empty($tasks)) {
      /**
       * @var string  $task_id
       * @var integer $next_run
       */
      foreach ($tasks as $task_id => $next_run) {
        if ($this->request_time >= $next_run) {
          try {
            if ($this->runTask($task_id)) {
              $this->schedule($task_id);
              // Set state for last run.
              //moved to TaskBase
//              $this->state->set(
//                'tasks.task_' . $task_id . '.last_run', $this->request_time
//              );
            }
          }
          catch (\Exception $e) {
//            error_log($e->getMessage());
            \Drupal::logger('dds_tasks')
              ->error(
                'Could\'nt run task with id "' . $task_id . '" '
                . $e->getMessage()
              );
          }
        }
      }
    }
  }

  /**
   * @param $task_id
   *
   * @return boolean
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function runTask($task_id) {
    /** @var \Drupal\dds_tasks\TaskInterface $task */
    $task = $this->taskPluginManager->createInstance($task_id);
    if (!empty($task) && $task instanceof TaskInterface) {
      $this->setTaskStatus($task_id, $task, TaskEvents::RUNNING);
      if ($task->run()) {
        $this->setTaskStatus($task_id, $task, TaskEvents::FINISHED);
        return TRUE;
      }
      $this->setTaskStatus($task_id, $task, TaskEvents::ERROR);
    }
    return FALSE;
  }

  /**
   * Schedule a cron task by settings.
   *
   * @param $task_id
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function schedule($task_id) {
    $definition = $this->taskPluginManager->getDefinition($task_id);
    $interval = stripslashes($definition['interval']);
    if (!empty($interval)) {
      $cron = CronExpression::factory($interval);
      $this->taskPluginManager->setCacheItem(
        $task_id, $cron->getNextRunDate()
          ->getTimestamp()
      );
    }
  }

  /**
   * @param $id
   * @param \Drupal\dds_tasks\TaskInterface $task
   * @param $event
   */
  protected function setTaskStatus($id, TaskInterface $task, $event) {
    $key = 'tasks.task_' . $id . '.state';
    $message = '';

    switch ($event) {
      case TaskEvents::RUNNING:
        $message = 'Running';
        break;

      case TaskEvents::FINISHED:
        $message = 'Finished';
        break;

      case TaskEvents::ERROR:
        $message = 'Error occurred';
        break;
    }
    // Dispatch event.
    $this->eventDispatcher->dispatch($event, new TaskEvent($id, $task));
    $this->state->set($key, $message);
  }

}
