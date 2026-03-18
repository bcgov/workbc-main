<?php

use Drupal\Core\Url;

$connection = \Drupal::database();

$videos = [];

foreach ([
  ['node', 'body'],
  ['node', 'field_hero_text'],
  ['paragraph', 'field_quote'],
  ['node', 'field_career_overview'],
  ['node', 'field_industry_overview'],
  ['node', 'field_region_overview'],
  ['paragraph', 'field_body']
] as $entity_field) {
  $table = $entity_field[0] . '__' . $entity_field[1];
  $field = $entity_field[1] . '_value';
  $query = $connection->select($table);
  $query->addField($table, $field);
  $query->addField($table, 'entity_id');
  $query->condition($field, '%data-entity-uuid%', 'LIKE');
  $values = $query->execute()->fetchAll();
  foreach ($values as $value) {
    if (preg_match_all('/data-entity-uuid="([a-z0-9-]+)"/', $value->$field, $matches, PREG_SET_ORDER) > 0) {
      foreach ($matches as $match) {
        $media = \Drupal::service('entity.repository')->loadEntityByUuid('media', $match[1]);
        if (isset($media) && $media->bundle() == 'remote_video') {
          $node_id = NULL;
          if ($entity_field[0] === 'node') {
            $node_id = $value->entity_id;
          }
          else if ($entity_field[0] === 'paragraph') {
            $paragraph = \Drupal::entityTypeManager()->getStorage('paragraph')->load($value->entity_id);
            while ($paragraph->get('parent_type')->value === 'paragraph') {
              $paragraph = \Drupal::entityTypeManager()->getStorage('paragraph')->load($paragraph->get('parent_id')->value);
            }
            $node_id = $paragraph->get('parent_id')->value;
          }
          if (isset($node_id)) {
            $videos[] = Url::fromRoute('entity.node.canonical',
              ['node' => $node_id],
              ['absolute' => FALSE]
            )->toString();
          }
        }
      }
    }
  }
}

echo join("\n", array_unique($videos)) . "\n";
