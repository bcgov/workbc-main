<?php
namespace Drupal\workbc_career_trek\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\workbc_jobboard\Controller\WorkBcJobboardController;

/**
 * Provides a 'Recent Career Trek Jobs' Block.
 *
 * @Block(
 *   id = "workbc_career_trek_recent_jobs_sidebar",
 *   admin_label = @Translation("Recent Career Trek Jobs"),
 *   category = @Translation("WorkBC Job Board"),
 * )
 */

class WorkbcCareerTrekJobboardSidebar extends BlockBase{

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();
    $form['job_board_sub_title'] = [
      '#type' => 'textfield',
      '#required' => true,
      '#title' => $this->t('Sub Title'),
      '#description' => $this->t('Recent Jobs Sub Title'),
      '#default_value' => $config['job_board_sub_title'] ?? '',
    ];
    $form['job_board_results_to_show'] = [
      '#type' => 'textfield',
      '#required' => true,
      '#title' => $this->t('Results to show'),
      '#description' => $this->t('No. of results to show.'),
      '#default_value' => $config['job_board_results_to_show'] ?? 3,
    ];
    $form['job_board_results_to_show_horizontal_view'] = [
      '#type' => 'textfield',
      '#required' => true,
      '#title' => $this->t('Results to show horizontal view'),
      '#description' => $this->t('No. of results to show in Horizontal View.'),
      '#default_value' => $config['job_board_results_to_show_horizontal_view'] ?? 4,
    ];
    $form['job_board_no_result_text'] = [
      '#type' => 'textfield',
      '#required' => true,
      '#title' => $this->t('No result text'),
      '#description' => $this->t('No result text'),
      '#default_value' => $config['job_board_no_result_text'] ?? 'There are no current job postings.',
    ];
    $form['job_board_read_more_button_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Read More Button Title'),
      '#description' => $this->t('Read More Button Title'),
      '#default_value' => (isset($config['job_board_read_more_button_title'])) ?$config['job_board_read_more_button_title'] : 'View more jobs',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['job_board_sub_title'] = $values['job_board_sub_title'];
    $this->configuration['job_board_results_to_show'] = $values['job_board_results_to_show'];
    $this->configuration['job_board_results_to_show_horizontal_view'] = $values['job_board_results_to_show_horizontal_view'];
    $this->configuration['job_board_no_result_text'] = $values['job_board_no_result_text'];
    $this->configuration['job_board_read_more_button_title'] = $values['job_board_read_more_button_title'];
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    if (empty($form_state->getValue('job_board_sub_title'))) {
      $form_state->setErrorByName('job_board_sub_title', $this->t('Sub Title can\'t be empty.'));
    }
    if (empty($form_state->getValue('job_board_results_to_show'))) {
      $form_state->setErrorByName('job_board_results_to_show', $this->t('No. of results field can\'t be empty.'));
    }
  }

	/**
   * {@inheritdoc}
   */
	public function build(){
    $config = $this->getConfiguration();
    if(!empty($config['node_id'])) {
      $entity_type_manager = \Drupal::entityTypeManager();
      $node_storage = $entity_type_manager->getStorage('node');
      $node = $node_storage->load($config['node_id']);
      if($node instanceof \Drupal\node\NodeInterface) {
        $type = $node->bundle();
        $jobs = [];
        $parameters["Page"]= 1;
        $parameters["SortOrder"]= 11;
        $parameters["PageSize"]= $config['job_board_results_to_show']??3;
        $theme = 'career_trek_recent_jobs';
  
        if($type == 'career_profile') {
          $noc_value = ($node->get('field_noc')->getValue())? $node->get('field_noc')->getValue(): '';
          $noc_value = (!empty($noc_value) && isset($noc_value[0]['value']))? $noc_value[0]['value'] :'';
          $parameters["SearchNocField"] ="$noc_value";
          $view_more_link_parameters = "noc=$noc_value";
        }
        
  
        $WorkBcJobboardController = new WorkBcJobboardController();
        $recent_jobs = $WorkBcJobboardController->getPosts($parameters);
        if($recent_jobs['response'] == 200){
          $total_result = $recent_jobs['data']['count']??0;
          foreach($recent_jobs['data']['result'] as $key => $job){
            $jobs[$key]['externalUrl'] = $job['ExternalSource']['Source'][0]['Url']??'';
            $jobs[$key]['jobTitle'] = $job['Title'];
            $jobs[$key]['jobId'] = $job['JobId'];
            $jobs[$key]['employer'] = $job['EmployerName'];
            $jobs[$key]['location'] = $job['City'];
            $jobs[$key]['datePosted'] = date("F d, Y", strtotime($job['DatePosted']));
          }
          $no_result_text_val = (isset($config['job_board_no_result_text'])) ?$config['job_board_no_result_text'] : 'There are no current job postings.';
        }
        else {
          $no_result_text_val = 'Unable to connect to Job Board API.';
          $api_url = \Drupal::config('jobboard')->get('jobboard_api_url_backend');
          \Drupal::logger('workbc_jobboard')->error('Error '. $recent_jobs['response'].': Unable to connect to Job Board API. '.$api_url);
        }
        $node_storage = \Drupal::entityTypeManager()->getStorage('node');
        $nodes = $node_storage->loadByProperties(['field_noc' => '00018']);
        $node = reset($nodes);
        $url_alias = '';
        if ($node instanceof \Drupal\node\NodeInterface) {
          $url_alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $node->id());
        }
        return [
          '#type' => 'markup',
          '#markup' => 'Explore recent job postings.',
          '#theme' => $theme,
          '#data' => $jobs,
          '#title' => $config['label']??'',
          '#sub_title' => $config['job_board_sub_title']??'',
          '#no_of_records_to_show' => $config['job_board_results_to_show']??'',
          '#no_of_records_to_show_horizontal_view' => $config['job_board_results_to_show_horizontal_view']??4,
          '#total_result' => $total_result??0,
          '#readmore_label' => (isset($config['job_board_read_more_button_title'])) ?$config['job_board_read_more_button_title'] : 'View more jobs',
          '#no_result_text' => $no_result_text_val,
          '#noc' => (isset($view_more_link_parameters)) ? $view_more_link_parameters : '',
          '#find_job_url'=>$url_alias,
        ];
      }
    }
    
	}
}