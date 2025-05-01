<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;
use Drupal\image\Entity\ImageStyle;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "career_trek_profile_skills",
 *   label = @Translation("Career Trek Profile Skills"),
 *   description = @Translation("An extra field to display skills."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileCareerTrekSkills extends ExtraFieldDisplayFormattedBase {

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
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['skills'])) {

      $skills = $entity->ssot_data['skills'];
      $filteredSkills = array_filter($skills, function($var) {
        return intval($var['importance']) > 0;
      });
      array_multisort(
        array_column($filteredSkills, 'importance'), SORT_DESC,
        array_column($filteredSkills, 'proficiency'), SORT_DESC,
        array_column($filteredSkills, 'skills_competencies'), SORT_ASC,
        $filteredSkills
      );
      $limitedSkills = array_slice($filteredSkills, 0, 10);
      $output = "";
      foreach ($limitedSkills as $skill) {
        $terms = \Drupal::entityTypeManager()
              ->getStorage('taxonomy_term')
              ->loadByProperties(['name' => $skill['skills_competencies'], 'vid' => 'skills']);
        $term = $terms[array_key_first($terms)];
        if (!$term) {
          $message = 'Taxonomy Skills term "%term" is missing.';
          $values = array('%term' => $skill['skills_competencies']);
          \Drupal::logger('workbc_extra_fields')->notice($message, $values);
          continue;
        }
        $image = "";
        if (!$term->get('field_image')->isEmpty()) {
          $file = $term->get('field_image')->entity;
          if ($file) {
            $uri = $file->getFileUri();
            $real_path = \Drupal::service('file_system')->realpath($uri);
            $image = file_get_contents($real_path);
          }
        }
        $output .= '<div class="career-profiles-skill">';
        $output .= '  <div class="career-profiles-skill-icon-container">';
        $output .= '  ' . $image;
        $output .= '  </div>';
        $output .= '  <div class="career-profiles-skill-content-container">';
        $output .= '    <div class="career-profiles-skill-title">' . $skill['skills_competencies'] . '</div>';
        $output .= '    <div class="career-profiles-skill-description">' . $term->getDescription() . '</div>';
        $output .= '  </div>';
        $output .= '</div>';
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
