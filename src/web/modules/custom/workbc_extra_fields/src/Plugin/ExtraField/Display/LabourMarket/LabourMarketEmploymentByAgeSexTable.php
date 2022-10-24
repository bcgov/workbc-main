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
 *   label = @Translation("Employment by Age Group And Sex"),
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

    return $this->t('Employment by Age Group And Sex');
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

    //values
    $currentYear = $entity->ssot_data['monthly_labour_market_updates'][0]['year'];
    //month
    $currentMonth = $entity->ssot_data['monthly_labour_market_updates'][0]['month'];
    $currentMonthName = date ('F', mktime(0, 0, 0, $currentMonth, 10));

    $previousMonth = $currentMonth - 1;
    $previousYear = $currentYear;
    if($previousMonth == 0) {
      $previousMonth = 12;
      $previousYear = $currentYear - 1;
    }

    $previousMonthName =  date ('F', mktime(0, 0, 0, $previousMonth, 10));;

    $header = [$this->t(''), $this->t("@curmonth @curyear", ["@curmonth" => $currentMonthName, "@curyear" => $currentYear]), $this->t("@premonth @preyear", ["@premonth" => $previousMonthName, "@preyear" => $previousYear])];

    $rows = $this->getGenderAgeValues($entity->ssot_data['monthly_labour_market_updates'][0]);
    $source_text = $entity->ssot_data['sources']['no-datapoint'];
    $output = '<span><strong>Source: </strong>'.$source_text.'</span>';

    return [
      [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#attributes' => array('class'=>array('bc-region-table')),
        '#header_columns' => 4,
      ],
      [
        '#markup' => $output 
      ]
    ];

  }


    public function getGenderAgeValues($values){
    $genderAgeValues = [];
    $ageNeedle = 'employment_by_age_group_';
    $genderNeedle = 'employment_by_gender_';
    if(!empty($values)){
      foreach($values as $key => $value){

        //age values
        if(strpos($key, $ageNeedle) !== false){
          $age = str_replace($ageNeedle, "", $key);
          
          if(empty($genderAgeValues['age']['head'])) {
            $genderAgeValues['ahead'] = [$this->t('Age'),'',''];
          }
          //if previous values
          if(strpos($age, 'previous') !== false) {
            $age = str_replace('_previous', "", $age);
            $genderAgeValues[$age]['age'] = str_replace("_"," ",$age) . $this->t(' years');
            $genderAgeValues[$age]['previous'] = number_format($value);
          } else {
            $genderAgeValues[$age]['age'] = str_replace("_"," ",$age). $this->t(' years');
            $genderAgeValues[$age]['current'] = number_format($value);
          }
        }

        //gender values
        if(strpos($key, $genderNeedle) !== false){
          $gender = str_replace($genderNeedle, "", $key);

          if(empty($genderAgeValues['gender']['head'])) {
            $genderAgeValues['ghead'] = [$this->t('Sex'),'',''];
          }
          //if previous values
          if(strpos($gender, 'previous') !== false) {
            $gender = str_replace('_previous', "", $gender);
            $genderAgeValues[$gender]['gender'] = $gender;
            $genderAgeValues[$gender]['previous'] = number_format($value);
          } else {
            $genderAgeValues[$gender]['gender'] = $gender;
            $genderAgeValues[$gender]['current'] = number_format($value);
          }
        }

      }
    }
    return $genderAgeValues;
  }

}
