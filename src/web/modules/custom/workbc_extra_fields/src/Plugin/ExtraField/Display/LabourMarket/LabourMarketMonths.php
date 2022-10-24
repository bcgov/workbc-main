<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarket;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "labourmarket_months",
 *   label = @Translation("Months"),
 *   description = @Translation("An extra field to display months dropdown."),
 *   bundles = {
 *     "node.labour_market_monthly",
 *   }
 * )
 */
class LabourMarketMonths extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Months');
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

    $query_year = \Drupal::request()->query->get('year');
    $query_month = \Drupal::request()->query->get('month');

    //values
    $data = $entity->ssot_months['monthly_labour_market_months'];

    if (!empty($data)){
      foreach($data as $key => $value) {
        $year = $value['year'];
        //month
       $monthNum = $value['month'];
       $monthName = date ('F', mktime(0, 0, 0, $monthNum, 10));

       $options[$monthNum.'_'.$year] = $monthName.' '.$year;
      }
    }
    

    $text = $this->t('The latest monthly data is displayed below. If you would like to see data from previous months, please select a month from the dropdown.');

    if(!empty($query_month) && !empty($query_year)) {
      $default_value = $query_month.'_'.$query_year;
    } else {
      $default_value = NULL;
    }

    // print $default_value; exit;
    
    //output
    $output = '
    <div class="LME--months">
    <span class="LME--months-label">'.$text.'</span>
    </div>';

    return [
      ['#markup' => $output ],
      [
        '#type' => 'select',
        '#title' => $this->t('Months'),
        '#options'=> $options,
        '#value' => $default_value,
        '#attributes' => ['id' => 'employment-months']
      ]
      ];
  }

}
