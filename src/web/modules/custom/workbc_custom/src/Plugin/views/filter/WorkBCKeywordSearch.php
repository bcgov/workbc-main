<?php

namespace Drupal\workbc_custom\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\StringFilter;

/**
 * Filters by given list of node title options.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("workbc_node_keyword_search")
 */
class WorkBCKeywordSearch extends StringFilter {

  /**
   * {@inheritdoc}
   */
  public function operators() {
    return [
      'matches' => [
        'title' => $this->t('Matches'),
        'short' => $this->t('='),
        'method' => 'opEqual',
        'values' => 1,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    if (!$this->options['exposed']) {
      // Administrative value.
    }
    else {
      // Exposed value.
      if (empty($this->value) || empty($this->value[0])) {
        return;
      }
      $nids = $this->search($this->value);
      if (!empty($nids)) {
        $this->query->addWhere(0, 'node_field_data.nid', $nids, 'IN');
      }
      else {
        $this->query->addWhere(0, 'node_field_data.nid', [0], 'IN');
      }

    }
  }

  /**
   * Search API programmatically for the entered value.
   *
   * @see https://www.drupal.org/docs/8/modules/search-api/developer-documentation/executing-a-search-in-code
   */
  private function search() {
    $index = \Drupal\search_api\Entity\Index::load('career_profile_index');
    $query = $index->query();

    // Change the parse mode for the search.
    $parse_mode = \Drupal::service('plugin.manager.search_api.parse_mode')
      ->createInstance('direct');  // terms
    $parse_mode->setConjunction('OR');
    $query->setParseMode($parse_mode);

    // Set fulltext search keywords and fields.
    $query->keys(strtoupper($this->value));
    $query->setFulltextFields(['title', 'field_noc', 'field_job_titles']);

    // Add sorting.
    $query->sort('search_api_relevance', 'DESC');

    // Set one or more tags for the query.
    // @see hook_search_api_query_TAG_alter()
    // @see hook_search_api_results_TAG_alter()
    $query->addTag('workbc_explore_careers_search');

    // Execute the search.
    $results = $query->execute();
    return array_values(array_filter(array_map(function($r) {
      if (preg_match('/entity:node\/(\d+):/', $r->getId(), $match)) {
        return $match[1];
      }
      return false;
    }, $results ? $results->getResultItems() : [])));
  }

}
