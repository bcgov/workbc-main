<?php

/**
 * @file
 * CustomModalController class.
 */

namespace Drupal\workbc_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

class ReportsController extends ControllerBase {
  public function unmanaged_files() {
    $unmanaged = getUnmanagedFiles();
    return [[
      '#markup' => 'This is a report of pages containing unmanaged files instead of media library items.<br>
      If the file is being used in a media item, then the match will link to that media item. Otherwise, the type of match is shown along with the raw link that was found.<br>
      Click the <b>Page</b> link, then <b>Edit</b> to edit the page, then look for the field named in the <b>Field</b> column to find the content to be edited.<br>
      Click the <b>Source</b> button of the editor to locate the unmanaged file(s) in the field content.
      It will typically be an HTML tag that references a file like <code>/sites/default/files/filename.pdf</code>.'
    ],[
      '#markup' => '<p>Found <strong>' . count($unmanaged) . '</strong> unmanaged files.'
    ],[
      '#theme' => 'table',
      '#header' => ['Page', 'Field', 'Matches'],
      '#rows' => array_map(function ($file) {
        $node = \Drupal::entityTypeManager()->getStorage('node')->load($file['node_id']);
        return [
          Link::createFromRoute($node->getTitle(), 'entity.node.canonical', ['node' => $file['node_id']], [
            'attributes' => ['target' => '_blank']
          ])->toString(),
          $file['label'],
          [
            'data' => ['#markup' => join('<br>', array_map(function ($m) {
              if (!empty($m['media_id'])) {
                return Link::createFromRoute($m['file_path'], 'media_entity_download.download', ['media' => $m['media_id']], [
                  'attributes' => ['target' => '_blank']
                ])->toString();
              }
              else {
                return $m['type'] .'://' . $m['file_path'];
              }
            }, $file['matches']))]
          ]
        ];
      }, $unmanaged),
    ]];
  }

  public function duplicate_files() {
    $dupes = getDuplicateFiles();
    return [[
      '#markup' => 'This is a report of duplicate files in the Drupal filesystem. For each set of duplicates, the corresponding media library items are shown, if any.',
    ],[
      '#markup' => '<p>Found <strong>' . count($dupes) . '</strong> sets of duplicate files.'
    ],[
      '#theme' => 'table',
      '#header' => ['Duplicates'],
      '#rows' => array_map(function ($duplicates) {
        return [['data' => ['#markup' => join('<br>', array_map(function($d) {
          $cells = [
            Link::fromTextAndUrl(ltrim($d['file_path'], '.'), Url::fromUri(\Drupal::service('file_url_generator')->generateAbsoluteString($d['file_path']), [
              'attributes' => ['target' => '_blank']
            ]))->toString()
          ];
          if (!empty($d['file_id'])) {
            $cells[] = '[' . Link::createFromRoute('File usage', 'view.files.page_2', ['arg_0' => $d['file_id']], [
              'attributes' => ['target' => '_blank']
            ])->toString() . ']';
          }
          if (!empty($d['media_id'])) {
            $cells[] = '[' . Link::createFromRoute('Media usage', 'entity.media.canonical', ['media' => $d['media_id']], [
              'attributes' => ['target' => '_blank']
            ])->toString() . ']';
          }
          if (!empty($d['usages'])) {
            foreach ($d['usages'] as $usage) {
              if ($usage['type'] === 'deleted') {
                $cells[] = 'DELETED MEDIA!!';
                continue;
              }
              if ($usage['entity'] === 'paragraph') {
                $paragraph = \Drupal::entityTypeManager()->getStorage('paragraph')->load($usage['entity_id']);
                while ($paragraph->get('parent_type')->value === 'paragraph') {
                  $paragraph = \Drupal::entityTypeManager()->getStorage('paragraph')->load($paragraph->get('parent_id')->value);
                }
                $node_id = $paragraph->get('parent_id')->value;
              }
              else {
                $node_id = $usage['entity_id'];
              }
              $node = \Drupal::entityTypeManager()->getStorage('node')->load($node_id);
              $cells[] = '[' . Link::createFromRoute($usage['entity'].':'.$usage['entity_id'].':'.$usage['field']/*$node?->getTitle() ?? 'MISSING NODE!!'*/, 'entity.node.canonical', ['node' => $node_id], [
                'attributes' => ['target' => '_blank']
              ])->toString() . ']';
            }
          }
          return join(' ', $cells);
        }, $duplicates))]]];
      }, $dupes)
    ]];
  }

  public function environment() {
    ob_start();
    phpinfo((INFO_VARIABLES | INFO_ENVIRONMENT));
    $env = ob_get_clean();
    return [
      '#type' => 'markup',
      '#markup' => $env,
    ];
  }
}
