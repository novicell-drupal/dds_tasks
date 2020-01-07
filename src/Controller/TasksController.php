<?php

namespace Drupal\dds_tasks\Controller;

use Drupal;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Link;
use Drupal\Core\State\State;
use Drupal\Core\Url;
use Drupal\dds_tasks\TaskPluginManager;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class TasksController extends ControllerBase {

  protected $taskPluginManager;

  protected $state;

  public function __construct(TaskPluginManager $taskPluginManager, State $state) {
    $this->taskPluginManager = $taskPluginManager;
    $this->state = $state;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.tasks'),
      $container->get('state')
    );
  }

  public function collection() {
    $build = [];
    $plugin_definitions = $this->taskPluginManager->getDefinitions();
    $next_runs = $this->taskPluginManager->getTasks();

    $header = [
      'id' => $this->t('Id'),
      'name' => $this->t('Name'),
      'description' => $this->t('Description'),
      'interval' => $this->t('Interval'),
      'last_run' => $this->t('Last run'),
      'next_run' => $this->t('Next run'),
      'state' => $this->t('State'),
      'actions' => $this->t('Actions'),
    ];

    $rows = [];

    foreach ($plugin_definitions as $key => $plugin_definition) {
      $last_run = \Drupal::state()->get('tasks.task_' . $plugin_definition['id'] . '.last_run', 0);
      $next_run = $next_runs[$plugin_definition['id']] ?? time();

      $rows[] = [
        'id' => $plugin_definition['id'],
        'name' => $plugin_definition['name'],
        'description' => $plugin_definition['description'],
        'interval' => stripslashes($plugin_definition['interval']),
        'last_run' => $last_run ? $this->formatToDate($last_run) : '–',
        'next_run' => $this->formatToDate($next_run),
        'state' => $this->state->get('tasks.task_' . $plugin_definition['id'] . '.state'),
        'actions' => Link::createFromRoute('Run', 'dds_tasks.run_task', ['task' => $plugin_definition['id']]),
      ];
    }

    $build['tasks'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

  protected function formatToDate($timestamp) {
    $date = DrupalDateTime::createFromTimestamp($timestamp);
    return $date->format('d/m/y H:i:s');
  }

  public function runTaskAsBatch($task) {
    $batch = [
      'title' => $this->t('Running task...'),
      'operations' => [
        ['\Drupal\dds_tasks\Controller\TasksController::runTask', $task]
      ],
      'finished' => '',
    ];

    batch_set($batch);
    return [];
  }

  public static function runTask($task) {
    $return_url = Url::fromRoute('dds_tasks.collection')->toString();
    try {
      /** @var \Drupal\dds_tasks\TaskInterface $task_plugin */
      $task_plugin = \Drupal::service('plugin.manager.tasks')->createInstance($task);
      $task_plugin->run();

      Drupal::messenger()->addMessage('The task ' . $task . ' was successfully run.');
    }
    catch (Exception $e) {
      Drupal::messenger()->addError('The task failed while trying to run, with msg: ' . $e->getMessage());
      Drupal::logger('dds_tasks')->error($e->getMessage());
    }

    return new RedirectResponse($return_url);
  }

}
