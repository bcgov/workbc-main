<?php
namespace Drupal\workbc_jobboard\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\workbc_jobboard\Controller\WorkBcJobboardController;


/**
 * Provides a 'Search Jobs' Block.
 *
 * @Block(
 *   id = "workbc_jobboard_search_recent_jobs",
 *   admin_label = @Translation("Search Jobs"),
 *   category = @Translation("WorkBC Job Board"),
 * )
 */

class WorkbcJobboardSearch extends BlockBase{

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();
    $form['job_board_findjob_title'] = [
      '#type' => 'textfield',
      '#required' => true,
      '#title' => $this->t('Job Title'),
      '#description' => $this->t('Find job block title'),
      '#default_value' => $config['job_board_findjob_title'] ?? '',
    ];
    $form['job_board_findjob_description'] = [
      '#type' => 'textarea',
      '#rows' => 10,
      '#cols' => 30,
      '#resizable' => TRUE,
      '#required' => true,
      '#title' => $this->t('Job Description'),
      '#description' => $this->t('Find job block description'),
      '#default_value' => $config['job_board_findjob_description'] ?? '',
    ];
    $form['job_board_postjob_title'] = [
      '#type' => 'textfield',
      '#required' => true,
      '#title' => $this->t('Post Job Title'),
      '#description' => $this->t('Post job block title'),
      '#default_value' => $config['job_board_postjob_title'] ?? '',
    ];
    $form['job_board_postjob_description'] = [
      '#type' => 'textarea',
      '#rows' => 10,
      '#cols' => 30,
      '#required' => true,
      '#title' => $this->t('Post Job Description'),
      '#description' => $this->t('Post job block description'),
      '#default_value' => $config['job_board_postjob_description'] ?? '',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['job_board_findjob_title'] = $values['job_board_findjob_title'];
    $this->configuration['job_board_findjob_description'] = $values['job_board_findjob_description'];
    $this->configuration['job_board_postjob_title'] = $values['job_board_postjob_title'];
    $this->configuration['job_board_postjob_description'] = $values['job_board_postjob_description'];
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    if (empty($form_state->getValue('job_board_findjob_title'))) {
      $form_state->setErrorByName('job_board_findjob_title', $this->t('Find job title can\'t be empty.'));
    }
  }

	/**
   * {@inheritdoc}
   */
	public function build(){

    $config = $this->getConfiguration();
    $searchform = \Drupal::formBuilder()->getForm('Drupal\workbc_jobboard\Form\JobboardSearchForm');
    $WorkBcJobboardController = new WorkBcJobboardController();
    $parameters = ['getTotalJobs'=>true];
    $recent_jobs = $WorkBcJobboardController->getPosts($parameters, 'getTotalJobs', 'get');
    return [
      '#type' => 'markup',
      '#markup' => 'Explore recent job postings.',
      '#theme' => 'search_recent_jobs',
      '#data' => [],
      '#form' => $searchform,
      '#totalJobs' => (isset($recent_jobs['data']))? $recent_jobs['data'] : 0,
      '#job_title' => $config['job_board_findjob_title']??'',
      '#job_description' => $config['job_board_findjob_description']??'',
      '#postjob_title' => $config['job_board_postjob_title']??'',
      '#postjob_description' => $config['job_board_postjob_description']??'',
    ];
  }

  /**
   * {@inheritdoc}
   */
	public function blockAccess(AccountInterface $account){
    return AccessResult::allowedIfHasPermission($account, "access find jobs block");
  }
}