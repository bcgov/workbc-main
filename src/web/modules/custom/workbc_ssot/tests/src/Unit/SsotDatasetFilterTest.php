<?php

namespace Drupal\Tests\workbc_ssot\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Condition;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\workbc_ssot\Plugin\views\filter\SsotDatasetFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the dataset exposed filter of the SSoT upload log view.
 */
#[Group('workbc_ssot')]
#[CoversClass(SsotDatasetFilter::class)]
class SsotDatasetFilterTest extends UnitTestCase {

  protected function filter(): SsotDatasetFilter {
    $filter = new SsotDatasetFilter([], 'workbc_ssot_dataset', []);
    $filter->setStringTranslation($this->getStringTranslationStub());
    return $filter;
  }

  public function testBuildOptionsMergesSourcesAndLoggedValues(): void {
    $sources = [
      ['endpoint' => 'wages', 'period' => '2025/01/01 08:00'],
      ['endpoint' => 'monthly_labour_market_updates', 'period' => '2026/08/01 08:00'],
      ['endpoint' => 'monthly_labour_market_updates', 'period' => '2026/07/01 08:00'],
      ['endpoint' => '', 'period' => ''],
      ['period' => 'no endpoint key'],
    ];
    $logged = [
      'industry_outlook',
      'labour_force_survey_industry,labour_force_survey_regional_employment',
      ' wages ',
      '',
    ];
    $this->assertSame([
      'industry_outlook' => 'industry_outlook',
      'labour_force_survey_industry' => 'labour_force_survey_industry',
      'labour_force_survey_regional_employment' => 'labour_force_survey_regional_employment',
      'monthly_labour_market_updates' => 'monthly_labour_market_updates',
      'wages' => 'wages',
    ], SsotDatasetFilter::buildOptions($sources, $logged));

    $this->assertSame([], SsotDatasetFilter::buildOptions([]));
  }

  public function testSplit(): void {
    $this->assertSame(['a', 'b', 'c'], SsotDatasetFilter::split('a, b,,c,'));
    $this->assertSame([], SsotDatasetFilter::split(''));
  }

  public function testOnlyInAndNotInOperatorsAreOffered(): void {
    $filter = $this->filter();
    $this->assertSame(['in', 'not in'], array_keys($filter->operators()));
  }

  /**
   * Build a filter wired to a mocked query and return the condition it adds.
   */
  protected function conditionFor(string $operator, array $values): Condition {
    $connection = $this->createMock(Connection::class);
    $connection->method('condition')->willReturnCallback(fn($conjunction) => new Condition($conjunction));
    $connection->method('escapeLike')->willReturnCallback(fn($s) => addcslashes($s, '\\%_'));

    $added = null;
    $query = $this->createMock(Sql::class);
    $query->method('getConnection')->willReturn($connection);
    $query->method('ensureTable')->willReturn('workbc_ssot_log');
    $query->method('addWhere')->willReturnCallback(function ($group, $field) use (&$added) {
      $added = ['group' => $group, 'condition' => $field];
    });

    $filter = $this->filter();
    $filter->query = $query;
    $filter->table = 'workbc_ssot_log';
    $filter->realField = 'dataset_name';
    $filter->options['group'] = 1;
    $filter->operator = $operator;
    $filter->value = $values;
    $filter->query();

    $this->assertNotNull($added, 'a WHERE condition was added');
    $this->assertSame(1, $added['group']);
    $this->assertInstanceOf(Condition::class, $added['condition']);
    return $added['condition'];
  }

  /**
   * Flatten a Condition into [field, value, operator] triples.
   */
  protected static function triples(Condition $condition): array {
    $out = [];
    foreach ($condition->conditions() as $key => $clause) {
      if ($key !== '#conjunction') {
        $out[] = [$clause['field'], $clause['value'], $clause['operator']];
      }
    }
    return $out;
  }

  public function testInMatchesAnyListElement(): void {
    $condition = $this->conditionFor('in', ['industry_outlook']);
    $this->assertSame('OR', $condition->conditions()['#conjunction']);
    $this->assertSame([
      ['workbc_ssot_log.dataset_name', 'industry_outlook', '='],
      // '_' is a LIKE wildcard and must be escaped in the patterns.
      ['workbc_ssot_log.dataset_name', 'industry\\_outlook,%', 'LIKE'],
      ['workbc_ssot_log.dataset_name', '%,industry\\_outlook', 'LIKE'],
      ['workbc_ssot_log.dataset_name', '%,industry\\_outlook,%', 'LIKE'],
    ], static::triples($condition));
  }

  public function testNotInExcludesEveryListElement(): void {
    $condition = $this->conditionFor('not in', ['a_b', 'c']);
    $this->assertSame('AND', $condition->conditions()['#conjunction']);
    $triples = static::triples($condition);
    $this->assertCount(8, $triples);
    // LIKE wildcards in the value are escaped.
    $this->assertSame(['workbc_ssot_log.dataset_name', 'a\_b,%', 'NOT LIKE'], $triples[1]);
    $this->assertSame(['workbc_ssot_log.dataset_name', 'c', '<>'], $triples[4]);
    $this->assertSame(['workbc_ssot_log.dataset_name', '%,c,%', 'NOT LIKE'], $triples[7]);
  }

  public function testEmptyValueAddsNoCondition(): void {
    $query = $this->createMock(Sql::class);
    $query->expects($this->never())->method('addWhere');
    $filter = $this->filter();
    $filter->query = $query;
    $filter->operator = 'in';
    $filter->value = [];
    $filter->query();
  }

}
