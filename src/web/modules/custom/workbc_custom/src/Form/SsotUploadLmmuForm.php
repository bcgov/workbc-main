<?php

namespace Drupal\workbc_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
* Class SsotUploadLmmuForm.
*
* @package Drupal\workbc_custom\Form
*/
class SsotUploadLmmuForm extends FormBase {
  /**
   * {@inheritdoc}
   */
	public function getFormId()
  {
		return 'ssot_lmmu_form';
	}

  /**
   * {@inheritdoc}
   */
	public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form['year'] = [
      '#type' => 'number',
      '#title' => $this->t('Year'),
      '#required' => true,
      '#default_value' => idate('Y'),
      '#max' => idate('Y'),
      '#min' => idate('Y') - 5
    ];

    $form['month'] = [
      '#type' => 'select',
      '#title' => $this->t('Month'),
      '#required' => true,
      '#default_value' => idate('m'),
      '#options' => [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December'
      ]
    ];

    $form['#attributes']['enctype'] = 'multipart/form-data';
    $form['lmmu'] = array(
      '#type' => 'managed_file',
      '#name' => 'lmmu',
      '#title' => t('LMMU Spreadsheet'),
      '#required' => true,
      '#description' => t('Please upload your Labour Market Monthly Update spreadsheet (Excel .xlsx   format).'),
      '#upload_validators' => ['file_validate_extensions' => ['xlsx']],
      '#upload_location' => 'public://ssot/',
    );

    $form['submit_upload'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Submit')
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    ksm($form_state);
  }
}
