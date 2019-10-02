<?php

namespace Drupal\dds_tasks;


abstract class TaskBase implements TaskInterface {

  /**
   *
   * @return bool
   */
  public function run() {
    try {
      $this->task();
      return TRUE;
    } catch (\Exception $e) {
      \Drupal::logger('dds_task')->error($e->getMessage());
      return FALSE;
    }
  }
}
