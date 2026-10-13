<?php

namespace Drupal\workbc_ssot\Form;

trait SsotUploadValidation {
  // @see https://stackoverflow.com/a/2430144/209184
  public function number_precision($value) {
    return strlen(substr(strrchr($value, '.'), 1));
  }

  public function is_number_integer($value) {
    return abs($value - round($value)) <= PHP_FLOAT_EPSILON;
  }

  public function array_key_push(&$array, $key, $value) {
    if (array_key_exists($key, $array)) {
      $array[$key][] = $value;
    }
    else {
      $array[$key] = [$value];
    }
  }

  function ssot($url, $read_timeout = null, $method = 'GET', $body = null)
  {
    $ssot = rtrim(\Drupal::config('workbc')->get('ssot_url'), '/') . '/';
    $client = new Client();
    $options = [];
    if ($read_timeout) {
      $options['read_timeout'] = $read_timeout;
    }
    switch (strtoupper($method)) {
      case 'GET':
        $response = $client->get($ssot . $url, $options);
        break;
      case 'POST':
      case 'PATCH':
        $options['body'] = $body;
        $response = $client->request($method, $ssot . $url, $options);
        break;
    }
    return $response;
  }

  function validateRules($dataset, $dataset_crosscheck)
  {
    $icons = [
      'error' => '❌',
      'warning' => '❗',
      'success' => '✅',
    ];
    $actions = [
      'error' => 'correct',
      'warning' => 'review',
    ];
    $results = [];
    foreach ($this->validations as $key => $validation) {
      $value = $dataset[$key];
      $warn_only = array_key_exists('warn_only', $validation) ? $validation['warn_only'] : [];

      // Perform validations based on type.
      if (array_key_exists('type', $validation)) {
        $success = true;
        switch ($validation['type']) {
          case 'abs':
            $result = in_array('abs', $warn_only) ? 'warning' : 'error';
            if (!is_numeric($value)) {
              $value = null;
            }
            else {
              $value = floatval($value);
              if ($value < 0 || !$this->is_number_integer($value)) {
                array_push($results, ['result' => $result, 'message' => $this->t('@icon Cell @cell (<strong>@key = @value</strong>) does not conform to: <em>@explanation</em> @suggestion', [
                  '@icon' => $icons[$result],
                  '@cell' => $validation['cell'],
                  '@key' => $key,
                  '@value' => $value,
                  '@explanation' => $this->t($this->descriptions[$validation['type']]),
                  '@suggestion' => $this->t('Please @action the value.', ['@action' => $actions[$result]]),
                ])]);
                $success = false;
              }
            }
            break;
          case 'pct':
            $result = in_array('pct', $warn_only) ? 'warning' : 'error';
            if (!is_numeric($value)) {
              $value = null;
            }
            else {
              $value = floatval($value);
              if ($value < 0 || $this->number_precision($value) > 1 || $value > 100) {
                array_push($results, ['result' => $result, 'message' => $this->t('@icon Cell @cell (<strong>@key = @value</strong>) does not conform to: <em>@explanation</em> @suggestion', [
                  '@icon' => $icons[$result],
                  '@cell' => $validation['cell'],
                  '@key' => $key,
                  '@value' => $value,
                  '@explanation' => $this->t($this->descriptions[$validation['type']]),
                  '@suggestion' => $this->t('Please @action the value.', ['@action' => $actions[$result]]),
                ])]);
                $success = false;
              }
            }
            break;
          case 'chg_pct':
            $result = in_array('chg_pct', $warn_only) ? 'warning' : 'error';
            if (!is_numeric($value)) {
              $value = null;
            }
            else {
              $value = floatval($value);
              if ($this->number_precision($value) > 1 || abs($value) > 50) {
                array_push($results, ['result' => $result, 'message' => $this->t('@icon Cell @cell (<strong>@key = @value</strong>) does not conform to: <em>@explanation</em> @suggestion', [
                  '@icon' => $icons[$result],
                  '@cell' => $validation['cell'],
                  '@key' => $key,
                  '@value' => $value,
                  '@explanation' => $this->t($this->descriptions[$validation['type']]),
                  '@suggestion' => $this->t('Please @action the value.', ['@action' => $actions[$result]]),
                ])]);
                $success = false;
              }
            }
            break;
          case 'chg_abs':
            $result = in_array('chg_abs', $warn_only) ? 'warning' : 'error';
            if (!is_numeric($value)) {
              $value = null;
            }
            else {
              $value = floatval($value);
              if (!$this->is_number_integer($value)) {
                array_push($results, ['result' => $result, 'message' => $this->t('@icon Cell @cell (<strong>@key = @value</strong>) does not conform to: <em>@explanation</em> @suggestion', [
                  '@icon' => $icons[$result],
                  '@cell' => $validation['cell'],
                  '@key' => $key,
                  '@value' => $value,
                  '@explanation' => $this->t($this->descriptions[$validation['type']]),
                  '@suggestion' => $this->t('Please @action the value.', ['@action' => $actions[$result]]),
                ])]);
                $success = false;
              }
            }
            break;
        }
        if (is_null($value)) {
          $result = 'warning';
          array_push($results, ['result' => $result, 'message' => $this->t('@icon Cell @cell has a warning: <em>@explanation</em> @suggestion', [
            '@icon' => $icons[$result],
            '@cell' => $validation['cell'],
            '@key' => $key,
            '@explanation' => $this->t($this->descriptions['blank']),
            '@suggestion' => $this->t('Please @action the value.', ['@action' => $actions[$result]]),
          ])]);
        }
        else if ($success) {
          $result = 'success';
          array_push($results, ['result' => $result, 'message' => $this->t('@icon Cell @cell (<strong>@key = @value</strong>) conforms to: <em>@explanation</em>', [
            '@icon' => $icons[$result],
            '@cell' => $validation['cell'],
            '@key' => $key,
            '@value' => $value,
            '@explanation' => $this->t($this->descriptions[$validation['type']])
          ])]);
        }
      }

      // Perform inter-cell same sign validation.
      if (array_key_exists('same_sign', $validation)) {
        $result = in_array('same_sign', $warn_only) ? 'warning' : 'error';
        $related_value = $dataset[$validation['same_sign']];
        if (!(
          (is_null($related_value) && is_null($value)) ||
          ($related_value * $value >= 0)
        )) {
          array_push($results, ['result' => $result, 'message' => $this->t('@icon Cells @cell1 (<strong>@key1 = @value1</strong>) and @cell2 (<strong>@key2 = @value2</strong>) do not conform to: <em>@explanation</em> @suggestion', [
            '@icon' => $icons[$result],
            '@cell1' => $this->validations[$validation['same_sign']]['cell'],
            '@key1' => $validation['same_sign'],
            '@value1' => $related_value,
            '@cell2' => $validation['cell'],
            '@key2' => $key,
            '@value2' => $value ?? 'N/A',
            '@explanation' => $this->t($this->descriptions['same_sign']),
            '@suggestion' => $this->t('Please @action the values.', ['@action' => $actions[$result]]),
          ])]);
        }
        else {
          $result = 'success';
          array_push($results, ['result' => $result, 'message' => $this->t('@icon Cells @cell1 (<strong>@key1 = @value1</strong>) and @cell2 (<strong>@key2 = @value2</strong>) conform to: <em>@explanation</em>', [
            '@icon' => $icons[$result],
            '@cell2' => $validation['cell'],
            '@key2' => $key,
            '@value2' => $value ?? 'N/A',
            '@cell1' => $this->validations[$validation['same_sign']]['cell'],
            '@key1' => $validation['same_sign'],
            '@value1' => $related_value ?? 'N/A',
            '@explanation' => $this->t($this->descriptions['same_sign'])
          ])]);
        }
      }

      // Perform inter-cell equality validation.
      if (array_key_exists('equal', $validation)) {
        $result = in_array('equal', $warn_only) ? 'warning' : 'error';
        $related_value = $dataset[$validation['equal']];
        if (!(
          (is_null($related_value) && is_null($value)) ||
          (abs($value - $related_value) <= PHP_FLOAT_EPSILON)
        )) {
          array_push($results, ['result' => $result, 'message' => $this->t('@icon Cells @cell1 (<strong>@key1 = @value1</strong>) and @cell2 (<strong>@key2 = @value2</strong>) do not conform to: <em>@explanation</em> @suggestion', [
            '@icon' => $icons[$result],
            '@cell1' => $this->validations[$validation['equal']]['cell'],
            '@key1' => $validation['equal'],
            '@value1' => $related_value,
            '@cell2' => $validation['cell'],
            '@key2' => $key,
            '@value2' => $value ?? 'N/A',
            '@explanation' => $this->t($this->descriptions['equal']),
            '@suggestion' => $this->t('Please @action the values.', ['@action' => $actions[$result]]),
          ])]);
        }
        else {
          $result = 'success';
          array_push($results, ['result' => $result, 'message' => $this->t('@icon Cells @cell1 (<strong>@key1 = @value1</strong>) and @cell2 (<strong>@key2 = @value2</strong>) conform to: <em>@explanation</em>', [
            '@icon' => $icons[$result],
            '@cell2' => $validation['cell'],
            '@key2' => $key,
            '@value2' => $value ?? 'N/A',
            '@cell1' => $this->validations[$validation['equal']]['cell'],
            '@key1' => $validation['equal'],
            '@value1' => $related_value ?? 'N/A',
            '@explanation' => $this->t($this->descriptions['equal'])
          ])]);
        }
      }

      // Perform inter-cell sum validation.
      if (array_key_exists('sum', $validation)) {
        $result = in_array('sum', $warn_only) ? 'warning' : 'error';
        foreach ($validation['sum'] as $sum_keys) {
          $sum = array_sum(array_map(function($sum_key) use ($dataset) {
            return $dataset[$sum_key] ?? 0;
          }, $sum_keys));
          if (abs($sum - ($value ?? 0)) > 100) {
            array_push($results, ['result' => $result, 'message' => $this->t('@icon Cell @cell (<strong>@key = @value</strong>) and cells @cells (<strong>sum = @sum</strong>) do not conform to: <em>@explanation</em> @suggestion', [
              '@icon' => $icons[$result],
              '@cell' => $validation['cell'],
              '@key' => $key,
              '@value' => $value ?? 'N/A',
              '@cells' => implode(' + ', array_map(function($sum_key) {
                return $this->validations[$sum_key]['cell'];
              }, $sum_keys)),
              '@sum' => $sum,
              '@explanation' => $this->t($this->descriptions['sum']),
              '@suggestion' => $this->t('Please @action the values.', ['@action' => $actions[$result]]),
            ])]);
          }
          else {
            $result = 'success';
            array_push($results, ['result' => $result, 'message' => $this->t('@icon Cell @cell (<strong>@key = @value</strong>) and cells @cells (<strong>sum = @sum</strong>) conform to: <em>@explanation</em>', [
              '@icon' => $icons[$result],
              '@cell' => $validation['cell'],
              '@key' => $key,
              '@value' => $value ?? 'N/A',
              '@cells' => implode(' + ', array_map(function($sum_key) {
                return $this->validations[$sum_key]['cell'];
              }, $sum_keys)),
              '@sum' => $sum,
              '@explanation' => $this->t($this->descriptions['sum']),
            ])]);
          }
        }
      }

      // Perform inter-sheet previous month validation.
      if (!empty($dataset_crosscheck) && array_key_exists('crosscheck_value', $validation)) {
        $result = in_array('crosscheck_value', $warn_only) ? 'warning' : 'error';
        $previous_value = $dataset_crosscheck[$validation['crosscheck_value']];
        if (!(
          (is_null($previous_value) && is_null($value)) ||
          ($previous_value == $value)
        )) {
          array_push($results, ['result' => $result, 'message' => $this->t('@icon Cell @cell (<strong>@key = @value</strong>) and previous month cell @cell_previous (<strong>@key_previous = @value_previous</strong>) do not conform to: <em>@explanation</em> @suggestion', [
            '@icon' => $icons[$result],
            '@cell' => $validation['cell'],
            '@key' => $key,
            '@value' => $value ?? 'N/A',
            '@cell_previous' => $this->validations[$validation['crosscheck_value']]['cell'],
            '@key_previous' => $validation['crosscheck_value'],
            '@value_previous' => $previous_value ?? 'N/A',
            '@explanation' => $this->t($this->descriptions['crosscheck_value']),
            '@suggestion' => $this->t('Please @action the value.', ['@action' => $actions[$result]]),
          ])]);
        }
        else {
          $result = 'success';
          array_push($results, ['result' => $result, 'message' => $this->t('@icon Cell @cell (<strong>@key = @value</strong>) and previous month cell @cell_previous (<strong>@key_previous = @value_previous</strong>) conform to: <em>@explanation</em>', [
            '@icon' => $icons[$result],
            '@cell' => $validation['cell'],
            '@key' => $key,
            '@value' => $value ?? 'N/A',
            '@cell_previous' => $this->validations[$validation['crosscheck_value']]['cell'],
            '@key_previous' => $validation['crosscheck_value'],
            '@value_previous' => $previous_value ?? 'N/A',
            '@explanation' => $this->t($this->descriptions['crosscheck_value']),
          ])]);
        }
      }
      if (!empty($dataset_crosscheck) && array_key_exists('crosscheck_change_abs', $validation)) {
        $result = in_array('crosscheck_change_abs', $warn_only) ? 'warning' : 'error';
        $previous_value = $dataset_crosscheck[$validation['crosscheck_change_abs']];
        $current_value = $dataset[$validation['crosscheck_change_abs']];
        $diff = is_numeric($current_value) && $current_value > 0 ? $current_value - $previous_value : NAN;
        if (is_nan($diff) || abs($diff - $value) > PHP_FLOAT_EPSILON) {
          array_push($results, ['result' => $result, 'message' => $this->t('@icon Cell @cell (<strong>@key = @value</strong>) and monthly difference in @cell_previous (<strong>absolute difference = @diff</strong>) do not conform to: <em>@explanation</em> @suggestion', [
            '@icon' => $icons[$result],
            '@cell' => $validation['cell'],
            '@key' => $key,
            '@value' => $value ?? 'N/A',
            '@cell_previous' => $this->validations[$validation['crosscheck_change_abs']]['cell'],
            '@diff' => $diff,
            '@explanation' => $this->t($this->descriptions['crosscheck_change_abs']),
            '@suggestion' => $this->t('Please @action the values.', ['@action' => $actions[$result]]),
          ])]);
        }
        else {
          $result = 'success';
          array_push($results, ['result' => $result, 'message' => $this->t('@icon Cell @cell (<strong>@key = @value</strong>) and monthly difference in @cell_previous (<strong>absolute difference = @diff</strong>) conform to: <em>@explanation</em>', [
            '@icon' => $icons[$result],
            '@cell' => $validation['cell'],
            '@key' => $key,
            '@value' => $value ?? 'N/A',
            '@cell_previous' => $this->validations[$validation['crosscheck_change_abs']]['cell'],
            '@diff' => $diff,
            '@explanation' => $this->t($this->descriptions['crosscheck_change_abs']),
          ])]);
        }
      }
      if (!empty($dataset_crosscheck) && array_key_exists('crosscheck_change_pct', $validation)) {
        $result = in_array('crosscheck_change_pct', $warn_only) ? 'warning' : 'error';
        $previous_value = $dataset_crosscheck[$validation['crosscheck_change_pct']];
        $current_value = $dataset[$validation['crosscheck_change_pct']];
        $diff = is_numeric($current_value) && $current_value > 0 ? round(($current_value - $previous_value) * 100 / $current_value, 1) : NAN;
        if (is_nan($diff) || abs($diff - $value) > PHP_FLOAT_EPSILON) {
          array_push($results, ['result' => $result, 'message' => $this->t('@icon Cell @cell (<strong>@key = @value</strong>) and monthly difference in @cell_previous (<strong>difference percentage = @diff</strong>) do not conform to: <em>@explanation</em> @suggestion', [
            '@icon' => $icons[$result],
            '@cell' => $validation['cell'],
            '@key' => $key,
            '@value' => $value ?? 'N/A',
            '@cell_previous' => $this->validations[$validation['crosscheck_change_pct']]['cell'],
            '@diff' => $diff,
            '@explanation' => $this->t($this->descriptions['crosscheck_change_pct']),
            '@suggestion' => $this->t('Please @action the values.', ['@action' => $actions[$result]]),
          ])]);
        }
        else {
          $result = 'success';
          array_push($results, ['result' => $result, 'message' => $this->t('@icon Cell @cell (<strong>@key = @value</strong>) and monthly difference in @cell_previous (<strong>difference percentage = @diff</strong>) conform to: <em>@explanation</em>', [
            '@icon' => $icons[$result],
            '@cell' => $validation['cell'],
            '@key' => $key,
            '@value' => $value ?? 'N/A',
            '@cell_previous' => $this->validations[$validation['crosscheck_change_pct']]['cell'],
            '@diff' => $diff,
            '@explanation' => $this->t($this->descriptions['crosscheck_change_pct']),
          ])]);
        }
      }
    }

    return $results;
  }
}
