<?php

namespace Drupal\media_entity_dlcke\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\editor\EditorInterface;
use Drupal\token\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\entity_browser\Events\Events;
use Drupal\entity_browser\Events\RegisterJSCallbacks;

/**
 * Class MediaEntityDLCKEDialog.
 */
class MediaEntityDLCKEDialog extends FormBase {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Form\FormBuilder definition.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher definition.
   *
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $eventDispatcher;

  /**
   * The entity browser.
   *
   * @var \Drupal\entity_browser\EntityBrowserInterface
   */
  protected $entityBrowser;

  /**
   * Drupal\token\Token definition.
   *
   * @var \Drupal\token\Token
   */
  protected $token;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Constructs a new MediaEntityDownloadDialog object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The Form Builder.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher service.
   * @param \Drupal\token\Token $token
   *   The token service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The configuration service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder, EventDispatcherInterface $event_dispatcher, Token $token, ConfigFactoryInterface $config) {
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
    $this->eventDispatcher = $event_dispatcher;
    $this->token = $token;
    $this->config = $config->get('media_entity_dlcke.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
      $container->get('event_dispatcher'),
      $container->get('token'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_entity_dlcke_dialog';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\editor\EditorInterface $editor
   *   The editor to which this dialog corresponds.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, EditorInterface $editor = NULL) {
    $settings = $editor->getSettings();
    $values = $form_state->getValues();
    $input = $form_state->getUserInput();
    $form_state->set('editor', $editor);
    $entity_element = empty($values['attributes']) ? [] : $values['attributes'];
    $entity_element += empty($input['attributes']) ? [] : $input['attributes'];
    // The default values are set directly from \Drupal::request()->request,
    // provided by the editor plugin opening the dialog.
    if (!$form_state->get('entity_element')) {
      $form_state->set('entity_element', isset($input['editor_object']) ? $input['editor_object'] : []);
    }
    $entity_element += $form_state->get('entity_element');
    $entity_element += [
      'data-entity-type' => 'media',
      'data-entity-uuid' => '',
      'data-entity-embed-display' => 'entity_reference:entity_reference_entity_view',
      'data-entity-embed-display-settings' => isset($form_state->get('entity_element')['data-entity-embed-settings']) ? $form_state->get('entity_element')['data-entity-embed-settings'] : [],
    ];
    $form_state->set('entity_element', $entity_element);
    $entity = $this->entityTypeManager->getStorage($entity_element['data-entity-type'])
      ->loadByProperties(['uuid' => $entity_element['data-entity-uuid']]);
    $form_state->set('entity', current($entity) ?: NULL);

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';
    $form['#attached']['library'][] = 'media_entity_dlcke/drupal.media_entity_dlcke.dialog';

    if (!empty($settings['plugins']['mediaentitydlcke']['entity_browser'])) {
      $entity_browser_name = $settings['plugins']['mediaentitydlcke']['entity_browser'];
      $entity_browser = $this->entityTypeManager->getStorage('entity_browser')->load($entity_browser_name);
      $this->entityBrowser = $entity_browser;
      $this->eventDispatcher->addListener(Events::REGISTER_JS_CALLBACKS, [$this, 'registerJsCallback']);
      $form['entity_browser'] = [
        '#type' => 'entity_browser',
        '#entity_browser' => $entity_browser,
        '#cardinality' => 1,
        '#entity_browser_validators' => [
          'entity_type' => ['type' => 'media'],
        ],
      ];
    }
    else {
      $entity = $form_state->get('entity');
      $form['entity_id'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'media',
        '#title' => $this->t('Select a Media Entity'),
        '#default_value' => $entity,
        '#required' => TRUE,
        '#description' => $this->t('Type label and pick the right one from suggestions. Note that the unique ID will be saved.'),
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['save_modal'] = [
      '#type' => 'submit',
      '#value' => $this->t('Select'),
      '#button_type' => 'primary',
      // No regular submit-handler. This form only works via JavaScript.
      '#submit' => [],
      '#ajax' => [
        'callback' => '::submitSelect',
        'event' => 'click',
      ],
      '#attributes' => [
        'class' => [
          'js-button-submit',
        ],
      ],
    ];
    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

  /**
   * An ajax submit handler for the form.
   *
   * @param array $form
   *   The form object.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response to update the ckeditor field and close the modal.
   */
  public function submitSelect(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $links = [];
    $entity_id = $form_state->getValue('entity_id');
    // @TODO - not sure what is going on here but trying to follow
    // what is going on on lines 108+ of the media_entity_download field formatter
    $url_options = [
      'query' => ['inline' => null],
      'attributes' => [
        'rel' => 'no-follow',
        'target' => '_blank',
      ],
    ];

    if (!empty($entity_id)) {
      $media_entity = $this->entityTypeManager->getStorage('media')
        ->load($entity_id);
      if (!empty($media_entity)) {
        $url_object = Url::fromRoute(
          'media_entity_download.download',
          ['media' => $entity_id],
          $url_options,
        );
        $links[] = [
          'url' => $url_object->toString(),
          'text' => $media_entity->label(),
          'rel' => $url_options['attributes']['rel'],
          'target' => $url_options['attributes']['target'],
        ];
        $response->addCommand(new EditorDialogSave($links));
      }
    }
    else {
      $entity_browser_results = $form_state->getValue('entity_browser');
      $text_token = $this->config->get('text_token');
      if (!empty($entity_browser_results['entities'])) {
        foreach ($entity_browser_results['entities'] as $media_entity) {
          $url_object = Url::fromRoute(
            'media_entity_download.download',
            ['media' => $media_entity->id()],
            $url_options,
          );
          if (!empty($text_token)) {
            $link_text = $this->token->replace($text_token, ['media' => $media_entity]);
          }
          // If the token is empty or evaluates to empty, use the label.
          if (empty($link_text)) {
            $link_text = $media_entity->label();
          }
          $links[] = [
            'url' => $url_object->toString(),
            'text' => $link_text,
            'rel' => $url_options['attributes']['rel'],
            'target' => $url_options['attributes']['target'],
          ];
        }
        $response->addCommand(new EditorDialogSave($links));
      }
    }

    $response->addCommand(new CloseModalDialogCommand());

    return $response;
  }

  /**
   * Register a call back for the modal form.
   *
   * Registers JS callback that gets entities from entity browser and updates
   * form values accordingly.
   *
   * @param \Drupal\entity_browser\Events\RegisterJSCallbacks $event
   *   The entity browser event to attach to.
   */
  public function registerJsCallback(RegisterJSCallbacks $event) {
    if ($event->getBrowserID() == $this->entityBrowser->id()) {
      $event->registerCallback('Drupal.mediaEntityDLCKEDialog.selectionCompleted');
    }
  }

}
