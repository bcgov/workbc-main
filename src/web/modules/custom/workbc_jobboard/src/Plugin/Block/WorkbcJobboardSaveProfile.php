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
 * Provides a 'Save Career Profile' Block.
 *
 * @Block(
 *   id = "workbc_jobboard_save_profile",
 *   admin_label = @Translation("Save Profile"),
 *   category = @Translation("Workbc Jobboard"),
 * )
 */
 
class WorkbcJobboardSaveProfile extends BlockBase{
  
	/**
   * {@inheritdoc}
   */	
	public function build(){
    $config = $this->getConfiguration();
    $saveProfile = \Drupal::formBuilder()->getForm('Drupal\workbc_jobboard\Form\JobboardSaveProfileForm');
    return [
      '#type' => 'markup',
      '#markup' => 'Save profile',
      '#theme' => 'save_profile',
      '#form' => (isset($saveProfile))? $saveProfile: '',
      '#data' => [],
    ];
  }
  
  /**
   * {@inheritdoc}
   */
	public function blockAccess(AccountInterface $account){
    return AccessResult::allowedIfHasPermission($account, "access find jobs block");
  }
}