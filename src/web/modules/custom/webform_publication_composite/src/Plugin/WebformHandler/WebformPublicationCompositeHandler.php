<?php

namespace Drupal\webform_publication_composite\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\Component\Utility\Html;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Webform validate handler.
 *
 * @WebformHandler(
 *   id = "webform_publication_composite_custom_validator",
 *   label = @Translation("Publication quantity validation"),
 *   category = @Translation("Settings"),
 *   description = @Translation("Validate at least one publication has a quantity greater than zero."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class WebformPublicationCompositeHandler extends WebformHandlerBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $this->validatePublication($form_state);
  }

  /**
    * Validate a quantity greater than zero has been selected for at least one publication.
    */
   private function validatePublication(FormStateInterface $formState) {
     $value = !empty($formState->getValue('phone')) ? Html::escape($formState->getValue('phone')) : NULL;

     $publications = $formState->getValue('publications');

     $total_publications = $publications['total_publications'];
     $total_ordered = 0;
     for ($i = 1; $i <= $total_publications; $i++) {
       $total_ordered += $publications['quantity-'.$i];
     }

     if ($total_ordered == 0) {
       $formState->setErrorByName('publication', 'Please enter a quantity.');
     }
   }

 }
