<?php

    namespace Drupal\workbc_custom\Form;
    use Drupal\Core\Form\FormBase;
    use Drupal\Core\Form\FormStateInterface;
    /**
     * Class HooOptionsForm for demonstration.
    */
    class HooOptionsForm extends FormBase {
      /**
       * {@inheritdoc}
      */
      public function getFormId() {
        return 'hoo_options';
      }
      /**
       * {@inheritdoc}
      */
      public function buildForm(array $form, FormStateInterface $form_state) {

        $region_value = \Drupal::request()->query->get('region');
        $education_value = \Drupal::request()->query->get('education');
        $interest_value = \Drupal::request()->query->get('interest');
        $offset = \Drupal::request()->query->get('offset');
        $limit = \Drupal::request()->query->get('limit');
        $parameters = '';
        $filters_exists = FALSE;

        //filters
        if(!empty($region_value)) {
          $parameters .= '&region=eq.' . $region_value;
          $filters_exists = TRUE;
        }
        if(!empty($education_value)) {
          $parameters .= '&typical_education_background=eq.' . $education_value;
          $filters_exists = TRUE;
        }
        if(!empty($interest_value)) {
          $parameters .= '&occupational_interest=like.*' . $interest_value. '*';
          $filters_exists = TRUE;
        }

        //pagination & offset
        if(!empty($offset)) {
            $parameters .= '&offset=' . $offset;
        }
        if(empty($limit)) {
            $parameters .= '&limit=20';
        } else {
            $parameters .= '&limit='. $limit;
        }

        //data
        $dataHHO = ssotHighOpportunityOptions($parameters);

        $data = $dataHHO['data'];
        $select_options = $dataHHO['options'];

        //table header
        $header = [$this->t('Occupation'), $this->t('Education Requirements'), $this->t('Median Hourly Wage'), $this->t('Job Openings to 2029'), $this->t('Occupational Interest')];


        $regionMappings = getRegionMappings();

        //filters options
        $regionOptions[''] = $this->t('Select');
        $educationOptions[''] = $this->t('Select');
        $interestOptions[''] = $this->t('Select');
        foreach($select_options as $select_key => $select_values) {
          $regionOptions[$select_values['region']] = $regionMappings[$select_values['region']];
          $educationOptions[$select_values['typical_education_background']] = $select_values['typical_education_background'];

          if(strpos($select_values['occupational_interest'], ',')!== false){
            $occupational_interest_arr = explode(',', $select_values['occupational_interest']);
            foreach($occupational_interest_arr as $occupational_interest_key => $occupational_interest_value) {
              $oi_value = trim($occupational_interest_value);
              $interestOptions[$oi_value] = $oi_value;
            }
          } else {
            $interestOptions[$select_values['occupational_interest']] = $select_values['occupational_interest'];
          }
          
        }

        //rows data
        if(!empty($data)) {
          foreach($data as $key => $values){
            $rows[$key]['occupation'] = $values['occupation'];
            $rows[$key]['typical_education_background'] = $values['typical_education_background'];
            $rows[$key]['wage_rate_median'] = '$'.$values['wage_rate_median'];
            $rows[$key]['openings_forecast'] = ssotFormatNumber($values['openings_forecast']);
            $rows[$key]['occupational_interest'] = $values['occupational_interest'];
          } 
        } else {
          $rows[] = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
        }

        //TBD: Source text
        $source_text = $this->t('<strong>Wage Rate</strong>: For occupations with a “*”, the annual wage rate is provided, as the hourly wage rate is not available.');

        //form
        $form['filters']['heading'] = [
            '#markup' => $this->t('Filter High Opportunity Occupations by region, education and occupational interest below.')
          ];

        $form['filters']['education_level'] = [
          '#type' => 'select',
          '#title' => $this->t('Education Level'),
          '#options'=> $educationOptions,
          '#value' => $education_value?$education_value:'null',
          '#attributes' => ['id' => 'education-level']
        ];
      
        $form['filters']['region'] = [
          '#type' => 'select',
          '#title' => $this->t('Region'),
          '#options'=> $regionOptions,
          '#value' => $region_value?$region_value:'null',
          '#attributes' => ['id' => 'region']
        ];
        $form['filters']['occupational_interest'] = [
          '#type' => 'select',
          '#title' => $this->t('Occupational Interest'),
          '#options'=> $interestOptions,
          '#value' => $interest_value?$interest_value:'null',
          '#attributes' => ['id' => 'occupational-interest']
        ];

        if(!empty($data)) {
          $form['data'] = [
            '#theme' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#attributes' => array('class'=>array('bc-high-opportunites-table')),
            '#header_columns' => 5,
          ];

          $form['load_more'] = [
            '#type' => 'button',
           '#value' => $this->t('Load More'),
          ];

        $form['source_text'] = [
            '#markup' => $source_text
          ];
        } else {
          if($filters_exists) {
            $output = '<div>'.$this->t('Choosen filters has no value. Please refine data from the above provided dropdowns.').'</div>';
          } else {
            $output = '<div>'.$this->t('No data available.').'</div>';  
          }
          $form['data'] = [
            '#markup' => $output 
          ];
        }
        return $form;
      }
      /**
       * {@inheritdoc}
      */
      public function validateForm(array &$form, FormStateInterface $form_state) {
        // Nothing.
      }
      /**
       * {@inheritdoc}
      */
      public function submitForm(array &$form, FormStateInterface $form_state) {
         // Nothing.
      }
        
    }