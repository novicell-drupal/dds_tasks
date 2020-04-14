<?php

namespace Drupal\dds_tasks;


use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\State\State;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class TaskBase extends PluginBase implements TaskInterface, ContainerFactoryPluginInterface {

  /**
   * @var State
   */
  protected $state;

  /**
   *
   * @return bool
   */
  public function run() {
    try {
      $start = time();
      $this->task();
      $this->updateLastRun($start);
      return TRUE;
    } catch (\Exception $e) {
      \Drupal::logger('dds_task')->error($e->getMessage());
      return FALSE;
    }
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, State $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->state = $state;
  }

  protected function getLastRun() {
    return $this->state->get('tasks.task_'.$this->getPluginId().'.last_run');
  }

  protected function updateLastRun($time) {
    $this->state->set('tasks.task_'.$this->getPluginId().'.last_run', $time);
  }


  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state')
    );
  }
}
