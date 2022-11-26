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
        'placeholder'=>'Keyword(s)',
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
        'placeholder'=>'City or Postal Code',
        'aria-label'=>'City or Postal Code',
        'data-form-type'=>'query',
        'role'=>'combobox',
        'size'=>20,
      ],
    ];
    $form['searchtype'] = [
      '#type' => 'radios',
      '#options' => [
        'bySearchAll' => 'Search All',
        'byJobTitle' => 'Job title only',
        'byEmployerName' => 'Employer name only',
        'byJobNumber' => 'Job Number'
      ],
      '#default_value' => 'bySearchAll',
      '#required' => TRUE,
      '#prefix' => '<div class="job-search__form-radios">',
      '#suffix' => '</div>',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Find Jobs'),
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
    $location = $values['location'];
    $searchtype = $values['searchtype'];
    if(!empty($keywords)){
      switch($searchtype){
        case 'byJobTitle':
          $url .= "title=$keywords;";
        break;
        case 'byEmployerName':
          $url .= "employer=$keywords;";
        break;
        case 'byJobNumber':
          $url .= "job=$keywords;";
        break;
        default:
          $url .= "search=$keywords;";
        break;
      }
    }
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
    return;
  }
}