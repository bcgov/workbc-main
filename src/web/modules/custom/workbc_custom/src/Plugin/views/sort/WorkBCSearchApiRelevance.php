<?php

namespace Drupal\workbc_custom\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;
use Drupal\Core\Cache\UncacheableDependencyTrait;

/**
 * Sorts by Search API order of results.
 *
 * @ViewsSort("workbc_node_keyword_search")
 */
class WorkBCSearchApiRelevance extends SortPluginBase {

  use UncacheableDependencyTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {}

  /**
   * {@inheritdoc}
   */
  public function postExecute(&$values) {
    // This list is set in WorkBCKeywordSearch as a result of the keyword search.
    // It is ordered by relevance in the search index.
    if (empty($this->view->search_api_results)) return;
    $nids = array_combine(array_column($this->view->search_api_results, 'nid'), array_keys($this->view->search_api_results));

    // Reorder in case no sort order was specified (aka Relevance).
    // This is detected by all exposed sorts being activated (probably due to Views bug).
    $order = $this->view->build_info['query']->getOrderBy();
    if (empty($order)) {
      usort($values, function($a, $b) use ($nids) {
        return $nids[$a->nid] - $nids[$b->nid];
      });
    }
  }

}
