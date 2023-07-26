<?php
namespace Drupal\workbc_jobboard\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Save Career Profile' Block.
 *
 * @Block(
 *   id = "workbc_jobboard_save_profile",
 *   admin_label = @Translation("Save Profile"),
 *   category = @Translation("WorkBC Job Board"),
 * )
 */

class WorkbcJobboardSaveProfile extends BlockBase {

	/**
   * {@inheritdoc}
   */
	public function build() {
    $node = \Drupal::routeMatch()->getParameter('node');
    if (!($node instanceof \Drupal\node\NodeInterface)) return null;

    $type = $node->bundle();
    switch ($type) {
      case 'career_profile':
        $profile_id = $node?->get('field_noc')?->value ?? '';
        $status = \Drupal::config('jobboard')->get('jobboard_api_url_frontend') . '/api/career-profiles/status/' . $profile_id;
        $save = \Drupal::config('jobboard')->get('jobboard_api_url_frontend') . '/api/career-profiles/save/' . $profile_id;
        break;
      case 'industry_profile':
        $profile_id = explode(',', $node?->get('field_job_board_save_profile_id')?->value ?? '')[0];
        $status = \Drupal::config('jobboard')->get('jobboard_api_url_frontend') . '/api/industry-profiles/status/' . $profile_id;
        $save = \Drupal::config('jobboard')->get('jobboard_api_url_frontend') . '/api/industry-profiles/save/' . $profile_id;
        break;
      default:
        return null;
    }

    return [
      '#type' => 'markup',
      '#theme' => 'save_profile',
      '#attached' => [
        'drupalSettings' => [
          'jobboard' => [
            'status' => $status,
            'save' => $save,
          ]
        ]
      ]
    ];
  }
}
