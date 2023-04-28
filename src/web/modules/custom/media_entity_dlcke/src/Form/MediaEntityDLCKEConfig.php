<?php

namespace Drupal\media_entity_dlcke\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\token\TreeBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MediaEntityDLCKEConfig.
 */
class MediaEntityDLCKEConfig extends ConfigFormBase {

  /**
   * The token tree builder service.
   *
   * @var \Drupal\token\TreeBuilderInterface
   */
  private $tokenTreeBuilder;

  /**
   * Constructor for Media Entity Download Config.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory service.
   * @param \Drupal\token\TreeBuilderInterface $token_tree_builder
   *   The token tree builder service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TreeBuilderInterface $token_tree_builder) {
    parent::__construct($config_factory);
    $this->tokenTreeBuilder = $token_tree_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('token.tree_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'media_entity_dlcke.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_entity_dlcke_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('media_entity_dlcke.settings');

    $form['text_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Link Title'),
      '#description' => $this->t('Provide a token compatible string to use for the default link title.'),
      '#default_value' => $config->get('text_token'),
    ];

    $form['browse_tokens'] = [
      '#type' => 'details',
      '#title' => $this->t('Browse Replacement Tokens'),
    ];

    $form['browse_tokens']['list'] = $this->tokenTreeBuilder->buildRenderable(['media'],
      [
        'click_insert' => FALSE,
        'global_types' => FALSE,
        'show_nested' => FALSE,
        'recursion_limit' => 1,
      ]);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('media_entity_dlcke.settings')
      ->set('text_token', $form_state->getValue('text_token'))
      ->save();
  }

}
