<?php
namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarket;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "labourmarket_employment_by_age_sex_table",
 *   label = @Translation("Employment by Age Group and Sex"),
 *   description = @Translation("An extra field to display table of employment by age and sex."),
 *   bundles = {
 *     "node.labour_market_monthly",
 *   }
 * )
 */
class LabourMarketEmploymentByAgeSexTable extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Employment by Age Group and Sex');
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

    if(empty($entity->ssot_data['monthly_labour_market_updates'])){
      $output = '<div>'.WORKBC_EXTRA_FIELDS_NOT_AVAILABLE.'</div>';
      return [
        ['#markup' => $output ],
      ];
    }

    $current_previous_months = $entity->ssot_data['current_previous_months_names'];
    $header = [' ', $current_previous_months['current_month_year'], $current_previous_months['previous_month_year']];

    if(!empty($entity->ssot_data['monthly_labour_market_updates'][0])) {
      $rows = $this->getGenderAgeValues($entity->ssot_data['monthly_labour_market_updates'][0], $header);
    } else {
      $rows[] = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }

    $source_text = !empty($entity->ssot_data['sources']['no-datapoint']) ? $entity->ssot_data['sources']['no-datapoint'] : WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    $output = '<div class="lm-source"><strong>'.$this->t("Source").':</strong> '.$source_text.'</div>';

    return [
      [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#attributes' => array(
          'class' => array('lm-table-age-gender'),
        ),
        '#header_columns' => 4,
      ],
      [
        '#markup' => $output
      ]
    ];

  }


  public function getGenderAgeValues($values, $header){
    $genderAgeValues = [];
    $ageNeedle = 'employment_by_age_group_';
    $genderNeedle = 'employment_by_gender_';

    if(!empty($values)){

      $options = array(
        'decimals' => 0,
        'na_if_empty' => TRUE,
      );

      $class = ['data-row'];
      foreach($values as $key => $value){

        //age values
        if(strpos($key, $ageNeedle) !== false){
          $age = str_replace($ageNeedle, "", $key);

          if(empty($genderAgeValues['age']['head'])) {
            $genderAgeValues['ahead']['class'] = ['age-header'];
            $genderAgeValues['ahead']['data'] = [
              [
                'data' => $this->t('Age'),
                'colspan' => 3,
                'class' => ['lm-header'],
              ],
            ];
          }

          //if previous values
          if(strpos($age, 'previous') !== false) {
            $age = str_replace('_previous', "", $age);
            $age = str_replace('55', "55+", $age);
            $genderAgeValues[$age]['class'] = ['age-data-row'];
            $genderAgeValues[$age]['data']['age'] = [
              'data' => str_replace("_"," - ",$age) . ' ' . $this->t('years'),
              'class' => ['label']
            ];
            $genderAgeValues[$age]['data']['previous'] = [
              'data' => ssotFormatNumber($value, $options),
              'class' => $class,
              'align' => "right",              
              'data-label' => $header[2]
            ];

          } else {
            $age = str_replace('55', "55+", $age);
            $genderAgeValues[$age]['class'] = ['age-data-row'];
            $genderAgeValues[$age]['data']['age'] = [
              'data' => str_replace("_"," - ",$age) . ' ' . $this->t('years'),
              'class' => ['label']
            ];
            $genderAgeValues[$age]['data']['current'] = [
              'data' => ssotFormatNumber($value, $options),
              'class' => $class,
              'align' => "right",              
              'data-label' => $header[1]
            ];
          }
        }

        //gender values
        if(strpos($key, $genderNeedle) !== false){
          $gender = str_replace($genderNeedle, "", $key);

          if(empty($genderAgeValues['gender']['head'])) {
            $genderAgeValues['ghead']['class'] = ['gender-header'];
            $genderAgeValues['ghead']['data'] = [
              [
                'data' => $this->t('Sex'),
                'colspan' => 3,
                'class' => ['lm-header'],
              ],
            ];
          }
          //if previous values
          if(strpos($gender, 'previous') !== false) {
            $gender = str_replace('_previous', "", $gender);
            $genderAgeValues[$gender]['class'] = ['gender-data-row'];
            $genderAgeValues[$gender]['data']['gender'] = [
              'data' => ucfirst($gender),
              'class' => ['label']
            ];
            $genderAgeValues[$gender]['data']['previous'] = [
              'data' => ssotFormatNumber($value, $options),
              'class' => $class,
              'align' => "right",
              'data-label' => $header[2]
            ];
          } else {
            $genderAgeValues[$gender]['data']['gender'] = [
              'data' => ucfirst($gender),
              'class' => ['label']
            ];
            $genderAgeValues[$gender]['data']['current'] = [
              'data' => ssotFormatNumber($value, $options),
              'class' => $class,
              'align' => "right",
              'data-label' => $header[1]
            ];
          }
        }

      }
    }
    return $genderAgeValues;
  }

}
