<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Profides a page title field for use with content type manage display.
 *
 * @ExtraFieldDisplay(
 *   id = "workbc_page_title",
 *   label = @Translation("Title"),
 *   description = @Translation("An extra field to display page title."),
 *   bundles = {
 *     "node.*",
 *   }
 * )
 */
class ContentTypePageTitle extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return "Title";
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

      $output = $entity->getTitle();


    return [
      ['#markup' => $output],
    ];
  }

}
