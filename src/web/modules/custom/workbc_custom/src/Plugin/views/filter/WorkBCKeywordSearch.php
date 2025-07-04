<?php

namespace Drupal\workbc_custom\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\StringFilter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api\Query\Query;
use Drupal\search_api\Query\ResultSetInterface;

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
      if (empty($this->value) && $this->value != 0) {
        return;
      }
      $this->view->search_api_results = $this->search($this->value);
      $this->query->addWhere(
        0, 'node_field_data.nid',
        empty($this->view->search_api_results) ? [0] : array_column($this->view->search_api_results, 'nid'),
        'IN'
      );
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
    // Convert curly quotations to regular quotations.
    // https://stackoverflow.com/a/6610752/209184
    $query->keys(iconv('UTF-8', 'ASCII//TRANSLIT', $this->value));
    $query->setFulltextFields(['title', 'field_noc', 'field_job_titles']);

    // Add sorting and limiting.
    $query->sort('search_api_relevance', 'DESC');
    $sorts =& $query->getSorts();
    $sorts['field_noc'] = 'ASC';
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
      \Drupal::logger('workbc')->error($e->getMessage());
      return [];
    }
    return array_values(array_filter(array_map(function($item) use ($results, $query) {
      if (preg_match('/entity:node\/(\d+):/', $item->getId(), $match)) {
        return [
          'nid' => $match[1],
          'excerpts' => $this->parseExcerpt($item, $results, $query)
        ];
      }
      return false;
    }, $results ? $results->getResultItems() : [])));
  }

  private function parseExcerpt(\Drupal\search_api\Item\Item $item, ResultSetInterface $results, Query $query) {
    $doc = $item->getExtraData('search_api_solr_document');
    $highlight = $results->getExtraData('search_api_solr_response')['highlighting'];
    $key = $doc['hash'] . '-' . $item->getIndex()->id() . '-' . $item->getId();
    if (!array_key_exists('tcngramm_X3b_en_field_job_titles', $highlight[$key])) return [];
    if (in_array('explore_careers_search_modified', $query->getTags())) {
      // In case it's our "safe" use case, make sure the number of highlighted keywords match the number of query keywords.
      return array_filter($highlight[$key]['tcngramm_X3b_en_field_job_titles'], function ($title) use ($query) {
        return substr_count($title, '<strong>') >= substr_count($query->getKeys(), '+');
      });
    }
    return $highlight[$key]['tcngramm_X3b_en_field_job_titles'];
  }
}
