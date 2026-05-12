<?php
namespace Drupal\workbc_jobboard\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\workbc_jobboard\Controller\WorkBcJobboardController;

class JobboardSearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workbc_jobboard_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['search__form'] = [
      '#type' => 'markup',
      '#prefix' => '<div class="job-search__form-container job-search__form-inputs">',
      '#suffix' => '</div>',
    ];
    $form['search__form']['keywords'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Keyword'),
      '#default_value' => '',
      '#prefix' => '<div class="field-group">',
      '#suffix' => '</div>',
      '#attributes' => [
        'class'=>['form-control','ng-untouched','ng-pristine','ng-valid'],
        'id'=>['edit-keywords'],
        'placeholder'=>'Job Title / Description / Employer',
        'aria-label'=>'Keyword(s)',
        'data-form-type'=>'query',
        'size'=>20,
      ],
    ];
    $form['search__form']['location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location'),
      '#default_value' => '',
      '#prefix' => '<div class="field-group">',
      '#suffix' => '</div>',
      '#autocomplete_route_name' => 'workbc_jobboard.get_recent_jobs',
      '#attributes' => [
        'class'=>['form-control','ng-untouched','ng-pristine','ng-valid','mat-autocomplete-trigger'],
        'id'=>['edit-location'],
        'placeholder'=>'City / Postal Code',
        'aria-label'=>'Location',
        'data-form-type'=>'query',
        'role'=>'combobox',
        'aria-expanded'=>'false',
        'size'=>20,
      ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Find Jobs'),
      '#attributes' => [
        'class' => [
          'btn-primary',
        ],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /*
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    global $base_url;
    $find_job_url = \Drupal::config('jobboard')->get('find_job_url');
    $url = $base_url.$find_job_url."#/job-search;";
    $keywords = $values['keywords'];
    $url .= "search=$keywords;";
    $location = $values['location'];
    if(!empty($location)){
      $WorkBcJobboardController = new WorkBcJobboardController();
      $getCities = $WorkBcJobboardController->getPosts($location, 'getCities', 'get');
      if(isset($getCities['response']) && !empty($getCities['data'])){
        $url .= "city=$location;";
      }
      else {
        $url .= "postal=$location;radius=15";
      }
    }
    $redirectSearchPage = new RedirectResponse($url);
    $redirectSearchPage->send();
  }
}
