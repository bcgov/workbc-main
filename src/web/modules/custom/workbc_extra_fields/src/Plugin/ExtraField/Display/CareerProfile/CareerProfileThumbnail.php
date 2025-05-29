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
 *   id = "profile_thumbnail",
 *   label = @Translation("Profile Thumbnail"),
 *   description = @Translation("An extra field to display Thumbnail."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileThumbnail extends ExtraFieldDisplayFormattedBase {

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
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['career_trek'])) {
      foreach($entity->ssot_data['career_trek'] as $career_trek) {
        if($career_trek['episode_num'] == $entity->episode_number) {
          $data = explode('/', $career_trek['youtube_link']);
          $video_id = end($data);
          $destination = 'public://career_trek_thumbnails/' . $video_id . '.jpg';
          $output = \Drupal::service('file_url_generator')->transformRelative($destination);
        }
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