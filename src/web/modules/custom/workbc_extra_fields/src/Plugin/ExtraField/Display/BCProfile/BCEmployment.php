<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\BCProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "bc_employment",
 *   label = @Translation("Employment"),
 *   description = @Translation("An extra field to display region employment."),
 *   bundles = {
 *     "node.bc_profile",
 *   }
 * )
 */
class BCEmployment extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    $date1 = strtotime($this->getEntity()->ssot_data['monthly_labour_market_updates']['year'] . "-" . $this->getEntity()->ssot_data['monthly_labour_market_updates']['month']. "-01", 10);
    return array('#markup' => $this->t("Employment") . "<br>(" . date("M Y", $date1) . ")");
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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['monthly_labour_market_updates'])) {
      $field = 'total_jobs_' . $entity->ssot_data['region'];
      $options = array(
        'decimals' => 0,
        'na_if_empty' => TRUE,
      );
      $output = ssotFormatNumber($entity->ssot_data['monthly_labour_market_updates'][$field], $options);
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}
