<?php

namespace Drupal\Tests\workbc_ssot\Unit;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exposes the protected engine of SsotUploadFormBase and swaps the lookup
 * sources for fixtures. Mixed into test subclasses of the real forms.
 */
trait EngineAccess {

  protected function loadIndustries(): array {
    return SsotUploadTestBase::industries();
  }

  protected function loadRegions(): array {
    return SsotUploadTestBase::regions();
  }

  public function toc(): array {
    return $this->toc;
  }

  public function setToc(array $toc): void {
    $this->toc = $toc;
  }

  public function period(): array {
    return $this->period;
  }

  public function context(int $year, ?int $month = null): array {
    return $this->buildContext($year, $month);
  }

  public function label(array $context): string {
    return $this->periodLabel($context);
  }

  public function fill(?string $template, array $context): ?string {
    return $this->interpolate($template, $context);
  }

  public function sheet(Spreadsheet $spreadsheet, string $title): ?Worksheet {
    return $this->findSheet($spreadsheet, $title);
  }

  public function rules(array $entry): array {
    return $this->fieldRules($entry);
  }

  public function read(Worksheet $sheet, array $entry, array $context): array {
    return $this->readDataset($sheet, $entry, $context);
  }

  public function prepare(array $entry, array $rows): array {
    return $this->prepareRows($entry, $rows);
  }

  /**
   * Full pipeline for one workbook: read, validate and prepare every dataset.
   *
   * @return array
   *   endpoint => ['rows' => ..., 'first_row' => ..., 'results' => ..., 'payload' => ...]
   */
  public function process(Spreadsheet $spreadsheet, int $year, ?int $month = null, array $crosschecks = []): array {
    $context = $this->buildContext($year, $month);
    $out = [];
    foreach ($this->toc as $endpoint => $entry) {
      $sheet = $this->findSheet($spreadsheet, $entry['sheet']);
      if (!$sheet) {
        throw new \RuntimeException("Tab {$entry['sheet']} not found");
      }
      $rules = $this->fieldRules($entry);
      $data = $this->readDataset($sheet, $entry, $context);
      $sheet_label = count($this->toc) > 1 ? $entry['sheet'] : '';
      $results = [];
      if ($entry['shape'] === 'table') {
        foreach ($data['rows'] as $i => $row) {
          $results = array_merge($results, $this->validateRules($rules, $row, null, $data['first_row'] + $i, $sheet_label));
        }
      }
      else {
        $results = $this->validateRules($rules, $data['rows'][0], $crosschecks[$endpoint] ?? null, null, $sheet_label);
      }
      $out[$endpoint] = $data + ['results' => $results, 'payload' => $this->prepareRows($entry, $data['rows'])];
    }
    return $out;
  }

}
