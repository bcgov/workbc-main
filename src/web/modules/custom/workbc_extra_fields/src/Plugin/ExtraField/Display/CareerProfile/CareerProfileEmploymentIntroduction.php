<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "career_employment_introduction",
 *   label = @Translation("Employment Introduction"),
 *   description = @Translation("An extra field to display the Employment introductory blurb."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */

class CareerProfileEmploymentIntroduction extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t('Employment');
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
    $introductions = $entity->get('field_introductions')?->referencedEntities();
    return empty($introductions) ? NULL : $introductions[0]->get('field_employment_introduction')?->view();
  }

}
