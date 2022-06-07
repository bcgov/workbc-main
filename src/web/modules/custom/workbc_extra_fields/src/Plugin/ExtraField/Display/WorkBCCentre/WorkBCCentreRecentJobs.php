<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\WorkBCCentre;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "wbcc_recent_jobs",
 *   label = @Translation("Recent Jobs"),
 *   description = @Translation("An extra field to display work bc centre recent jobs."),
 *   bundles = {
 *     "node.workbc_centre",
 *   }
 * )
 */
class WorkBCCentreRecentJobs extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Recent Jobs');
  }

  /**
   * {@inheritdoc}
   */
  public function getLabelDisplay() {

    return 'above';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity) {

    $output = "[not-yet-available]";

    return [
      ['#markup' => $output],
    ];
  }

}
