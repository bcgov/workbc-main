<?php

namespace Drupal\media_entity_dlcke\Plugin\Filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\media\MediaInterface;
use Drupal\Component\Utility\Html;

/**
 * Provides a 'DLCKE Filter' filter.
 *
 * @Filter(
 *   id = "media_entity_dlcke_filter",
 *   title = @Translation("DLCKE Filter"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 *   weight = -10
 * )
 */
class DLCKEFilter extends FilterBase {

  // /**
  //  * {@inheritdoc}
  //  */
  // public function settingsForm(array $form, FormStateInterface $form_state) {
  //   $form['example'] = [
  //     '#type' => 'textfield',
  //     '#title' => $this->t('Example'),
  //     '#default_value' => $this->settings['example'],
  //     '#description' => $this->t('Description of the setting.'),
  //   ];
  //   return $form;
  // }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    if (stristr($text, '[dlcke:') === FALSE) {
      return $result;
    }

    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);

    foreach ($xpath->query('//a[@data-entity-loader="dlcke" and normalize-space(@data-uuid)!=""]') as $node) {
      /** @var \DOMElement $node */
      $uuid = $node->getAttribute('data-uuid');

      $media = \Drupal::service('entity.repository')->loadEntityByUuid('media', $uuid);
      assert($media === NULL || $media instanceof MediaInterface);
      if (!$media) {
        $this->loggerFactory->get('media')->error('During replacement of dlcke media id: the media item with UUID "@uuid" does not exist.', ['@uuid' => $uuid]);
        return $result;
      } else {
        $text = str_replace('[dlcke:'.$uuid.']', $media->id(), $text);
      }
    }

    return new FilterProcessResult($text);
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    return $this->t('Filters media_download_dlcke links to be the entity id instead of uuid.');
  }

}
