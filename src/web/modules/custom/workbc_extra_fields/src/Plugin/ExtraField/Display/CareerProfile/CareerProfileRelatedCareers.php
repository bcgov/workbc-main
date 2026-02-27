<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "related_careers",
 *   label = @Translation("[SSOT] Related Careers"),
 *   description = @Translation("An extra field to display related careers."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileRelatedCareers extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Related Careers');
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
    if (!empty($entity->ssot_data) && !empty($entity->ssot_data['career_related'])) {
      $careers = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadByProperties([
          'field_noc' => array_column($entity->ssot_data['career_related'], 'noc_related'),
        ]);
      usort($careers, function($c1, $c2) {
        return intval($c1->get('field_noc')->value) - intval($c2->get('field_noc')->value);
      });
      $output = [
        '#theme' => 'item_list',
        '#items' => array_map(function($career) {
          return Link::fromTextAndUrl(t('@title (NOC @noc)', [
            '@title' => $career->getTitle(),
            '@noc' => $career->get('field_noc')->value,
          ]), Url::fromRoute('entity.node.canonical', ['node' => $career->id()]));
        }, $careers),
        '#list_type' => 'ul',
        '#attributes' => [
          'class' => 'career-content-related-careers',
          'data-static-load-more-items' => 'data-static-load-more-items'
        ]
      ];
    }
    else {
      $output = ['#markup' => WORKBC_EXTRA_FIELDS_NOT_AVAILABLE];
    }
    return $output;
  }

}
