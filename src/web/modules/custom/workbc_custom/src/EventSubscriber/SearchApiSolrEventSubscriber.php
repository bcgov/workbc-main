<?php

namespace Drupal\workbc_custom\EventSubscriber;

use Drupal\search_api_solr\Event\SearchApiSolrEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\search_api_solr\Event\PostConvertedQueryEvent;
use Solarium\QueryType\Select\Query\Query;

/**
 * Ensure charts settings are calculated when configurations are imported.
 */
class SearchApiSolrEventSubscriber implements EventSubscriberInterface {
  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      SearchApiSolrEvents::POST_CONVERT_QUERY => ['onPostConvertQuery', 1000],
    ];
  }

  /**
   * Event handler for SearchApiSolrEvents::POST_CONVERT_QUERY.
   */
  public function onPostConvertQuery(PostConvertedQueryEvent $event) {
    $sapi = $event->getSearchApiQuery();
    $tags = $sapi->getTags();
    if (in_array('explore_careers_search', $tags)) {
      /** @var Query $query */
      $query = $event->getSolariumQuery();

      // Invoke Solr highlighting.
      if (!in_array('search_api_autocomplete', $tags)) {
        $hl = $query->getHighlighting();
        $hl->setFields('tcngramm_X3b_en_field_job_titles');
        $hl->setSimplePrefix('<strong>');
        $hl->setSimplePostfix('</strong>');
        $hl->setSnippets(100);
      }

      // If we modified the query in workbc_custom_search_api_query_explore_careers_search_alter(), then
      // it's safe to further change the field_job_titles to fuzzy proximity search.
      if (in_array('explore_careers_search_modified', $tags)) {
        $q = $query->getQuery();
        $q = preg_replace_callback("/field\_job\_titles:\(\((.*?)\)\)/", function($matches) {
          return 'field_job_titles:(("' . str_replace('+', '', $matches[1]) . '"~5))';
        }, $q);
        $query->setQuery($q);
      }
    }
  }
}
