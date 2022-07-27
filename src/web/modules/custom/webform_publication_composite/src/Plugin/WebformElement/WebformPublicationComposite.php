<?php

namespace Drupal\webform_publication_composite\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;
use Drupal\webform\WebformSubmissionInterface;

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
    $value = $this->getValue($element, $webform_submission, $options);

    $lines = [];
    for ($pub = 1; $pub <= $value['total_publications']; $pub++) {
      if ($value['quantity-'.$pub] > 0) {
        $line = "Qty: " . ($value['quantity-'.$pub] ? $value['quantity-'.$pub] : '');
        $line .= ' - ' . ($value['resource_no-'.$pub] ? ' ' . $value['resource_no-'.$pub] : '');
        $line .= ' - ' . ($value['title-'.$pub] ? ' ' . $value['title-'.$pub] : '');
        $lines[] = $line;
      }
    }
    return $lines;
  }

}
