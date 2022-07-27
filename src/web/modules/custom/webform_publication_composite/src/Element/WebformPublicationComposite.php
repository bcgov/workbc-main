<?php

namespace Drupal\webform_publication_composite\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformCompositeBase;
use Drupal\file\FileInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Provides a 'webform_publication_composite'.
 *
 * Webform composites contain a group of sub-elements.
 *
 *
 * IMPORTANT:
 * Webform composite can not contain multiple value elements (i.e. checkboxes)
 * or composites (i.e. webform_address)
 *
 * @FormElement("webform_publication_composite")
 *
 * @see \Drupal\webform\Element\WebformCompositeBase
 * @see \Drupal\webform_example_composite\Element\WebformExampleComposite
 */
class WebformPublicationComposite extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return parent::getInfo() + ['#theme' => 'webform_publication_composite'];
  }

  /**
   * {@inheritdoc}
   */
  public static function getCompositeElements(array $element) {

    $options = [];
    for ($i = 0; $i <= 160; $i++) {
      $options[$i] = $i;
    }

    $query = \Drupal::entityQuery('node');
    $query->condition('status', 1);
    $query->condition('field_hardcopy_available', 1);
    $query->condition('type', 'publication');
    $query->sort('title', 'ASC');
    $entity_ids = $query->execute();
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($entity_ids);

    $pub = 1;

    $elements = [];
    $elements['publications'] = array(
      '#type' => 'table',
      '#header' => array(t('Qty'), t('Title'), t('Resource No'), t('')),
    );
    foreach ($nodes as $node) {
      $elements['publications'][$pub]['quantity-'.$pub] = [
        '#type' => 'select',
        // '#title' => t('Quantity'),
        '#options' => $options,
        '#value' => 0,
      ];
      $elements['publications'][$pub]['title-'.$pub] = [
        '#type' => 'item',
        '#markup' => $node->getTitle(),
        '#value' => $node->getTitle(),
      ];
      $elements['publications'][$pub]['resource_no-' . $pub] = [
        '#type' => 'item',
        '#markup' => $node->get('field_resource_number')->getString(),
        '#value' => $node->get('field_resource_number')->getString(),
      ];

      $fid = $node->get('field_publication')->target_id;
      if (!is_null($fid)) {
        $file = \Drupal\file\Entity\File::load($fid);
        $link_options = [];
        $link_options['attributes']['target'] = 1;
        $link = Link::fromTextAndUrl("View PDF", Url::fromUri('internal:'.$file->createFileUrl(), $link_options))->toString();
        $url = Url::fromUri('internal:'.$file->createFileUrl(), $link_options)->toString();
      }
      else {
        $link = '';
        $url = '';
      }


      $elements['publications'][$pub]['display_link-' . $pub] = [
        '#type' => 'item',
        '#markup' => $link,
        '#value' => $url,
      ];

      $elements['publicationspublications'][$pub]['nid-' . $pub] = [
        '#type' => 'hidden',
        '#value' => $node->id(),
      ];
      $pub++;
    }
    $elements['total_publications'] = [
      '#type' => 'hidden',
      '#value' => count($nodes),
    ];
    return $elements;
  }


}
