<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarketOutlook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "labourmarketoutlook_test",
 *   label = @Translation("LMO Test"),
 *   description = @Translation("An extra field to display a test value."),
 *   bundles = {
 *     "node.lmo_report_2024",
 *   }
 * )
 */
class LabourMarketOutlookTest extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('LMO Test');
  }

  /**
   * {@inheritdoc}
   */
  public function getLabelDisplay() {

    return 'hidden';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity) {

    $output = "lmo-test";

    return [
      ['#markup' => $output ],
    ];
  }

}
