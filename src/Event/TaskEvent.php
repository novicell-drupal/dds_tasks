<?php

namespace Drupal\dds_tasks\Event;

use Drupal\dds_tasks\TaskInterface;
use Symfony\Contracts\EventDispatcher\Event;

class TaskEvent extends Event {

  protected $id;

  protected $task;

  public function __construct($task_id, TaskInterface $task) {
    $this->id = $task_id;
    $this->task = $task;
  }
}
