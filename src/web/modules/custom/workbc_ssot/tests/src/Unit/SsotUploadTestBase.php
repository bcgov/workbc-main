<?php

namespace Drupal\Tests\workbc_ssot\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Shared plumbing for the SSoT upload engine tests.
 */
abstract class SsotUploadTestBase extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // DateHelper::monthNames() and TranslatableMarkup need a translation service.
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Absolute path of a file under tests/fixtures.
   */
  protected static function fixture(string $name): string {
    return dirname(__DIR__, 2) . '/fixtures/' . $name;
  }

  /**
   * Load a fixture workbook.
   */
  protected static function workbook(string $name): Spreadsheet {
    return IOFactory::load(static::fixture($name));
  }

  /**
   * Industry lookup rows from the migrator's industries.csv.
   */
  public static function industries(): array {
    $rows = [];
    foreach (array_map('str_getcsv', file(static::fixture('industries.csv'))) as $row) {
      $rows[] = ['key' => $row[0], 'name' => $row[1]];
    }
    return $rows;
  }

  /**
   * Region lookup rows, mirroring ssotRegions().
   */
  public static function regions(): array {
    return [
      'british_columbia' => 'British Columbia',
      'cariboo' => 'Cariboo',
      'kootenay' => 'Kootenay',
      'mainland_southwest' => 'Mainland/Southwest',
      'north_coast_nechako' => 'North Coast and Nechako',
      'northeast' => 'Northeast',
      'thompson_okanagan' => 'Thompson-Okanagan',
      'vancouver_island_coast' => 'Vancouver Island/Coast',
    ];
  }

  /**
   * Read a migrator CSV, optionally skipping a header row.
   */
  protected static function csv(string $name, bool $header = false): array {
    $rows = array_map('str_getcsv', file(static::fixture($name)));
    return $header ? array_slice($rows, 1) : $rows;
  }

  /**
   * Plain-text message of a validation result.
   */
  protected static function text(array $result): string {
    return strip_tags((string) $result['message']);
  }

  /**
   * Count results by kind.
   */
  protected static function tally(array $results): array {
    return array_merge(['success' => 0, 'warning' => 0, 'error' => 0], array_count_values(array_column($results, 'result')));
  }

  /**
   * Messages of results of a given kind.
   */
  protected static function messages(array $results, string $kind): array {
    return array_map([static::class, 'text'], array_values(array_filter($results, fn($r) => $r['result'] === $kind)));
  }

  /**
   * Assert two payload cells agree: both NULL, or numerically equal.
   */
  protected function assertCellEquals($expected, $actual, string $label): void {
    $expected = is_numeric($expected) ? $expected + 0 : null;
    if (is_null($expected)) {
      $this->assertNull($actual, "$label expected NULL");
    }
    else {
      $this->assertNotNull($actual, "$label expected $expected, got NULL");
      $this->assertEqualsWithDelta($expected, $actual, 1e-9, $label);
    }
  }

}
