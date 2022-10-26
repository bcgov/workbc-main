<?php
namespace Drupal\workbc_jobboard\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Access\AccessResult; 
use Drupal\Core\Session\AccountInterface;
use Drupal\workbc_jobboard\Controller\WorkBcJobboardController;

/**
 * Provides a 'Recent Jobs' Block.
 *
 * @Block(
 *   id = "workbc_jobboard_recent_jobs_sidebar",
 *   admin_label = @Translation("Recent Jobs"),
 *   category = @Translation("Workbc Jobboard Sidebar"),
 * )
 */
 
class WorkbcJobboardSidebar extends BlockBase{
  
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
    $this->configuration['job_board_results_to_show'] = $values['job_board_results_to_show'];
    $this->configuration['job_board_no_result_text'] = $values['job_board_no_result_text'];
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
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof \Drupal\node\NodeInterface) {
      $nid = $node->id();
      $noc_value =   $node->get('field_noc')->getValue();
      if(!empty($noc_value) && isset($noc_value[0]['value'])){
        $noc_value = $noc_value[0]['value'];
        $WorkBcJobboardController = new WorkBcJobboardController();
        $recent_jobs = $WorkBcJobboardController->getRecentPosts($noc_value);
        if($recent_jobs['response'] == 200){
          $jobs = $recent_jobs['data'];
          return [
            '#type' => 'markup',
            '#markup' => 'Explore recent job postings.',
            '#theme' => 'recent_jobs',
            '#data' => $jobs,
            '#sub_title' => $config['job_board_sub_title']??'',
            '#no_of_records' => $config['job_board_results_to_show']??'',
            '#readmore_label' => (isset($config['job_board_read_more_button_title'])) ?$config['job_board_read_more_button_title'] : 'View more jobs',
            '#no_result_text' => (isset($config['job_board_no_result_text'])) ?$config['job_board_no_result_text'] : 'There are no current job postings.',
            '#noc' => (isset($noc_value)) ? $noc_value : '',
            '#find_job_url'=>\Drupal::config('jobboard')->get('find_job_url'),
          ];
        }else {
          return [
            '#type' => 'markup',
            '#markup' => 'Explore recent job postings.',
            '#theme' => 'recent_jobs',
            '#data' => [],
            '#sub_title' => $config['job_board_sub_title']??'',
            '#no_of_records' => $config['job_board_results_to_show']??'',
            '#readmore_label' => (isset($config['job_board_read_more_button_title'])) ?$config['job_board_read_more_button_title'] : 'View more jobs',
            '#no_result_text' => 'Error '. $recent_jobs['response'].': Unable to connect to Job Board API.',
            '#noc' => (isset($noc_value)) ? $noc_value : '',
            '#find_job_url'=>\Drupal::config('jobboard')->get('find_job_url'),
          ];
        }
      }
    }
	}
  
  /**
   * {@inheritdoc}
   */
	public function blockAccess(AccountInterface $account){
    return AccessResult::allowedIfHasPermission($account, "access recent jobs block");
  }
}