<?php

namespace Drupal\workbc_career_trek\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Site\Settings;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface; // Add this for FILE_EXISTS_RENAME

class CareerTrekSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'workbc_career_trek.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workbc_career_trek_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('workbc_career_trek.settings');

    // Get the path to the module.
    $moduleHandler = \Drupal::service('module_handler');
    $modulePath = '/' . $moduleHandler->getModule('workbc_career_trek')->getPath();

    // Default icon paths
    $default_logo_path = $modulePath . '/assets/images/carrerTrekLogo.png';
    $default_icon_grid_path = $modulePath . '/assets/icons/grid_icon.svg';
    $default_list_icon_path = $modulePath . '/assets/icons/list_icon.svg';
    $default_toggle_icon_path = $modulePath . '/assets/icons/close.svg';

    // Theme icon paths
    $theme_icon_search = 'themes/custom/workbc/assets/icons/icon-search.svg';
    $theme_icon_occupational_categories = 'themes/custom/workbc/assets/icons/occupational-categories.svg';
    $theme_icon_profile_location = 'themes/custom/workbc/assets/icons/profile-location.svg';
    $theme_icon_noc = 'themes/custom/workbc/assets/icons/icon-noc.svg';
    $theme_icon_calendar = 'themes/custom/workbc/assets/icons/icon-calendar.svg';

    $form['main_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $config->get('main_title'),
    ];

    // Logo (allow PNG/JPG/SVG, not restricted to SVG)
    $form['logo'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Logo'),
    ];
    $form['logo']['logo_file'] = [
      '#type' => 'file',
      '#title' => $this->t('Upload Logo'),
      '#description' => $this->t('Allowed file types: SVG, PNG, JPG, JPEG. Leave empty to keep the current or default logo.'),
    ];
    // Show current logo file name if set
    $logo_fid = $config->get('logo_fid');
    $logo_url = $config->get('logo');
    if ($logo_fid && ($file = File::load($logo_fid))) {
      $filename = $file->getFilename();
      $form['logo']['current_logo'] = [
        '#markup' => '<div>' . $this->t('Current file: ') . "<a href='$logo_url' target='_blank'>" . $filename . '</a></div>',
      ];
    } elseif ($logo_url) {
      $form['logo']['current_logo'] = [
        '#markup' => '<div>' . $this->t('Current file: ') . "<code>" . $default_logo_path . '</code></div>',
      ];
    } else {
      $form['logo']['logo_default'] = [
        '#markup' => '<div>' . $this->t('Default:') . ' <code>' . $default_logo_path . '</code></div>',
      ];
    }

    // Helper for SVG upload fields (using file type, not managed_file)
    $svg_upload_field = function($name, $title, $default_path, $config, $form_state, $description = '') {
      $field = [
        '#type' => 'fieldset',
        '#title' => $title,
      ];
      $field[$name . '_file'] = [
        '#type' => 'file',
        '#title' => t('Upload SVG Icon'),
        '#description' => t('Only SVG files are allowed. Leave empty to keep the current or default icon.'),
      ];
      // Show current file name if set
      $icon_url = $config->get($name);
      $icon_fid = $config->get($name . '_fid');
      if ($icon_fid && ($file = File::load($icon_fid))) {
        $filename = $file->getFilename();
        $field['current_' . $name] = [
          '#markup' => '<div>' . t('Current file: ') . "<a href='$icon_url' target='_blank'>" . $filename . '</a></div>' ,
        ];
      } elseif ($icon_url) {
        $field['current_' . $name] = [
          '#markup' => '<div>' . t('Current file: ') . '<code>' . $default_path . '</code></div>',
        ];
      } else {
        $field[$name . '_default'] = [
          '#markup' => '<div>' . t('Default:') . ' <code>' . $default_path . '</code></div>',
        ];
      }
      if ($description) {
        $field['#description'] = $description;
      }
      return $field;
    };

    // SVG icon fields (all use file type, not managed_file)
    $form['icon_search'] = $svg_upload_field(
      'icon_search',
      $this->t('Search Bar Icon'),
      $theme_icon_search,
      $config,
      $form_state,
      $this->t('Icon for the search bar.')
    );
    $form['icon_occupational_categories'] = $svg_upload_field(
      'icon_occupational_categories',
      $this->t('Occupational Categories Icon'),
      $theme_icon_occupational_categories,
      $config,
      $form_state,
      $this->t('Icon for occupational categories.')
    );
    $form['icon_profile_location'] = $svg_upload_field(
      'icon_profile_location',
      $this->t('Profile Location Icon'),
      $theme_icon_profile_location,
      $config,
      $form_state,
      $this->t('Icon for profile location.')
    );
    $form['icon_noc'] = $svg_upload_field(
      'icon_noc',
      $this->t('NOC Icon'),
      $theme_icon_noc,
      $config,
      $form_state,
      $this->t('Icon for NOC.')
    );
    $form['icon_calendar'] = $svg_upload_field(
      'icon_calendar',
      $this->t('Calendar Icon'),
      $theme_icon_calendar,
      $config,
      $form_state,
      $this->t('Icon for calendar.')
    );
    $form['toggle_icon_grid'] = $svg_upload_field(
      'toggle_icon_grid',
      $this->t('Toggle Grid Icon'),
      $default_icon_grid_path,
      $config,
      $form_state,
      $this->t('Grid view toggle icon.')
    );
    $form['toggle_icon_list'] = $svg_upload_field(
      'toggle_icon_list',
      $this->t('Toggle List Icon'),
      $default_list_icon_path,
      $config,
      $form_state,
      $this->t('List view toggle icon.')
    );
    $form['responsive_toggle_icon'] = $svg_upload_field(
      'responsive_toggle_icon',
      $this->t('Responsive Toggle Icon'),
      $default_toggle_icon_path,
      $config,
      $form_state,
      $this->t('Responsive close/toggle icon.')
    );

    // The rest of the fields remain unchanged
    $form['back_button'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Back Button'),
    ];

    $form['back_button']['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Back Button URL'),
      '#default_value' => $config->get('back_button_url'),
      '#description' => $this->t('Enter the URL for the back button'),
    ];

    $form['back_button']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Back Button Title'),
      '#default_value' => $config->get('back_button_title'),
      '#description' => $this->t('Enter the text to display on the back button'),
    ];

    $form['searching_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Searching text'),
      '#default_value' => $config->get('searching_text'),
      '#description' => $this->t('Enter the searching text'),
    ];
    $form['in_demand_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('In Demand Title'),
      '#default_value' => $config->get('in_demand_title'),
      '#description' => $this->t('Enter the demand title'),
    ];
    $form['latest_career_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Latest Career Title'),
      '#default_value' => $config->get('latest_career_title'),
      '#description' => $this->t('Enter the latest career title'),
    ];
    $form['filter_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filter Title'),
      '#default_value' => $config->get('filter_title'),
      '#description' => $this->t('Enter the Filter title'),
    ];
    $form['related_careers_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Related Title'),
      '#default_value' => $config->get('related_careers_title'),
      '#description' => $this->t('Enter the Related title'),
    ];

    $form['url_skills_future_workforce'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Skill Future Url'),
      '#default_value' => $config->get('url_skills_future_workforce'),
      '#description' => $this->t('Enter the Related title'),
    ];
    $form['link_to_career_profile_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link to Career Profile Text'),
      '#default_value' => $config->get('link_to_career_profile_text') ?? "View career profile",
      '#description' => $this->t('Enter the text for the link.'),
    ];
    $form['view_occupational_category_api_field_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Career Category Title'),
      '#default_value' => $config->get('view_occupational_category_api_field_title') ?? "Career Category",
      '#description' => $this->t('Enter the text for the title.'),
    ];
    $form['view_occupational_category_api_field_description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Career Category Description'),
      '#default_value' => $config->get('view_occupational_category_api_field_description') ?? "Select one or more options to filter your search results.",
      '#description' => $this->t('Enter the text for the description.'),
    ];
    $form['view_minimum_education_all_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Education & Training Title'),
      '#default_value' => $config->get('view_minimum_education_all_title') ?? "Education and training",
      '#description' => $this->t('Enter the text for the education & training title.'),
    ];
    $form['view_minimum_education_all_description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Education & Training Description'),
      '#default_value' => $config->get('view_minimum_education_all_description') ?? "Select one option to filter your search results.",
      '#description' => $this->t('Enter the text for the education & training description.'),
    ];
    $form['view_annual_salary_wrapper_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Salary Range Title'),
      '#default_value' => $config->get('view_annual_salary_wrapper_title') ?? "Salary Range",
      '#description' => $this->t('Enter the text for the salary range title.'),
    ];
    $form['view_annual_salary_wrapper_description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Salary Range Description'),
      '#default_value' => $config->get('view_annual_salary_wrapper_description') ?? "Adjust the slider to set your desired salary range.",
      '#description' => $this->t('Enter the text for the salary range description.'),
    ];
    $form['view_skills_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Skills Title'),
      '#default_value' => $config->get('view_skills_title') ?? "Skills",
      '#description' => $this->t('Enter the text for the skills title.'),
    ];
    $form['view_skills_description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Skills Description'),
      '#default_value' => $config->get('view_skills_description') ?? "Select one or more options to filter your search results.",
      '#description' => $this->t('Enter the text for the skills description.'),
    ];
    $form['view_regions_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Regions Title'),
      '#default_value' => $config->get('view_regions_title') ?? "Regions",
      '#description' => $this->t('Enter the text for the regions title.'),
    ];
    $form['view_regions_description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Regions Description'),
      '#default_value' => $config->get('view_regions_description') ?? "Select one option to filter your search results.",
      '#description' => $this->t('Enter the text for the regions description.'),
    ];
    $form['apply_button'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Apply Filter Text'),
      '#default_value' => $config->get('apply_button') ?? "Apply filters",
      '#description' => $this->t('Enter the text for the Apply Filter.'),
    ];

    $form['clear_filter_button'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Clear Filter Text'),
      '#default_value' => $config->get('clear_filter_button') ?? "Clear filters",
      '#description' => $this->t('Enter the text for the Clear filters.'),
    ];

    // Add a wrapper to the entire form for AJAX partial rendering.
    $form['#prefix'] = '<div id="career-trek-settings-form-wrapper">';
    $form['#suffix'] = '</div>';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('workbc_career_trek.settings');
    $fields = [
      'main_title' => 'main_title',
      'back_button_url' => 'url',
      'back_button_title' => 'title',
      'searching_text' => 'searching_text',
      'in_demand_title' => 'in_demand_title',
      'latest_career_title' => 'latest_career_title',
      'filter_title' => 'filter_title',
      'related_careers_title' => 'related_careers_title',
      'url_skills_future_workforce' => 'url_skills_future_workforce',
      'link_to_career_profile_text' => 'link_to_career_profile_text',
      'view_occupational_category_api_field_title' => 'view_occupational_category_api_field_title',
      'view_occupational_category_api_field_description' => 'view_occupational_category_api_field_description',
      'view_minimum_education_all_title' => 'view_minimum_education_all_title',
      'view_minimum_education_all_description' => 'view_minimum_education_all_description',
      'view_annual_salary_wrapper_title' => 'view_annual_salary_wrapper_title',
      'view_annual_salary_wrapper_description' => 'view_annual_salary_wrapper_description',
      'view_skills_title' => 'view_skills_title',
      'view_skills_description' => 'view_skills_description',
      'view_regions_title' => 'view_regions_title',
      'view_regions_description' => 'view_regions_description',
      'apply_button' => 'apply_button',
      'clear_filter_button' => 'clear_filter_button',
    ];

    foreach ($fields as $formKey => $configKey) {
      if (is_array($configKey)) {
        $value = isset($form_state->getValue($configKey[0])[$configKey[1]]) ? $form_state->getValue($configKey[0])[$configKey[1]] : '';
      } else {
        $value = $form_state->getValue($configKey);
      }
      $config->set($formKey, $value);
    }

    $moduleHandler = \Drupal::service('module_handler');
    $modulePath = '/' . $moduleHandler->getModule('workbc_career_trek')->getPath();

    // Handle logo upload (allow SVG, PNG, JPG, JPEG)
    $validators = [
      'file_validate_extensions' => ['svg png jpg jpeg'],
      'file_validate_size' => [2 * 1024 * 1024], // 2MB
    ];
    if (isset($_FILES['files']['name']['logo_file']) && !empty($_FILES['files']['name']['logo_file'])) {
      $destination = 'public://career_trek_icons/';
      \Drupal::service('file_system')->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $file_upload = file_save_upload('logo_file', $validators, $destination, 0, FileSystemInterface::EXISTS_RENAME);
      if ($file_upload) {
        // Remove previous file if exists
        $old_fid = $config->get('logo_fid');
        if ($old_fid && ($old_file = File::load($old_fid))) {
          $old_file->delete();
        }
        $file_upload->setPermanent();
        $file_upload->save();
        $config->set('logo_fid', $file_upload->id());
        $config->set('logo', \Drupal::service('file_url_generator')->generateString($file_upload->getFileUri()));
      }
    }
    // If no file uploaded, keep existing or default
    if (!$config->get('logo')) {
      $config->set('logo', $modulePath . '/assets/images/carrerTrekLogo.png');
      $config->set('logo_fid', NULL);
    }

    // SVG icon fields (all use file type, not managed_file)
    $svg_icons = [
      'icon_search' => 'themes/custom/workbc/assets/icons/icon-search.svg',
      'icon_occupational_categories' => 'themes/custom/workbc/assets/icons/occupational-categories.svg',
      'icon_profile_location' => 'themes/custom/workbc/assets/icons/profile-location.svg',
      'icon_noc' => 'themes/custom/workbc/assets/icons/icon-noc.svg',
      'icon_calendar' => 'themes/custom/workbc/assets/icons/icon-calendar.svg',
      'toggle_icon_grid' => $modulePath . '/assets/icons/grid_icon.svg',
      'toggle_icon_list' => $modulePath . '/assets/icons/list_icon.svg',
      'responsive_toggle_icon' => $modulePath . '/assets/icons/close.svg',
    ];
    foreach ($svg_icons as $key => $default_path) {
      $validators = [
        'file_validate_extensions' => ['svg'],
        'file_validate_size' => [2 * 1024 * 1024], // 2MB
      ];
      if (isset($_FILES['files']['name'][$key . '_file']) && !empty($_FILES['files']['name'][$key . '_file'])) {
        $destination = 'public://career_trek_icons/';
        \Drupal::service('file_system')->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $file_upload = file_save_upload($key . '_file', $validators, $destination, 0, FileSystemInterface::EXISTS_RENAME);
        if ($file_upload) {
          // Remove previous file if exists
          $old_fid = $config->get($key . '_fid');
          if ($old_fid && ($old_file = File::load($old_fid))) {
            $old_file->delete();
          }
          $file_upload->setPermanent();
          $file_upload->save();
          $config->set($key . '_fid', $file_upload->id());
          $config->set($key, \Drupal::service('file_url_generator')->generateString($file_upload->getFileUri()));
        }
      }
      // If no file uploaded, keep existing or default
      if (!$config->get($key)) {
        $config->set($key, $default_path);
        $config->set($key . '_fid', NULL);
      }
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

}
