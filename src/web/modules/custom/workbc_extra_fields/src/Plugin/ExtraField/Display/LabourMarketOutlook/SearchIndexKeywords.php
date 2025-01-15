<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarketOutlook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "lmo_search_index_keywords",
 *   label = @Translation("LMO Search Index Keywords"),
 *   description = @Translation("An extra field to display LMO keywords for search indexing."),
 *   bundles = {
 *     "node.lmo_report_2024",
 *   }
 * )
 */
class SearchIndexKeywords extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('');
  }

  /**
   * {@inheritdoc}
   */
  public function getLabelDisplay() {

    return 'above';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity) {
    $output = "Labour Market Outlook, Labour Report, LMO, labour data";
    return [
      ['#markup' => $output],
    ];
  }

}
