<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "search_index_noc",
 *   label = @Translation("Search Index NOC"),
 *   description = @Translation("An extra field to display NOC # including text 'NOC' for search indexing."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileSearchIndexNoc extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('NOC');
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
    // ksm("Search Index");
    $output = $entity->get("field_noc")->value;;
    return [
      ['#markup' => $output],
    ];
  }

}
