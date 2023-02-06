<?php

use Drupal\redirect\Entity\Redirect;

/**
 * Add redirections of the form http://www.workbc.ca/Job-Seekers/Career-Profiles/[NOC]
 *
 * As per ticket WR-1531.
 */
function workbc_custom_post_update_1531(&$sandbox = NULL) {
  if (!isset($sandbox['nocs'])) {
    $connection = \Drupal::database();
    $query = $connection->select('node__field_noc');
    $query->condition('node__field_noc.bundle', 'career_profile');
    $query->addField('node__field_noc', 'entity_id');
    $query->addField('node__field_noc', 'field_noc_value');
    $sandbox['nocs'] = $query->execute()->fetchAll();
    $sandbox['count'] = count($sandbox['nocs']);
  }

  $noc = array_pop($sandbox['nocs']);
  if (!empty($noc)) {
    $source = 'Job-Seekers/Career-Profiles/' . $noc->field_noc_value . '.aspx';
    $target_url = 'internal:/node/' . $noc->entity_id;
    try {
      Redirect::create([
        'redirect_source' => $source,
        'redirect_redirect' => $target_url,
        'language' => 'und',
        'status_code' => '301',
      ])->save();
      Redirect::create([
        'redirect_source' => str_replace('.aspx', '', $source),
        'redirect_redirect' => $target_url,
        'language' => 'und',
        'status_code' => '301',
      ])->save();
    }
    catch (Exception $e) {
      // Do nothing.
    }
  }

  $sandbox['#finished'] = empty($sandbox['nocs']) ? 1 : ($sandbox['count'] - count($sandbox['nocs'])) / $sandbox['count'];
  return t('[WR-1531] Added new redirection for career profile.');
}
