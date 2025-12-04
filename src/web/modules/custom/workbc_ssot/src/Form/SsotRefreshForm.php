<?php

namespace Drupal\workbc_ssot\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
* Class SsotRefreshForm.
*
* @package Drupal\workbc_ssot\Form
*/
class SsotRefreshForm extends FormBase {
  /**
   * {@inheritdoc}
   */
	public function getFormId()
  {
		return 'ssot_refresh_form';
	}

  /**
   * {@inheritdoc}
   */
	public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form['description'] = [
      '#markup' => '<p>Schedule a full refresh of SSoT datasets that are used by this site. You will be redirected to the <strong>Queue manager</strong> page to run the <strong>SSoT Downloader</strong> batch process.</p>'
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Refresh')
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    // Retrieve the local state, making sure we set any modified datasets to null.
    $ssot_datasets = array_filter(SSOT_DATASETS, function($dataset) {
      return array_key_exists('noc_key', $dataset);
    });
    $local_dates = array_merge(array_combine(
      array_keys($ssot_datasets),
      array_fill(0, count($ssot_datasets), null)
    ), \Drupal::state()->get('workbc.ssot_dates', []));

    // Get the latest update dates from SSOT.
    $result = ssot(
      'sources?' . http_build_query([
        'select' => 'endpoint,date',
        'endpoint' => 'in.("' . join('","', array_keys($ssot_datasets)) . '")',
        'order' => 'date.desc'
      ])
    );
    if (!$result) {
      return;
    }
    $ssot_dates = json_decode($result->getBody());

    // Download datasets and schedule each career separately.
    $datasets = [];
    foreach ($ssot_dates as $ssot_date) {
      if (empty($ssot_date->date)) continue;

      $date1 = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $ssot_date->date);
      if (!$date1) {
        \Drupal::logger('workbc')->error('Error parsing date @date for SSOT dataset @dataset: @errors', [
          '@date' => $ssot_date->date,
          '@dataset' => $ssot_date->endpoint,
          '@errors' => print_r(\DateTimeImmutable::getLastErrors()),
        ]);
        continue;
      }

      // Some datasets have multiple entries. Pick the latest date and ignore the rest.
      if (array_key_exists($ssot_date->endpoint, $datasets) && $date1 <= \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $datasets[$ssot_date->endpoint]->date)) continue;

      $metadata = SSOT_DATASETS[$ssot_date->endpoint];

      // Formulate SSOT query given dataset information.
      $endpoint = (array_key_exists('endpoint', $metadata) ? $metadata['endpoint'] : $ssot_date->endpoint) . '?' . http_build_query(array_merge(
        ['select' => $metadata['fields']],
        array_key_exists('filters', $metadata) ? $metadata['filters'] : [],
        array_key_exists('order', $metadata) ? ['order' => $metadata['order']] : [],
      ));
      $result = ssot($endpoint);
      if (!$result) {
        \Drupal::logger('workbc')->error('Error fetching SSOT dataset @dataset at @endpoint. Skipping.', [
          '@dataset' => $ssot_date->endpoint,
          '@endpoint' => $endpoint,
        ]);
        continue;
      };

      // Index the dataset by NOC.
      $datasets[$ssot_date->endpoint] = [
        'endpoint' => $ssot_date->endpoint,
        'date' => $ssot_date->date,
        'data' => array_reduce(json_decode($result->getBody(), true), function($entries, $entry) use($metadata) {
          $noc = $entry[$metadata['noc_key']];
          $entries[$noc] ??= []; $entries[$noc][] = $entry;
          return $entries;
        }, []),
      ];
    }

    // Load and schedule all career profiles.
    $storage = \Drupal::service('entity_type.manager')->getStorage('node');
    $careers = $storage->loadByProperties([
      'type' => 'career_profile',
    ]);
    foreach ($careers as $career) {
      $noc = $career->get('field_noc')->value;
      if (empty($noc)) continue;

      \Drupal::queue('ssot_downloader_batch')->createItem([
        'nid' => $career->id(),
        'datasets' => array_map(function ($dataset) use ($noc, $ssot_datasets) {
          return [
            'endpoint' => $dataset['endpoint'],
            'date' => $dataset['date'],
            'data' => array_key_exists($noc, $dataset['data']) ? $dataset['data'][$noc] : null,
            'call_if_missing' => $ssot_datasets[$dataset['endpoint']]['call_if_missing'] ?? false
          ];
        }, $datasets),
      ]);
    }

    // Redirect to Queue manager.
    $form_state->setRedirect('queue_ui.overview_form');
  }

}
