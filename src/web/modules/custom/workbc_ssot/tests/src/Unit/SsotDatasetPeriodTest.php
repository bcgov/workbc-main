<?php

namespace Drupal\Tests\workbc_ssot\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\ResultRow;
use Drupal\workbc_ssot\Plugin\views\field\SsotDatasetPeriod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests period formatting for single- and multi-dataset uploads.
 */
#[Group('workbc_ssot')]
#[CoversClass(SsotDatasetPeriod::class)]
class SsotDatasetPeriodTest extends UnitTestCase {

  #[DataProvider('periodProvider')]
  public function testPeriodRendering(string $datasets, string $date, string $format, string $expected): void {
    $timestamp = strtotime("$date UTC");
    $formatter = $this->createMock(DateFormatterInterface::class);
    $formatter->expects($this->once())
      ->method('format')
      ->with($timestamp, 'custom', $format, 'UTC')
      ->willReturnCallback(fn($value, $type, $custom) => gmdate($custom, $value));
    $field = new SsotDatasetPeriod(
      [], 'workbc_ssot_dataset_period', [],
      $formatter,
      $this->createMock(EntityStorageInterface::class),
      $this->createMock(TimeInterface::class),
    );
    $field->field_alias = 'period';
    $field->aliases['dataset_name'] = 'datasets';
    $field->options = [
      'date_format' => 'custom',
      'custom_date_format' => 'F Y',
      'timezone' => 'UTC',
    ];
    $this->assertSame($expected, $field->render(new ResultRow([
      'period' => $timestamp,
      'datasets' => $datasets,
    ])));
  }

  public static function periodProvider(): array {
    return [
      'industry outlook' => ['industry_outlook', '2026-01-01', 'Y', '2026'],
      'LFS industry' => ['labour_force_survey_industry', '2024-01-01', 'Y', '2024'],
      'LFS regional employment' => ['labour_force_survey_regional_employment', '2024-01-01', 'Y', '2024'],
      'LFS regional industry' => ['labour_force_survey_regional_industry_region', '2024-01-01', 'Y', '2024'],
      'LFS province industry' => ['labour_force_survey_regional_industry_province', '2024-01-01', 'Y', '2024'],
      'multi-dataset yearly upload' => [
        'labour_force_survey_industry, labour_force_survey_regional_employment,labour_force_survey_regional_industry_region,labour_force_survey_regional_industry_province',
        '2024-01-01', 'Y', '2024',
      ],
      'monthly January is not yearly' => ['monthly_labour_market_updates', '2026-01-01', 'F Y', 'January 2026'],
      'monthly September' => ['monthly_labour_market_updates', '2025-09-01', 'F Y', 'September 2025'],
      'unknown dataset preserves date format' => ['other', '2026-01-01', 'F Y', 'January 2026'],
      'mixed granularities preserve date format' => ['industry_outlook,monthly_labour_market_updates', '2026-01-01', 'F Y', 'January 2026'],
    ];
  }

}
