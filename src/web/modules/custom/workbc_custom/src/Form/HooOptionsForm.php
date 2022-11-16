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
        $wage_value = \Drupal::request()->query->get('wage');
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
        if(!empty($wage_value)) {
          $wages_limit = explode('-',$wage_value);
          if($wages_limit[0] > 0){
            $parameters .= '&wage_rate_median=gt.' . $wages_limit[0];
          }
          if($wages_limit[1] > 0){
            $parameters .= '&wage_rate_median=lt.' . $wages_limit[1];
          }
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


        $parsed_date_range = ssotParseDateRange($dataHHO['schema'], 'high_opportunity_occupations', 'openings_forecast');

        //table header
        $header = [$this->t('Occupation'), $this->t('Education Requirements'), $this->t('Median Hourly Wage'), $this->t('Job Openings to'). ' '. $parsed_date_range, $this->t('Occupational Interest')];


        $regionMappings = getRegionMappings();

        //filters options
        $no_value_text = $this->t('Select');;
        $regionOptions[''] = $no_value_text;
        $educationOptions[''] = $no_value_text;
        $interestOptions[''] = $no_value_text;

        //static wage options
        $wageOptions = [
          '' => $no_value_text,
          '0-20'  => $this->t('Under $20.00 per hour'),
          '20-30' => $this->t('$20.00 to $29.99 per hour'),
          '30-40' => $this->t('$30.00 to $39.99 per hour'),
          '40-49' => $this->t('$40.00 to $49.99 per hour'),
          '50-0' => $this->t('$50.00+ per hour')
        ];

        //occupational interests options
        $occupational_interests = [];
        foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('occupational_interests') as $term) {
            $interestOptions[$term->name] = $term->name;
        }

        foreach($select_options as $select_key => $select_values) {
          $regionOptions[$select_values['region']] = $regionMappings[$select_values['region']];
          $educationOptions[$select_values['typical_education_background']] = $select_values['typical_education_background'];
        }

        //rows data
        if(!empty($data)) {
          foreach($data as $key => $values){
            $rows[$key]['occupation'] = $values['occupation'];
            $rows[$key]['typical_education_background'] = $values['typical_education_background'];
            $rows[$key]['wage_rate_median'] = '$'.ssotFormatNumber($values['wage_rate_median'], 2);
            $rows[$key]['openings_forecast'] = ssotFormatNumber($values['openings_forecast']);
            $rows[$key]['occupational_interest'] = $values['occupational_interest'];
          }
        } else {
          $rows[] = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
        }

        //TBD: Source text
        $source_text = $this->t('<div class="form-note"><strong>Wage Rate</strong>: For occupations with a “*”, the annual wage rate is provided, as the hourly wage rate is not available.</div>');

        $form['#attributes']['class'][] = 'high-opportunity-occupations-form';

        //form
        $form['filters']['heading'] = [
            '#prefix' => '<div class="high-opportunity-occupations-form__filters">',
            '#markup' => $this->t('<p>Filter High Opportunity Occupations by region, education, occupational interest and wage below.</p>')
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

        $form['filters']['wage'] = [
          '#type' => 'select',
          '#title' => $this->t('Wage'),
          '#options'=> $wageOptions,
          '#value' => $wage_value?$wage_value:'null',
          '#attributes' => ['id' => 'wage'],
          '#suffix' => '</div>'
        ];

        if(!empty($data)) {
          $form['data'] = [
            '#prefix' => '<div class="content-form">',
            '#theme' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#attributes' => array('class'=>array('bc-high-opportunites-table')),
            '#header_columns' => 5,
            '#suffix' => '</div>',
          ];

          $form['load_more'] = [
            '#prefix' => '<div class="text-center">',
            '#type' => 'button',
            '#value' => $this->t('Load More'),
            '#suffix' => '</div>',
          ];

        $form['source_text'] = [
            '#markup' => $source_text
          ];
        } else {
          if($filters_exists) {
            $output = '<div>'.$this->t('No data available for chosen filters. Please select other values for filtering.').'</div>';
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