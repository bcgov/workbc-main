<?php

declare(strict_types=1);

namespace Drupal\ckeditor_media_resize\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Form\FormStateInterface;

/**
 * CKEditor 5 MediaResize plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class MediaResize extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'apply_image_styles' => TRUE,
      // @todo should the view modes be configurable be exposed on the form too?
      'image_styles' => [
        'cke_media_resize_small',
        'cke_media_resize_medium',
        'cke_media_resize_large',
        'cke_media_resize_xl',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['apply_image_styles'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable this to dynamically scale resized images using image styles.'),
      '#description' => $this->t(
        'The used image style is dynamically determined by the resize width. Used image styles: @styles',
        ['@styles' => implode(', ', $this->configuration['image_styles'])]
      ),
      '#default_value' => $this->configuration['apply_image_styles'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // This value will submit as an integer, thus cast to boolean as defined in
    // the schema.
    $form_value = $form_state->getValue('apply_image_styles');
    $form_state->setValue('apply_image_styles', (bool) $form_value);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['apply_image_styles'] = $form_state->getValue('apply_image_styles');
  }

}
