<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\RegionProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "region_unemployment_rate",
 *   label = @Translation("Unemployment Rate"),
 *   description = @Translation("An extra field to display unemployment rate."),
 *   bundles = {
 *     "node.region_profile",
 *   }
 * )
 */
class RegionUnemploymentRate extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    $date1 = strtotime($this->getEntity()->ssot_data['monthly_labour_market_updates']['year'] . "-" . $this->getEntity()->ssot_data['monthly_labour_market_updates']['month']. "-01", 10);
    return array('#markup' => $this->t("Unemployment Rate") . "<br>(" . date("M Y", $date1) . ")");
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

    $options = array(
      'decimals' => 1,
      'suffix' => "%",
      'na_if_empty' => TRUE,
    );
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['monthly_labour_market_updates'])) {
      $field = 'unemployment_pct_' . $entity->ssot_data['region'];
      if (!is_null($entity->ssot_data['monthly_labour_market_updates'][$field])) {
        $output = ssotFormatNumber($entity->ssot_data['monthly_labour_market_updates'][$field], $options);
      }
      else {
        $output = "not available";
      }

    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}
