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
 *   id = "profile_video_url",
 *   label = @Translation("Profile Video Url"),
 *   description = @Translation("An extra field to display Video Url."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileVideoUrl extends ExtraFieldDisplayFormattedBase {

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
          // Convert YouTube URL to oEmbed iframe with hash check.
          $video_id = explode('/', $career_trek['youtube_link']);
          $youtube_url = "https://www.youtube.com/watch?v=" . end($video_id);
          $max_width = 1200;
          $max_height = 675;

          // Try to use the oEmbed resource fetcher to get the embed HTML.
          try {
            /** @var \Drupal\media\OEmbed\OEmbedResourceFetcherInterface $oembed_fetcher */
            $oembed_fetcher = \Drupal::service('media.oembed.resource_fetcher');
            $resource = $oembed_fetcher->fetchResource($youtube_url, $max_width, $max_height);

            if ($resource && !empty($resource->getHtml())) {
              // Output the oEmbed HTML directly.
              $output = $resource->getHtml();
            }
            else {
              // Fallback: Build the oEmbed iframe URL with hash as Drupal expects.
              $iframe_url_helper = \Drupal::service('media.oembed.iframe_url_helper');
              $hash = $iframe_url_helper->getHash($youtube_url, $max_width, $max_height);
              $encoded_url = urlencode($youtube_url);
              $oembed_url = "/media/oembed?url={$encoded_url}&max_width={$max_width}&max_height={$max_height}&hash={$hash}";
              $output = $oembed_url;
            }
          }
          catch (\Exception $e) {
            // Fallback: Build the oEmbed iframe URL with hash as Drupal expects.
            $iframe_url_helper = \Drupal::service('media.oembed.iframe_url_helper');
            $hash = $iframe_url_helper->getHash($youtube_url, $max_width, $max_height);
            $encoded_url = urlencode($youtube_url);
            $oembed_url = "/media/oembed?url={$encoded_url}&max_width={$max_width}&max_height={$max_height}&hash={$hash}";
            $output = $oembed_url;
          }
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