<?php

namespace Drupal\workbc_career_trek\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\search_api\Event\IndexingItemsEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\node\Entity\Node;

/**
 * Event subscriber to alter Search API indexed data.
 */
class SearchApiIndexAlterSubscriber implements EventSubscriberInterface {

  /**
   * Alters indexed items before they are sent to Solr.
   *
   * @param \Drupal\search_api\Event\IndexingItemsEvent $event
   */
  public function onIndexingItems(IndexingItemsEvent $event) {
    $items = $event->getItems();

    foreach ($items as $item) {
      // Get the entity being indexed.
      $entity = $item->getOriginalObject()->getValue();

      if ($entity instanceof Node) {
        if ($entity->hasField('field_noc')) {
            $noc = $entity->get('field_noc')->value;
            $ssot = ssotCareerProfile($noc);
            // $regions = [];
            $fields = $item->getFields();

            if(isset($ssot['wages']['calculated_median_annual_salary']) && !empty($ssot['wages']['calculated_median_annual_salary'])) {
                $fields['annual_salary']->setValues([$ssot['wages']['calculated_median_annual_salary']]);
            }
            if(isset($ssot['education']['teer']) && !empty($ssot['education']['teer'])) {
                // if(is_numeric($ssot['education']['teer'])) {
                    // $ssot['education']['teer'] += 1;
                // }
                $fields['minimum_education']->setValues([
                    $ssot['education']['teer']
                ]);
            }
            // if(isset($ssot['high_opportunity_occupations'])) {
            //     foreach($ssot['high_opportunity_occupations'] as $rows) {
            //         $regions[] = $rows['region'];
            //     }
            // }
            // if(isset($ssot['carrer_trek'][0]) && !empty($ssot['carrer_trek'][0])) {
            //     $fields['regions']->setValues([
            //         $ssot['carrer_trek'][0]['region']
            //     ]);
            //     $fields['location']->setValues([
            //         $ssot['carrer_trek'][0]['location']
            //     ]);
            // }else{
            //     $fields['regions']->setValues([
            //         'none'
            //     ]);
            //     $fields['location']->setValues([
            //        'none'
            //     ]);
            // }

        }
      }
    }
  }


  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      SearchApiEvents::INDEXING_ITEMS => ['onIndexingItems'],
    ];
  }
}
