<?php

namespace Drupal\workbc_custom\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a WorkBC Explore Careers Search Block.
 *
 * @Block(
 *   id = "site_search_explore_careers_block",
 *   admin_label = @Translation("WorkBC Site Search > Explore Careers block"),
 *   category = @Translation("WorkBC"),
 * )
 */
class SiteSearchExploreCareersBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    $matches = null;
    // Discard searches for "job"/"jobs", but add option to redirect everything else to Explore Careers.
    return preg_match('/\bjob|jobs\b/i', $this->getKeywords(), $matches) ? AccessResult::forbidden() : AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $keywords = $this->getKeywords();
    $paths = \Drupal::config('workbc')->get('paths');
    return Link::fromTextAndUrl(
      t('Looking for career information? Use our new search function to browse and filter over 500 career profiles.'),
      Url::fromUri('internal:' . $paths['career_exploration_search'], [
        'query' => [
          'hide_category' => 0,
          'keyword_search' => $keywords,
          'sort_bef_combine' => empty($keywords) ? 'title_ASC' : 'keyword_search_ASC',
        ]
      ])
    )->toRenderable();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  protected function getKeywords() {
    return trim(\Drupal::request()->query->get('search_api_fulltext', ''));
  }

}
