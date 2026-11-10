<?php

namespace Drupal\workbc_ssot\Plugin\views\filter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters the SSoT upload log by dataset.
 *
 * Options are the SSoT endpoints known to the `sources` API (plus anything
 * already present in the log). The `dataset_name` column may hold several
 * comma-separated endpoints, so matching is done per list element.
 */
#[ViewsFilter("workbc_ssot_dataset")]
class SsotDatasetFilter extends InOperator {

  const CACHE_ID = 'workbc_ssot:dataset_options';
  const CACHE_TTL = 3600;

  protected Connection $connection;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->connection = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (isset($this->valueOptions)) {
      return $this->valueOptions;
    }
    $cache = \Drupal::cache()->get(static::CACHE_ID);
    if ($cache) {
      $this->valueOptions = $cache->data;
      return $this->valueOptions;
    }

    $logged = $this->connection->select('workbc_ssot_log', 'l')
      ->fields('l', ['dataset_name'])
      ->distinct()
      ->execute()
      ->fetchCol();
    $this->valueOptions = static::buildOptions(querySSoT('sources') ?? [], $logged);

    \Drupal::cache()->set(static::CACHE_ID, $this->valueOptions, time() + static::CACHE_TTL, ['workbc_ssot_log']);
    return $this->valueOptions;
  }

  /**
   * Build the option list from SSoT sources and existing log values.
   *
   * @param array $sources
   *   Rows from the SSoT `sources` endpoint.
   * @param string[] $logged
   *   Distinct `dataset_name` values from the log (possibly comma-separated).
   *
   * @return array
   *   endpoint => endpoint, sorted.
   */
  public static function buildOptions(array $sources, array $logged = []): array {
    $options = [];
    foreach ($sources as $source) {
      if (!empty($source['endpoint'])) {
        $options[$source['endpoint']] = $source['endpoint'];
      }
    }
    foreach ($logged as $names) {
      foreach (static::split((string) $names) as $name) {
        $options[$name] = $name;
      }
    }
    ksort($options);
    return $options;
  }

  /**
   * Split a comma-separated dataset_name into trimmed, non-empty parts.
   */
  public static function split(string $value): array {
    return array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
  }

  /**
   * Clear the cached option list; call after an upload adds a dataset.
   */
  public static function resetOptions(): void {
    Cache::invalidateTags(['workbc_ssot_log']);
  }

  /**
   * {@inheritdoc}
   */
  public function operators() {
    $operators = parent::operators();
    return array_intersect_key($operators, array_flip(['in', 'not in']));
  }

  /**
   * {@inheritdoc}
   *
   * Match when any element of the comma-separated dataset_name equals one of
   * the selected values (or none of them, for "not in").
   */
  protected function opSimple() {
    if (empty($this->value)) {
      return;
    }
    $this->ensureMyTable();
    $field = "$this->tableAlias.$this->realField";
    $connection = $this->query->getConnection();

    $negate = $this->operator === 'not in';
    $condition = $connection->condition($negate ? 'AND' : 'OR');
    [$eq, $like] = $negate ? ['<>', 'NOT LIKE'] : ['=', 'LIKE'];
    foreach (array_values($this->value) as $value) {
      $escaped = $connection->escapeLike($value);
      $condition->condition($field, $value, $eq);
      $condition->condition($field, "$escaped,%", $like);
      $condition->condition($field, "%,$escaped", $like);
      $condition->condition($field, "%,$escaped,%", $like);
    }
    $this->query->addWhere($this->options['group'], $condition);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['workbc_ssot_log']);
  }

}
