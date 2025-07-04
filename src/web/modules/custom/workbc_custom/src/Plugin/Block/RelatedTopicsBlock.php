<?php

namespace Drupal\workbc_custom\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\Cache;
use Drupal\media\Entity\Media;

/**
 * Provides a WorkBC Related topics Block.
 *
 * @Block(
 *   id = "related_topics_block",
 *   admin_label = @Translation("WorkBC related topics block"),
 *   category = @Translation("WorkBC"),
 * )
 */
class RelatedTopicsBlock extends BlockBase {

  /**
     * {@inheritdoc}
     */
    public function blockForm($form, FormStateInterface $form_state) {
      $form = parent::blockForm($form, $form_state);

      $config = $this->getConfiguration();

      $form['trimmed_limit'] = [
        '#type' => 'number',
        '#title' => $this->t('Trimmed limit'),
        '#description' => $this->t('If no hero text or body summary is available, the body field will be used, the trimmed Body field will end before this character limit.'),
        '#default_value' => $config['trimmed_limit'] ?? '150',
      ];

      return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function blockSubmit($form, FormStateInterface $form_state) {
      parent::blockSubmit($form, $form_state);
      $values = $form_state->getValues();
      $this->configuration['trimmed_limit'] = $values['trimmed_limit'];
    }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $related_topics = array();
    $renderable = array();

    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof \Drupal\node\NodeInterface) {
      if ($node->hasField('field_related_topics')) {
        $related = $node->get('field_related_topics')->referencedEntities();
        if (!empty($related)) {
          foreach ($related as $refNode) {
            $related_fields = array(
              'image' => $this->renderImage($refNode),
              'top_level_parent' => $this->getTopLevel($refNode),
              'title' => $refNode->getTitle(),
              'body' => $this->renderText($refNode),
              'action' => $this->renderLink($refNode),
            );
            array_push($related_topics, $related_fields);
          }
          $renderable = [
            '#theme' => 'related_topics_block',
            '#related_topics' => $related_topics,
          ];
        }
      }
    }

    return $renderable;
  }

  // public function getCacheMaxAge() {
  //     return 0;
  // }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    // With this when your node change your block will rebuild.
    if ($node = \Drupal::routeMatch()->getParameter('node')) {
      // If there is node add its cachetag.
      return Cache::mergeTags(parent::getCacheTags(), ['node:' . $node->id()]);
    }
    else {
      // Return default tags instead.
      return parent::getCacheTags();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // Every new route this block will rebuild.
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  private function renderImage($node) {
    if ($node->hasField('field_related_topics_image') && !$node->get('field_related_topics_image')->isEmpty()) {
      $media_id = $node->field_related_topics_image[0]->getValue()['target_id'];
      $media = Media::load($media_id);
      $imageUri = $media?->field_media_image?->entity?->getFileUri();
      if($imageUri) {
        $image = [
          '#theme' => 'image_style',
          '#style_name' => 'related_topics',
          '#uri' => $imageUri
        ];
        return \Drupal::service('renderer')->render($image);
      }
    }
    else if ($node->hasField('field_hero_image_media') && !$node->get('field_hero_image_media')->isEmpty()) {
      $media_id = $node->field_hero_image_media[0]->getValue()['target_id'];
      $media = Media::load($media_id);
      $imageUri = $media?->field_media_image?->entity?->getFileUri();
      if($imageUri) {
        $image = [
          '#theme' => 'image_style',
          '#style_name' => 'related_topics',
          '#uri' => $imageUri
        ];
        return \Drupal::service('renderer')->render($image);
      }
    }
    else if ($node->hasField('field_image_media') && !$node->get('field_image_media')->isEmpty()) {
      $media_id = $node->field_image_media[0]->getValue()['target_id'];
      $media = Media::load($media_id);
      $imageUri = $media?->field_media_image?->entity?->getFileUri();
      if($imageUri) {
        $image = [
          '#theme' => 'image_style',
          '#style_name' => 'related_topics',
          '#uri' => $imageUri
        ];
        return \Drupal::service('renderer')->render($image);
      }
    }
    return '';
  }

  private function renderText($node) {
    if ($node->hasField('field_related_topics_blurb') && !empty($node->get('field_related_topics_blurb')->value)) {
      return strip_tags($node->get('field_related_topics_blurb')->value);
    }
    else if ($node->hasField('field_hero_text') && !empty($node->get('field_hero_text')->value)) {
      return strip_tags($node->get('field_hero_text')->value);
    }
    else {
      if ($node->hasField('body')) {
        if (!empty($node->get('body')->summary)){
          return $node->get('body')->summary;
        }
        else {
          if (!empty($node->get('body')->value)) {
            $text = strip_tags($node->get('body')->value);
            $config = $this->getConfiguration();
            $trim = isset($config['trimmed_limit']) ? $config['trimmed_limit'] : 150;
            $text = \Drupal\Component\Utility\Unicode::truncate($text, $trim, TRUE, TRUE);
            return $text;
          }
        }
      }
    }
    return '';
  }

  private function renderLink($node) {
    if ($node->getType() == "related_topics_card") {
      return $node->field_external_link->uri;
    }
    else {
      $options = ['absolute' => TRUE];
      $link = $url = \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $node->id()], $options);
      return $link->toString();
    }
  }

  private function getTopLevel($node) {
    if ($node->getType() == "related_topics_card") {
      return $node->field_ia_location->value;
    }
    else {
      $menu_link_manager = \Drupal::service('plugin.manager.menu.link');

      if ($node->id()) {
        $menu_link = $menu_link_manager->loadLinksByRoute('entity.node.canonical', array('node' => $node->id()));
      }
      else {
        return '';
      }
      if (is_array($menu_link) && count($menu_link)) {
        $menu_link = reset($menu_link);
        if ($menu_link->getParent()) {
          $parents = $menu_link_manager->getParentIds($menu_link->getParent());
          $parents = array_reverse($parents);
          $parent = reset($parents);
          $title = $menu_link_manager->createInstance($parent)->getTitle();
          return $title;
        }
      }
      return $node->getTitle();
    }
  }

}
