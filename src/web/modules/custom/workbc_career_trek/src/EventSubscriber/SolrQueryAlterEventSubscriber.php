<?php

namespace Drupal\workbc_career_trek\EventSubscriber;

use Drupal\search_api_solr\Event\PreQueryEvent;
use Drupal\search_api_solr\Event\SearchApiSolrEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alters the query where necessary to implement business logic.
 *
 * @package Drupal\workbc_career_trek\EventSubscriber
 */

class SolrQueryAlterEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiSolrEvents::PRE_QUERY => 'preQuery',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function preQuery(PreQueryEvent $event): void {
    $query = $event->getSearchApiQuery();
    $solarium_query = $event->getSolariumQuery();
    $keys = &$query->getKeys();
    $string = '';
    $current_uri = \Drupal::request()->getRequestUri();
    if(strpos($current_uri, '/career-trek/search-autocomplete/') !== FALSE) {
      $param = \Drupal::request()->query->all();
      if(!empty($param['q']) || $param['q'] === '0') {
        if(is_numeric($param['q'])) {
          $solarium_query->createFilterQuery('noc_prefix')
          ->setQuery('ss_career_noc_1:' . $param['q'] . '*');
        }else{

          // $solarium_query->createFilterQuery('string_contains')
          // ->setQuery('ss_episode_title_1:' . '*' . $param['q'] . '*');
        }
      }
    }
    if(!empty($keys)) {
      foreach($keys as $key) {
        if (is_array($key) && !empty($key['#full_numeric_prefix']) && isset($key['#value'])) {
          $solarium_query->createFilterQuery('noc_prefix')
                ->setQuery('ss_career_noc_1:' . $key['#value'] . '*');
        }elseif(is_array($key) && !empty($key['#contains']) && isset($key['#value'])) {
          if(empty($string)) {
            $string .= 'ss_episode_title_1:' . '*' . $key['#value'] . '*';
          }else{
            $string .= ' OR ss_episode_title_1:' . '*' . $key['#value'] . '*';

          }
        }
      }
      if(!empty($string)) {
        $solarium_query->createFilterQuery('string_contains')
        ->setQuery(str_replace("'", "", $string));
      }
    }
  }
}
