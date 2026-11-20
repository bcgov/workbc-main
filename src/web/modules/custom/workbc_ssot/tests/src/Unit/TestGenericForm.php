<?php

namespace Drupal\Tests\workbc_ssot\Unit;

use Drupal\workbc_ssot\Form\SsotUploadFormBase;

/**
 * A bare form whose TOC and period are supplied by each test.
 */
class TestGenericForm extends SsotUploadFormBase {
  use EngineAccess;

  protected string $id = 'generic';
  protected string $title = 'Generic';
  protected string $label = 'Generic {year}';

  public function __construct(array $toc = [], array $period = ['granularity' => 'year']) {
    $this->toc = $toc;
    $this->period = $period;
  }

  protected function getRouteName(): string {
    return 'workbc_ssot.generic';
  }

  protected function syntheticTotal(array $row): ?float {
    return $row['num'] === null ? null : $row['num'] + $row['avg'];
  }

  protected function invalidSyntheticValue(array $row): array {
    return $row;
  }

}
