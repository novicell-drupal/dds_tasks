<?php

namespace Drupal\dds_tasks\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an task annotation object.
 *
 * Plugin Namespace: Plugin\Task
 *
 * @Annotation
 */
class Task extends Plugin {

  /**
   * The task plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The name of the class
   *
   * @var string
   */
  public $name;

  /**
   * The description of the class
   *
   * @var string
   */
  public $description;

  /**
   * The interval of when the task should run
   *
   * @var string
   */
  public $interval;

}