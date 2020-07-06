<?php

namespace Drupal\dds_tasks;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\State\StateInterface;

abstract class TaskBase extends PluginBase implements TaskInterface, ContainerFactoryPluginInterface {

  /**
   * A state object, for storing plugin state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Runs the task and returns a boolean based on the result.
   *
   * @return bool
   *   A boolean indicating if the task ran successfully.
   */
  public function run() {
    try {
      $start = time();
      $this->task();
      $this->updateLastRun($start);
      return TRUE;
    }
    catch (\Exception $e) {
      \Drupal::logger('dds_task')->error($e->getMessage());
      return FALSE;
    }
  }

  /**
   * Returns the plugin state name.
   *
   * @return string
   *   Used for getting and setting plugin state.
   */
  protected function getPluginStateName(): string {
    return 'tasks.task_' . $this->getPluginId() . '.last_run';
  }

  /**
   * Get the timstamp for when the task was last run.
   *
   * @return int|null
   *   Returns a timestamp for last run or null.
   */
  protected function getLastRun() {
    return $this->getState()->get($this->getPluginStateName());
  }

  /**
   * Update the last updated run timestamp plugin state.
   *
   * @param int $time
   *   The timestamp for last run.
   */
  protected function updateLastRun($time) {
    $this->getState()->set($this->getPluginStateName(), $time);
  }

  /**
   * Get a state interface object, to handle the plugin state with.
   *
   * @return \Drupal\Core\State\StateInterface
   *   A State object.
   */
  public function getState(): StateInterface {
    if ($this->state === NULL) {
      /** @var \Drupal\Core\State\StateInterface state */
      $this->state = \Drupal::service('state');
    }
    return $this->state;
  }

}
