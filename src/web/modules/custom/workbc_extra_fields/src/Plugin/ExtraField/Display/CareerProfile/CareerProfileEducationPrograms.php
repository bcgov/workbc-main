<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Example Extra field Display.
 *
 * @ExtraFieldDisplay(
 *   id = "education_programs",
 *   label = @Translation("Education Programs"),
 *   description = @Translation("An extra field to display enterprise data."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileEducationPrograms extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Education programs in B.C.');
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
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['epbc_nocs'])) {
      // Show the EPBC sub-NOCS if there are any.
      // Otherwise, show the main NOC with the incoming label (which may be different from the official career profile title).
      $output = [
        '#theme' => 'item_list',
        '#items' => array_map(function ($v) {
          return Link::fromTextAndUrl($v['sub_noc_label_en'] ?? $v['label_en'], Url::fromUri('https://www.educationplannerbc.ca/find-your-path/results/career/noc-' . ($v['sub_noc'] ?? $v['noc_2021']) . '#Post-secondary-programs', ['attributes' => [
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
          ]]));
        }, array_filter($entity->ssot_data['epbc_nocs'], function($v) use($entity) {
          if (count($entity->ssot_data['epbc_nocs']) > 1 && empty($v['sub_noc'])) {
            return false;
          }
          return true;
        })),
        '#list_type' => 'ul',
      ];
    }
    else {
      $output = ['#markup' => WORKBC_EXTRA_FIELDS_NOT_AVAILABLE];
    }
    return $output;
  }
}
