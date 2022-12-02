<?php
namespace Drupal\workbc_jobboard\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\workbc_jobboard\Controller\WorkBcJobboardController;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Symfony\Component\HttpFoundation\Cookie;

class JobboardSaveProfileForm extends FormBase {
  
  public $isSaved = false;
  
  /**
   * {@inheritdoc}
   */
  function __construct(){
    if(isset($_COOKIE['currentUser_token'])){
      $Bearer = "Bearer ".$_COOKIE['currentUser_token'];
      $node = \Drupal::routeMatch()->getParameter('node');
      if($node instanceof \Drupal\node\NodeInterface) {
        $type = $node->bundle();
        if($type == 'career_profile') {
          $noc_value = ($node->get('field_noc')->getValue())? $node->get('field_noc')->getValue(): '';
          $noc_value = (!empty($noc_value) && isset($noc_value[0]['value']))? $noc_value[0]['value'] :'';
          $action = 'statusProfile';
        }
        else if($type == 'industry_profile') {
          $noc_value = ($node->get('field_jobboard_save_profile_id')->getValue())? $node->get('field_jobboard_save_profile_id')->getValue(): '';
          $noc_value = (!empty($noc_value) && isset($noc_value[0]['value']))? $noc_value[0]['value'] :'';
          $noc_value = explode(',', $noc_value);
          $noc_value = $noc_value[0];
          $action = 'statusIndustryProfile';
        }
      }
      $WorkBcJobboardController = new WorkBcJobboardController();
      $parameters = ['profile_id'=>$noc_value, 'Authorization'=>$Bearer];
      $response = $WorkBcJobboardController->getPosts($parameters, $action, 'GET');
      if($response['response'] == 200){
        $this->isSaved = $response['data'];
      }
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workbc_fobboard_save_profile';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['search__form'] = [
      '#type' => 'markup',
      '#prefix' => '<div id="save_career_profile" class="save_career_profile">',
      '#suffix' => '</div>',
    ];
    $form['search__form']['submit'] = [
      '#type' => 'submit',
      '#value' => (!empty($this->isSaved))? t('Saved'): t('Save this profile'),
    ];
    if(!empty($this->isSaved)){
      $form['search__form']['submit']['#attributes'] = [
        "onclick" => "javascript: this.submit; this.disabled = true;"
      ];
    }
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function validateForme($element, FormStateInterface $form_state) {
    if(!empty($this->isSaved)){
      $form_state->setError($element, t('Profile already saved.'));
    }else{
      parent::validateForm($form, $form_state);
    }
  }
  
  /*
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $response = [];
    if(empty($this->isSaved)){
        $Bearer = "Bearer ".$_COOKIE['currentUser_token'];
        $node = \Drupal::routeMatch()->getParameter('node');
        if($node instanceof \Drupal\node\NodeInterface) {
          $type = $node->bundle();
          if(isset($_COOKIE['currentUser_token'])){
            if($type == 'career_profile') {
              $noc_value = ($node->get('field_noc')->getValue())? $node->get('field_noc')->getValue(): '';
              $noc_value = (!empty($noc_value) && isset($noc_value[0]['value']))? $noc_value[0]['value'] :'';
              $action = 'saveProfile';
            }
            else if($type == 'industry_profile') {
              $noc_value = ($node->get('field_jobboard_save_profile_id')->getValue())? $node->get('field_jobboard_save_profile_id')->getValue(): '';
              $noc_value = (!empty($noc_value) && isset($noc_value[0]['value']))? $noc_value[0]['value'] :'';
              $noc_value = explode(',', $noc_value);
              $noc_value = $noc_value[0];
              $action = 'saveIndustryProfile';
            }
            
            $WorkBcJobboardController = new WorkBcJobboardController();
            $parameters = ['profile_id'=>$noc_value, 'Authorization'=>$Bearer];
            $saveProfile = $WorkBcJobboardController->getPosts($parameters, $action);
            if($saveProfile['response'] == 200){
              $this->isSaved = 1;
              $message = 'Profile successfully added.';
              \Drupal::messenger()->addMessage($message);
            }
        }
        else {
          $session = \Drupal::request()->getSession();
          $session->set('tmp_saved_profile', $node->id());
          $url = \Drupal::config('jobboard')->get('find_job_account_url');
          $redirectSearchPage = new RedirectResponse($url);
          $redirectSearchPage->send();
        }
      }
    }
    else{
      $message = 'Profile already saved.';
      \Drupal::messenger()->addMessage($message);
    }
  } 
}