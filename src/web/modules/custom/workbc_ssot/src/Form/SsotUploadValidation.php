<?php

namespace Drupal\workbc_ssot\Form;

use GuzzleHttp\Client;

/**
 * Declarative cell validation engine shared by the SSoT upload forms.
 *
 * The consuming class must provide a public `$descriptions` array mapping each
 * rule name (and 'blank') to a human-readable explanation.
 */
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
      case 'DELETE':
        $options['body'] = $body;
        $response = $client->request($method, $ssot . $url, $options);
        break;
    }
    return $response;
  }

  /**
   * Resolve a lookup value (industry or region name) to its SSoT key.
   *
   * Returns the key, or NULL when the name is unknown. Subclasses may override
   * to provide extra lookup types.
   */
  public function resolveLookup($type, $value, array $rule) {
    switch ($type) {
      case 'industry':
        return $this->industryKey($value, $rule['exceptions'] ?? []);
      case 'region':
        return $this->regionKey($value);
    }
    return null;
  }

  /**
   * Require a field to be blank when another field has a specified value.
   */
  protected function validateBlankIf(string $field, array &$row, array $options, array $fields): array {
    if (!isset($options['field']) || !is_string($options['field']) ||
        !array_key_exists('value', $options) || (!is_scalar($options['value']) && $options['value'] !== null) ||
        !array_key_exists($options['field'], $row) ||
        !array_key_exists($field, $row) ||
        !isset($fields[$options['field']]) || !is_array($fields[$options['field']]) ||
        array_diff(array_keys($options), ['field', 'value'])) {
      throw new \LogicException("Invalid blank-if options for field $field.");
    }

    $related_field = $options['field'];
    $related_value = $row[$related_field];
    $related_rule = $fields[$related_field];
    $related_value = $this->resolveLookup($related_rule['type'] ?? null, $related_value, $related_rule) ?? $related_value;
    if ($related_value !== $options['value']) {
      return ['success' => true, 'action' => 'continue'];
    }
    return [
      'success' => $row[$field] === null,
      'explanation' => html_entity_decode((string) $this->t('must be blank when @field = @value.', [
        '@field' => $related_field,
        '@value' => $options['value'],
      ]), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
      'action' => 'skip',
    ];
  }


    /**
   * Require a field to be blank when field has a valid "not available" value.
   */
  protected function validateBlankIfNull(string $field, array &$row, array $options, array $fields): array {

    return ['success' => true, 'action' => $row[$field] === null ? 'skip' : 'continue'];
  }

  /**
   * Mirror of the SSoT SQL function normalize_industry_name().
   */
  public function industryKey($name, array $exceptions = []) {
    if (is_null($name) || $name === '') {
      return null;
    }
    foreach ($exceptions as $exception) {
      if (strcasecmp(trim($name), $exception) === 0) {
        return $exception;
      }
    }
    if (is_null($this->industry_keys)) {
      $this->industry_keys = [];
      foreach ($this->loadIndustries() as $industry) {
        $this->industry_keys[$this->normalizeName($industry['name'])] = $industry['key'];
      }
    }
    return $this->industry_keys[$this->normalizeName($name)] ?? null;
  }

  /**
   * Mirror of the SSoT SQL function normalize_region_name().
   */
  public function regionKey($name) {
    if (is_null($name) || $name === '') {
      return null;
    }
    if (is_null($this->region_keys)) {
      $this->region_keys = [];
      foreach ($this->loadRegions() as $key => $label) {
        $this->region_keys[$this->normalizeName($label)] = $key;
      }
      $this->region_keys[$this->normalizeName('Lower Mainland Southwest')] = 'mainland_southwest';
    }
    return $this->region_keys[$this->normalizeName($name)] ?? null;
  }

  /**
   * Industry list as [['key' => ..., 'name' => ...], ...]. Overridable for tests.
   */
  protected function loadIndustries(): array {
    return querySSoT('industries') ?? [];
  }

  /**
   * Region list as [key => label, ...]. Overridable for tests.
   */
  protected function loadRegions(): array {
    return ssotRegions();
  }

  private ?array $industry_keys = null;
  private ?array $region_keys = null;
  private ?array $skill_names = null;
  private ?array $interest_names = null;
  private ?array $teer_values = null;

  /**
   * TEER categories. Overridable for tests.
   */
  protected function loadTeers(): array {
    return array_keys(ssotTEERValues());
  }

  /**
  * Occupational interest names. Overridable for tests.
  */
  protected function loadInterests(): array {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('occupational_interests');
    return array_map(fn($term) => $term->name, $terms);
  }

  /**
  * Skill names. Overridable for tests.
  */
  protected function loadSkills(): array {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('skills');
    return array_map(fn($term) => $term->name, $terms);
  }

  /**
   * Same normalization as the SQL side: drop "with", "and", commas, whitespace
   * and common punctuation, then lowercase.
   */
  public function normalizeName($name) {
    return strtolower(preg_replace('/with|and|,|\s+|[\/\-&\.()]/i', '', (string) $name));
  }


  /**
   * Check a value (skill, occupational interest or TEER) against its known values.
   *
   * Returns TRUE when the value is known, FALSE when it is not, or NULL for an
   * unsupported type. Subclasses may override to provide extra string types.
   */
  public function validateString($type, $value, array $rule) {
    switch ($type) {
      case 'skill':
        return $this->validSkill($value);
      case 'interest':
        return $this->validInterests($value);
      case 'teer':
        return $this->validTeer($value);
    }
    return null;
  }

  protected function validTeer($value): bool {
    $this->teer_values ??= array_map('strval', $this->loadTeers());
    return in_array(trim((string) $value), $this->teer_values, true);
  }

  protected function validSkill($value): bool {
    $this->skill_names ??= array_map('strtolower', $this->loadSkills());
    return in_array(strtolower(trim((string) $value)), $this->skill_names, true);
  }

  protected function validInterests($value): bool {
    $this->interest_names ??= array_map('strtolower', $this->loadInterests());
    $interests = array_filter(array_map('trim', explode(',', (string) $value)), 'strlen');
    return $interests && !array_diff(array_map('strtolower', $interests), $this->interest_names);
  }

  /**
   * Validate one row of values against a rule set.
   *
   * @param array $rules
   *   Map of field key => rule. Each rule has a 'cell' (absolute cell address
   *   for record-shaped datasets, or column letter for table-shaped datasets)
   *   and optionally 'type', 'same_sign', 'equal', 'sum', 'crosscheck_value',
   *   'crosscheck_change_abs', 'crosscheck_change_pct', 'validators',
   *   'warn_only'.
   * @param array $dataset
   *   Map of field key => value for the row being validated.
   * @param array|null $dataset_crosscheck
   *   Same shape as $dataset, holding the previous period's values, or NULL.
   * @param int|null $row
   *   Sheet row number appended to column letters for table-shaped datasets.
   * @param string $sheet
   *   Sheet title, prefixed to cell addresses in messages when non-empty.
   * @param bool $custom_only
   *   Run custom validators without the core rules.
   *
   * @return array
   *   List of ['result' => 'error'|'warning'|'success', 'message' => ...].
   */
  function validateRules(array $rules, array $dataset, ?array $dataset_crosscheck = null, ?int $row = null, string $sheet = '', bool $custom_only = false)
  {
    $custom_validators = [];
    foreach ($rules as $key => $rule) {
      if (array_key_exists('blank_if', $rule)) {
        throw new \LogicException("The blank_if rule for field $key has been replaced by the validateBlankIf validator.");
      }
      if (array_key_exists('validators', $rule)) {
        if (!is_array($rule['validators'])) {
          throw new \LogicException("Invalid validators for field $key: expected an array.");
        }
        foreach ($rule['validators'] as $name => $validator) {
          $options = null;
          if (is_array($validator) && !array_is_list($validator)) {
            if (!array_key_exists('callback', $validator) ||
                (array_key_exists('options', $validator) && !is_array($validator['options'])) ||
                array_diff(array_keys($validator), ['callback', 'options'])) {
              throw new \LogicException("Invalid validator definition for field $key.");
            }
            $options = $validator['options'] ?? [];
            $validator = $validator['callback'];
          }
          $label = is_string($name) ? $name : (is_string($validator) ? $validator : (string) ($name + 1));
          if ($label === '') {
            throw new \LogicException("Invalid validator name for field $key.");
          }
          $callback = is_string($validator) && is_callable([$this, $validator])
            ? [$this, $validator] : $validator;
          if (!is_callable($callback)) {
            throw new \LogicException("Invalid validator $label for field $key.");
          }
          $callback = \Closure::fromCallable($callback);
          $signature = new \ReflectionFunction($callback);
          $custom_validators[$key][] = [
            'name' => $label, 'callback' => $callback, 'options' => $options,
            'accepts_fields' => $signature->isVariadic() || $signature->getNumberOfParameters() >= 4,
          ];
        }
      }
    }

    $icons = [
      'error' => '❌',
      'warning' => '❗',
      'success' => '✅',
    ];
    $actions = [
      'error' => 'correct',
      'warning' => 'review',
    ];
    $cellOf = function ($key) use ($rules, $row, $sheet) {
      return ($sheet !== '' ? "$sheet!" : '') . ($rules[$key]['cell'] ?? '') . ($row ?? '');
    };
    $results = [];
    foreach ($rules as $key => $validation) {
      if (($validation['type'] ?? null) === 'ignore') {
        continue;
      }
      $value = $dataset[$key] ?? null;
      $cell = $cellOf($key);
      $warn_only = array_key_exists('warn_only', $validation) ? $validation['warn_only'] : [];

      foreach ($custom_validators[$key] ?? [] as $validator) {
        $location = isset($validation['cell']) ? $cell :
          (($sheet !== '' ? "$sheet!" : '') . ($row !== null ? "row $row" : ''));
        $where = $location !== '' ? " at $location" : '';
        $validator_row = $dataset;
        $validator_fields = $rules;
        try {
          if ($validator['accepts_fields']) {
            $check = ($validator['callback'])($key, $validator_row, $validator['options'] ?? [], $validator_fields);
          }
          elseif ($validator['options'] === null) {
            $check = ($validator['callback'])($key, $validator_row);
          }
          else {
            $check = ($validator['callback'])($key, $validator_row, $validator['options']);
          }
        }
        catch (\Throwable $error) {
          throw new \UnexpectedValueException("Validator {$validator['name']} failed for field $key$where: " . $error->getMessage(), 0, $error);
        }
        if (!is_array($check) || !isset($check['success']) || !is_bool($check['success']) ||
            (array_key_exists('explanation', $check) && !is_string($check['explanation'])) ||
            (array_key_exists('suggestion', $check) && !is_string($check['suggestion'])) ||
            (array_key_exists('action', $check) && !in_array($check['action'], ['continue', 'skip'], true))) {
          throw new \UnexpectedValueException("Invalid result from validator {$validator['name']} for field $key$where.");
        }
        $result = $check['success'] ? 'success' :
          (in_array('validators', $warn_only, true) || in_array($validator['name'], $warn_only, true) ? 'warning' : 'error');
        $explanation = $check['explanation'] ?? '';
        if ($result !== 'success' || trim($explanation) !== '') {
          $arguments = [
            '@icon' => $icons[$result],
            '@cell' => $location,
            '@key' => $key,
            '@value' => $value ?? 'N/A',
            '@explanation' => trim($explanation) !== '' ? $explanation : $this->t('No explanation given.'),
          ];
          if ($result === 'success') {
            $message = $this->t('@icon @cell (<strong>@key = @value</strong>) conforms to: <em>@explanation</em>', $arguments);
          }
          else {
            $arguments['@suggestion'] = $check['suggestion'] ?? $this->t('Please @action the value.', ['@action' => $actions[$result]]);
            $message = $this->t('@icon @cell (<strong>@key = @value</strong>) does not conform to: <em>@explanation</em> @suggestion', $arguments);
          }
          $results[] = ['result' => $result, 'message' => $message];
        }
        if (($check['action'] ?? 'continue') === 'skip') {
          continue 2;
        }
      }

      if ($custom_only || ($validation['type'] ?? null) === 'synthetic') {
        continue;
      }

      // Perform validations based on type.
      if (array_key_exists('type', $validation)) {
        $type = $validation['type'];
        $result = in_array($type, $warn_only) ? 'warning' : 'error';
        $success = true;

        // NOC codes are text identifiers: they skip the numeric checks and only
        // a blank is reported.
        if ($type === 'noc') {
          if (is_null($value) || ltrim(trim((string) $value), '#') === '') {
            array_push($results, ['result' => $result, 'message' => $this->t('@icon @cell (<strong>@key</strong>) is blank: <em>@explanation</em> @suggestion', [
              '@icon' => $icons[$result],
              '@cell' => $cell,
              '@key' => $key,
              '@explanation' => $this->t($this->descriptions[$type]),
              '@suggestion' => $this->t('Please @action the value.', ['@action' => $actions[$result]]),
            ])]);
          }
          continue;
        }

        $is_lookup = in_array($type, ['industry', 'region']);

        if ($is_lookup) {
          if (is_null($value) || trim((string) $value) === '') {
            array_push($results, ['result' => 'error', 'message' => $this->t('@icon @cell (<strong>@key</strong>) is blank: <em>@explanation</em> @suggestion', [
              '@icon' => $icons['error'],
              '@cell' => $cell,
              '@key' => $key,
              '@explanation' => $this->t($this->descriptions[$type]),
              '@suggestion' => $this->t('Please correct the value.'),
            ])]);
            continue;
          }
          $resolved = $this->resolveLookup($type, $value, $validation);
          if (is_null($resolved)) {
            array_push($results, ['result' => $result, 'message' => $this->t('@icon @cell (<strong>@key = @value</strong>) does not conform to: <em>@explanation</em> @suggestion', [
              '@icon' => $icons[$result],
              '@cell' => $cell,
              '@key' => $key,
              '@value' => $value,
              '@explanation' => $this->t($this->descriptions[$type]),
              '@suggestion' => $this->t('Please @action the value.', ['@action' => $actions[$result]]),
            ])]);
          }
          else {
            array_push($results, ['result' => 'success', 'message' => $this->t('@icon @cell (<strong>@key = @value</strong>) conforms to: <em>@explanation</em> (<code>@resolved</code>)', [
              '@icon' => $icons['success'],
              '@cell' => $cell,
              '@key' => $key,
              '@value' => $value,
              '@resolved' => $resolved,
              '@explanation' => $this->t($this->descriptions[$type]),
            ])]);
          }
          continue;
        }

        $is_lookup = in_array($type, ['skill', 'interest', 'teer']);
        if ($is_lookup) {
          if (is_null($value) || trim((string) $value) === '') {
            array_push($results, ['result' => 'error', 'message' => $this->t('@icon @cell (<strong>@key</strong>) is blank: <em>@explanation</em> @suggestion', [
              '@icon' => $icons['error'],
              '@cell' => $cell,
              '@key' => $key,
              '@explanation' => $this->t($this->descriptions[$type]),
              '@suggestion' => $this->t('Please correct the value.'),
            ])]);
            continue;
          }
          $exists = $this->validateString($type, $value, $validation);
          if ($exists) {
            array_push($results, ['result' => 'success', 'message' => $this->t('@icon @cell (<strong>@key = @value</strong>) conforms to: <em>@explanation</em>', [
              '@icon' => $icons['success'],
              '@cell' => $cell,
              '@key' => $key,
              '@value' => $value,
              '@explanation' => $this->t($this->descriptions[$type]),
            ])]);
          }
          else {
            array_push($results, ['result' => $result, 'message' => $this->t('@icon @cell (<strong>@key = @value</strong>) does not conform to: <em>@explanation</em> @suggestion', [
              '@icon' => $icons[$result],
              '@cell' => $cell,
              '@key' => $key,
              '@value' => $value,
              '@explanation' => $this->t($this->descriptions[$type]),
              '@suggestion' => $this->t('Please @action the value.', ['@action' => $actions[$result]]),
            ])]);
          }
          continue;
        }

        if (!is_numeric($value)) {
          $value = null;
        }
        else {
          $value = floatval($value);
          switch ($type) {
            case 'abs':
              $success = !($value < 0 || !$this->is_number_integer($value));
              break;
            case 'num':
              $success = !($value < 0);
              break;
            case 'pct':
              $success = !($value < 0 || $this->number_precision($value) > 1 || $value > 100);
              break;
            case 'chg_pct':
              $success = !($this->number_precision($value) > 1 || abs($value) > 50);
              break;
            case 'chg_abs':
              $success = $this->is_number_integer($value);
              break;
          }
          if (!$success) {
            array_push($results, ['result' => $result, 'message' => $this->t('@icon @cell (<strong>@key = @value</strong>) does not conform to: <em>@explanation</em> @suggestion', [
              '@icon' => $icons[$result],
              '@cell' => $cell,
              '@key' => $key,
              '@value' => $value,
              '@explanation' => $this->t($this->descriptions[$type]),
              '@suggestion' => $this->t('Please @action the value.', ['@action' => $actions[$result]]),
            ])]);
          }
        }
        if (is_null($value)) {
          $result = 'warning';
          array_push($results, ['result' => $result, 'message' => $this->t('@icon @cell has a warning: <em>@explanation</em> @suggestion', [
            '@icon' => $icons[$result],
            '@cell' => $cell,
            '@key' => $key,
            '@explanation' => $this->t($this->descriptions['blank']),
            '@suggestion' => $this->t('Please @action the value.', ['@action' => $actions[$result]]),
          ])]);
        }
        else if ($success) {
          $result = 'success';
          array_push($results, ['result' => $result, 'message' => $this->t('@icon @cell (<strong>@key = @value</strong>) conforms to: <em>@explanation</em>', [
            '@icon' => $icons[$result],
            '@cell' => $cell,
            '@key' => $key,
            '@value' => $value,
            '@explanation' => $this->t($this->descriptions[$type])
          ])]);
        }
      }

      // Perform inter-cell same sign validation.
      if (array_key_exists('same_sign', $validation)) {
        $result = in_array('same_sign', $warn_only) ? 'warning' : 'error';
        $related_value = $dataset[$validation['same_sign']] ?? null;
        if (!(
          (is_null($related_value) && is_null($value)) ||
          ($related_value * $value >= 0)
        )) {
          array_push($results, ['result' => $result, 'message' => $this->t('@icon @cell1 (<strong>@key1 = @value1</strong>) and @cell2 (<strong>@key2 = @value2</strong>) do not conform to: <em>@explanation</em> @suggestion', [
            '@icon' => $icons[$result],
            '@cell1' => $cellOf($validation['same_sign']),
            '@key1' => $validation['same_sign'],
            '@value1' => $related_value,
            '@cell2' => $cell,
            '@key2' => $key,
            '@value2' => $value ?? 'N/A',
            '@explanation' => $this->t($this->descriptions['same_sign']),
            '@suggestion' => $this->t('Please @action the values.', ['@action' => $actions[$result]]),
          ])]);
        }
        else {
          $result = 'success';
          array_push($results, ['result' => $result, 'message' => $this->t('@icon @cell1 (<strong>@key1 = @value1</strong>) and @cell2 (<strong>@key2 = @value2</strong>) conform to: <em>@explanation</em>', [
            '@icon' => $icons[$result],
            '@cell2' => $cell,
            '@key2' => $key,
            '@value2' => $value ?? 'N/A',
            '@cell1' => $cellOf($validation['same_sign']),
            '@key1' => $validation['same_sign'],
            '@value1' => $related_value ?? 'N/A',
            '@explanation' => $this->t($this->descriptions['same_sign'])
          ])]);
        }
      }

      // Perform inter-cell equality validation.
      if (array_key_exists('equal', $validation)) {
        $result = in_array('equal', $warn_only) ? 'warning' : 'error';
        $related_value = $dataset[$validation['equal']] ?? null;
        if (!(
          (is_null($related_value) && is_null($value)) ||
          (abs($value - $related_value) <= PHP_FLOAT_EPSILON)
        )) {
          array_push($results, ['result' => $result, 'message' => $this->t('@icon @cell1 (<strong>@key1 = @value1</strong>) and @cell2 (<strong>@key2 = @value2</strong>) do not conform to: <em>@explanation</em> @suggestion', [
            '@icon' => $icons[$result],
            '@cell1' => $cellOf($validation['equal']),
            '@key1' => $validation['equal'],
            '@value1' => $related_value,
            '@cell2' => $cell,
            '@key2' => $key,
            '@value2' => $value ?? 'N/A',
            '@explanation' => $this->t($this->descriptions['equal']),
            '@suggestion' => $this->t('Please @action the values.', ['@action' => $actions[$result]]),
          ])]);
        }
        else {
          $result = 'success';
          array_push($results, ['result' => $result, 'message' => $this->t('@icon @cell1 (<strong>@key1 = @value1</strong>) and @cell2 (<strong>@key2 = @value2</strong>) conform to: <em>@explanation</em>', [
            '@icon' => $icons[$result],
            '@cell2' => $cell,
            '@key2' => $key,
            '@value2' => $value ?? 'N/A',
            '@cell1' => $cellOf($validation['equal']),
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
          $cells = implode(' + ', array_map($cellOf, $sum_keys));
          if (abs($sum - ($value ?? 0)) > 100) {
            array_push($results, ['result' => $result, 'message' => $this->t('@icon @cell (<strong>@key = @value</strong>) and cells @cells (<strong>sum = @sum</strong>) do not conform to: <em>@explanation</em> @suggestion', [
              '@icon' => $icons[$result],
              '@cell' => $cell,
              '@key' => $key,
              '@value' => $value ?? 'N/A',
              '@cells' => $cells,
              '@sum' => $sum,
              '@explanation' => $this->t($this->descriptions['sum']),
              '@suggestion' => $this->t('Please @action the values.', ['@action' => $actions[$result]]),
            ])]);
          }
          else {
            $result = 'success';
            array_push($results, ['result' => $result, 'message' => $this->t('@icon @cell (<strong>@key = @value</strong>) and cells @cells (<strong>sum = @sum</strong>) conform to: <em>@explanation</em>', [
              '@icon' => $icons[$result],
              '@cell' => $cell,
              '@key' => $key,
              '@value' => $value ?? 'N/A',
              '@cells' => $cells,
              '@sum' => $sum,
              '@explanation' => $this->t($this->descriptions['sum']),
            ])]);
          }
        }
      }

      // Perform inter-sheet previous month validation.
      if (!empty($dataset_crosscheck) && array_key_exists('crosscheck_value', $validation)) {
        $result = in_array('crosscheck_value', $warn_only) ? 'warning' : 'error';
        $previous_value = $dataset_crosscheck[$validation['crosscheck_value']] ?? null;
        if (!(
          (is_null($previous_value) && is_null($value)) ||
          ($previous_value == $value)
        )) {
          array_push($results, ['result' => $result, 'message' => $this->t('@icon @cell (<strong>@key = @value</strong>) and previous month cell @cell_previous (<strong>@key_previous = @value_previous</strong>) do not conform to: <em>@explanation</em> @suggestion', [
            '@icon' => $icons[$result],
            '@cell' => $cell,
            '@key' => $key,
            '@value' => $value ?? 'N/A',
            '@cell_previous' => $cellOf($validation['crosscheck_value']),
            '@key_previous' => $validation['crosscheck_value'],
            '@value_previous' => $previous_value ?? 'N/A',
            '@explanation' => $this->t($this->descriptions['crosscheck_value']),
            '@suggestion' => $this->t('Please @action the value.', ['@action' => $actions[$result]]),
          ])]);
        }
        else {
          $result = 'success';
          array_push($results, ['result' => $result, 'message' => $this->t('@icon @cell (<strong>@key = @value</strong>) and previous month cell @cell_previous (<strong>@key_previous = @value_previous</strong>) conform to: <em>@explanation</em>', [
            '@icon' => $icons[$result],
            '@cell' => $cell,
            '@key' => $key,
            '@value' => $value ?? 'N/A',
            '@cell_previous' => $cellOf($validation['crosscheck_value']),
            '@key_previous' => $validation['crosscheck_value'],
            '@value_previous' => $previous_value ?? 'N/A',
            '@explanation' => $this->t($this->descriptions['crosscheck_value']),
          ])]);
        }
      }
      if (!empty($dataset_crosscheck) && array_key_exists('crosscheck_change_abs', $validation)) {
        $result = in_array('crosscheck_change_abs', $warn_only) ? 'warning' : 'error';
        $previous_value = $dataset_crosscheck[$validation['crosscheck_change_abs']] ?? null;
        $current_value = $dataset[$validation['crosscheck_change_abs']] ?? null;
        $diff = is_numeric($current_value) && $current_value > 0 ? $current_value - $previous_value : NAN;
        if (is_nan($diff) || abs($diff - $value) > PHP_FLOAT_EPSILON) {
          array_push($results, ['result' => $result, 'message' => $this->t('@icon @cell (<strong>@key = @value</strong>) and monthly difference in @cell_previous (<strong>absolute difference = @diff</strong>) do not conform to: <em>@explanation</em> @suggestion', [
            '@icon' => $icons[$result],
            '@cell' => $cell,
            '@key' => $key,
            '@value' => $value ?? 'N/A',
            '@cell_previous' => $cellOf($validation['crosscheck_change_abs']),
            '@diff' => $diff,
            '@explanation' => $this->t($this->descriptions['crosscheck_change_abs']),
            '@suggestion' => $this->t('Please @action the values.', ['@action' => $actions[$result]]),
          ])]);
        }
        else {
          $result = 'success';
          array_push($results, ['result' => $result, 'message' => $this->t('@icon @cell (<strong>@key = @value</strong>) and monthly difference in @cell_previous (<strong>absolute difference = @diff</strong>) conform to: <em>@explanation</em>', [
            '@icon' => $icons[$result],
            '@cell' => $cell,
            '@key' => $key,
            '@value' => $value ?? 'N/A',
            '@cell_previous' => $cellOf($validation['crosscheck_change_abs']),
            '@diff' => $diff,
            '@explanation' => $this->t($this->descriptions['crosscheck_change_abs']),
          ])]);
        }
      }
      if (!empty($dataset_crosscheck) && array_key_exists('crosscheck_change_pct', $validation)) {
        $result = in_array('crosscheck_change_pct', $warn_only) ? 'warning' : 'error';
        $previous_value = $dataset_crosscheck[$validation['crosscheck_change_pct']] ?? null;
        $current_value = $dataset[$validation['crosscheck_change_pct']] ?? null;
        $diff = is_numeric($current_value) && $current_value > 0 ? round(($current_value - $previous_value) * 100 / $current_value, 1) : NAN;
        if (is_nan($diff) || abs($diff - $value) > PHP_FLOAT_EPSILON) {
          array_push($results, ['result' => $result, 'message' => $this->t('@icon @cell (<strong>@key = @value</strong>) and monthly difference in @cell_previous (<strong>difference percentage = @diff</strong>) do not conform to: <em>@explanation</em> @suggestion', [
            '@icon' => $icons[$result],
            '@cell' => $cell,
            '@key' => $key,
            '@value' => $value ?? 'N/A',
            '@cell_previous' => $cellOf($validation['crosscheck_change_pct']),
            '@diff' => $diff,
            '@explanation' => $this->t($this->descriptions['crosscheck_change_pct']),
            '@suggestion' => $this->t('Please @action the values.', ['@action' => $actions[$result]]),
          ])]);
        }
        else {
          $result = 'success';
          array_push($results, ['result' => $result, 'message' => $this->t('@icon @cell (<strong>@key = @value</strong>) and monthly difference in @cell_previous (<strong>difference percentage = @diff</strong>) conform to: <em>@explanation</em>', [
            '@icon' => $icons[$result],
            '@cell' => $cell,
            '@key' => $key,
            '@value' => $value ?? 'N/A',
            '@cell_previous' => $cellOf($validation['crosscheck_change_pct']),
            '@diff' => $diff,
            '@explanation' => $this->t($this->descriptions['crosscheck_change_pct']),
          ])]);
        }
      }
    }

    return $results;
  }
}
