<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

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

    $noc = $entity->get("field_noc")->getString();

    $programs = epbcGetPrograms($noc);
    $text = "";
    if (!empty($programs)) {
      $text .= "<p>The following program areas are related to this occupation:</p>";
      $text .= "<ul>";
      foreach($programs as $program) {
        $text .= "<li>" . $program . "</li>";
      }
      $text .= "</ul>";
    }
    $output = $text;

    return ['#markup' => $output];
  }

}
