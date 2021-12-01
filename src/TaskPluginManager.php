<?php

namespace Drupal\dds_tasks;

use Cron\CronExpression;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

class TaskPluginManager extends DefaultPluginManager {

  protected $taskCacheBackend;

  /**
   * Constructs a TaskManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Cache\CacheBackendInterface $taskCacheBackend
   *   Cache backend instance to use for tasks.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, CacheBackendInterface $taskCacheBackend) {
    parent::__construct('Plugin/Task',
      $namespaces,
      $module_handler,
      'Drupal\dds_tasks\TaskInterface',
      'Drupal\dds_tasks\Annotation\Task');
    $this->alterInfo('dds_tasks');
    $this->setCacheBackend($cache_backend, 'tasks');
    $this->taskCacheBackend = $taskCacheBackend;
  }

  public function getTasks() {
    $cached = $this->taskCacheBackend->get($this->cacheKey . ':tasks');

    $rebuild = FALSE;
    if (empty($cached)) {
      $rebuild = TRUE;
    } elseif (!$this->checkCachedTasks($cached->data)) {
      $rebuild = TRUE;
    }

    if ($rebuild) {
      $this->setCachedTasks();
      $cached = $this->taskCacheBackend->get($this->cacheKey . ':tasks');
    }
    return $cached->data ?? [];
  }

  /**
   * Check if the tasks list is correct.
   *
   * @param array $tasks
   *   List of tasks.
   *
   * @return bool
   *   TRUE if everything is fine, otherwise FALSE.
   */
  public function checkCachedTasks(array $tasks) {
    $definitions = $this->getDefinitions();
    $result = TRUE;
    foreach ($definitions as $key => $definition) {
      if (!isset($tasks[$definition['id']])) {
        $result = FALSE;
      }
      if (!is_int($tasks[$definition['id']])) {
        $result = FALSE;
      }
    }
    return $result;
  }

  public function setCacheItem($id, $run_date) {
    $tasks = $this->getTasks();
    $tasks[$id] = $run_date;
    $this->saveCache($tasks);
  }

  public function setCachedTasks() {
    $definitions = $this->getDefinitions();

    $tasks = [];
    foreach ($definitions as $key => $definition) {
      $cron = new CronExpression(stripslashes($definition['interval']));
      $tasks[$definition['id']] = $cron->getNextRunDate()->getTimestamp();
    }

    $this->saveCache($tasks);
  }

  public function getCachedItem($id) {
    $cached = $this->taskCacheBackend->get($this->cacheKey . ':tasks');
    return $cached->data[$id];
  }

  /**
   * @param $tasks
   */
  public function saveCache($tasks) {
    $this->taskCacheBackend->set($this->cacheKey . ':tasks', $tasks);
  }
}
