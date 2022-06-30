<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
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
      $output = "";
      foreach ($entity->ssot_data['skills'] as $skill) {

        $terms = \Drupal::entityTypeManager()
              ->getStorage('taxonomy_term')
              ->loadByProperties(['name' => $skill['skills_competencies'], 'vid' => 'skills']);
        $term = $terms[array_key_first($terms)];
        $icon_url = "";
        if (!$term->get('field_image')->isEmpty()) {
          $image_id = $term->field_image->entity->getFileUri();
          $icon_url = ImageStyle::load('skill_icon')->buildUrl($image_id);
        }

        $data = array();
        $data[] = intval($skill['importance']);
        $data[] = 100 - intval($skill['importance']);
        $labels = [t('Importance')];
        $chart = [
          '#type' => 'chart',
          '#chart_type' => 'donut',
          'series' => [
            '#type' => 'chart_data',
            '#title' => t(''),
            '#data' => $data,
            '#prefix' => '',
            '#suffix' => '',
          ],
          'xaxis' => [
            '#type' => 'chart_xaxis',
            // '#labels' => $labels,
            '#max' => count($data),
            '#min' => 0,
          ],
          'yaxis' => [
            '#type' => 'chart_yaxis',
            '#max' => 100,
            '#min' => 0,
          ],
          // '#raw_options' => [
          //   'options' => [
          //     'pieHole' => 0.75,
          //     'legend' => 'none',
          //   ]
          // ]
        ];
        $chart_render = \Drupal::service('renderer')->render($chart);

        $output .= '<div class="career-profile-skill">';
        $output .= '<div class="career-profile-skill-icon"><img src="' . $icon_url . '" /></div>';
        $output .= '<div class="career-profile-skill-title">' . $skill['skills_competencies'] . '</div>';
        $output .= '<div class="career-profile-skill-description">' . $term->getDescription() . '</div>';
        $output .= '<div class="career-profile-skill-chart">' . $chart_render . '</div>';
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
