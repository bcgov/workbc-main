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
 *   id = "profile_skills",
 *   label = @Translation("Profile Skills"),
 *   description = @Translation("An extra field to display skills."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileSkills extends ExtraFieldDisplayFormattedBase {

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
        $icon_url = "";
        $image = "";
        if (!$term->get('field_image')->isEmpty()) {
          $image_id = $term->field_image->entity->getFileUri();
          $icon_url = ImageStyle::load('skill_icon')->buildUrl($image_id);

          $imageUri = isset($term->get('field_image')->entity) ? $term->get('field_image')->entity->getFileUri() : null;
          if($imageUri) {
            $image = [
              '#theme' => 'image_style',
              '#style_name' => 'skill_icon',
              '#uri' => $imageUri
            ];
            $image = render($image);
          }
        }

        $data = array();
        $data[] = intval($skill['importance']);
        $data[] = 100 - intval($skill['importance']);
        $chart = [
          '#type' => 'chart',
          '#chart_type' => 'donut',
          '#chart_library' => 'google',
          '#colors' => array(
            '#029CDD',
            '#dbdbdb'
          ),
          'series' => [
            '#type' => 'chart_data',
            '#title' => $this->t('Profile Skills'),
            '#data' => $data,
          ],
          'xaxis' => [
            '#type' => 'chart_xaxis',
            '#labels' => [$this->t('Importance')],
          ],
          'yaxis' => [
            '#type' => 'chart_yaxis',
          ],
          '#raw_options' => [
            'options' => [
              'pieHole' => 0.75,
              'legend' => 'none',
              'enableInteractivity' => 'false',
              'width' => 110,
              'height' => 110,
              'theme' => 'maximized', // only use this if no legend, otherwise legend will be on top of chart
            ]
          ]
        ];
        $chart_render = \Drupal::service('renderer')->render($chart);

        $output .= '<div class="career-profiles-skill">';
        $output .= '  <div class="career-profiles-skill-icon-container">';
        $output .= '  ' . $image;
        $output .= '  </div>';
        $output .= '  <div class="career-profiles-skill-content-container">';
        $output .= '    <div class="career-profiles-skill-title">' . $skill['skills_competencies'] . '</div>';
        $output .= '    <div class="career-profiles-skill-description">' . $term->getDescription() . '</div>';
        $output .= '  </div>';
        $output .= '  <div class="career-profiles-skill-chart-container">';
        $output .= '    <div class="career-profiles-skill-chart-data">' . $chart_render . '</div>';
        $output .= '    <div class="career-profiles-skill-chart-overlay">' . intval($skill['importance']) . '%</div>';
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
