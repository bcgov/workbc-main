<?php

namespace Drupal\workbc_custom\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\StringFilter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api_solr\SearchApiSolrException;

/**
 * Filters by Search API keywords.
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
   * Provide a simple textfield for equality.
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    // We have to make some choices when creating this as an exposed
    // filter form. For example, if the operator is locked and thus
    // not rendered, we can't render dependencies; instead we only
    // render the form items we need.
    $which = 'all';
    if (!empty($form['operator'])) {
      $source = ':input[name="options[operator]"]';
    }
    if ($exposed = $form_state->get('exposed')) {
      $identifier = $this->options['expose']['identifier'];

      if (empty($this->options['expose']['use_operator']) || empty($this->options['expose']['operator_id'])) {
        // Exposed and locked.
        $which = in_array($this->operator, $this->operatorValues(1)) ? 'value' : 'none';
      }
      else {
        $source = ':input[name="' . $this->options['expose']['operator_id'] . '"]';
      }
    }

    if ($which == 'all' || $which == 'value') {
      $form['value'] = [
        '#type' => 'search_api_autocomplete',
        '#search_id' => 'explore_careers_autocomplete',
        '#additional_data' => [
          'display' => 'block_1',
          'arguments' => [],
          'filter' => 'search',
        ],
        '#title' => $this->t('Value'),
        '#size' => 30,
        '#default_value' => $this->value,
      ];
      if (!empty($this->options['expose']['placeholder'])) {
        $form['value']['#attributes']['placeholder'] = $this->options['expose']['placeholder'];
      }
      $user_input = $form_state->getUserInput();
      if ($exposed && !isset($user_input[$identifier])) {
        $user_input[$identifier] = $this->value;
        $form_state->setUserInput($user_input);
      }

      if ($which == 'all') {
        // Setup #states for all operators with one value.
        foreach ($this->operatorValues(1) as $operator) {
          $form['value']['#states']['visible'][] = [
            $source => ['value' => $operator],
          ];
        }
      }
    }

    if (!isset($form['value'])) {
      // Ensure there is something in the 'value'.
      $form['value'] = [
        '#type' => 'value',
        '#value' => NULL,
      ];
    }
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
      $this->query->addWhere(0, 'node_field_data.nid', empty($nids) ? [0] : $nids, 'IN');
      $this->view->search_api_results = array_combine($nids, array_keys($nids));
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
      ->createInstance('direct');
    $parse_mode->setConjunction('OR');
    $query->setParseMode($parse_mode);

    // Set fulltext search keywords and fields.
    $query->keys($this->value);
    $query->setFulltextFields(['title', 'field_noc', 'field_job_titles']);

    // Add sorting.
    $query->sort('search_api_relevance', 'DESC');
    $query->setOption('limit', 1000);

    // Set one or more tags for the query.
    // @see hook_search_api_query_TAG_alter()
    // @see hook_search_api_results_TAG_alter()
    $query->addTag('explore_careers_search');

    // Execute the search.
    try {
      $results = $query->execute();
    }
    catch (SearchApiSolrException $e) {
      $results = null;
    }
    return array_values(array_filter(array_map(function($r) {
      if (preg_match('/entity:node\/(\d+):/', $r->getId(), $match)) {
        return $match[1];
      }
      return false;
    }, $results ? $results->getResultItems() : [])));
  }
}
