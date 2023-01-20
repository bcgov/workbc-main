<?php

namespace Drupal\webform_publication_composite\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\Component\Utility\Html;

/**
 * Provides a 'webform_publication_composite' element.
 *
 * @WebformElement(
 *   id = "webform_publication_composite",
 *   label = @Translation("Webform publication composite"),
 *   description = @Translation("Provides a webform publication composite."),
 *   category = @Translation("WorkBC elements"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 *
 * @see \Drupal\webform_example_composite\Element\WebformExampleComposite
 * @see \Drupal\webform\Plugin\WebformElement\WebformCompositeBase
 * @see \Drupal\webform\Plugin\WebformElementBase
 * @see \Drupal\webform\Plugin\WebformElementInterface
 * @see \Drupal\webform\Annotation\WebformElement
 */
class WebformPublicationComposite extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  protected function formatHtmlItemValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    return $this->formatTextItemValue($element, $webform_submission, $options);
  }

  /**
   * {@inheritdoc}
   */
  protected function formatTextItemValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $publications = $this->getValue($element, $webform_submission, $options);

    $total_publications = is_numeric($publications['total_publications']) ? intval($publications['total_publications']) : 0;
    $lines = [];
    for ($pub = 1; $pub <= $total_publications; $pub++) {
      $quantity = is_numeric($publications['quantity-'.$pub]) ? intval($publications['quantity-'.$pub]) : 0;
      $resource = Html::escape($publications['resource_no-'.$pub]);
      $title = Html::escape($publications['title-'.$pub]);
      if ($quantity > 0) {
        $line = "Qty: " . $quantity;
        $line .= ' - ' . $resource;
        $line .= ' - ' . $title;
        $lines[] = $line;
      }
    }
    return $lines;
  }

}
