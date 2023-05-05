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
    return [[
      '#markup' => 'This is a report of pages containing unmanaged files instead of media library items.<br>
      Click the <b>Edit</b> link to edit the page, then look for the field named in the <b>Field</b> column to find the content to be edited.<br>
      Click the <b>Source</b> button of the editor to locate the unmanaged file(s) in the field content.
      It will typically be an HTML tag that references a file like <code>/sites/default/files/filename.pdf</code>.'
    ],[
      '#theme' => 'table',
      '#header' => ['Page', 'Field', 'Matches', 'Edit'],
      '#rows' => array_map(function ($file) {
        return [
          $file['title'],
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
          ],
          Link::fromTextAndUrl($this->t('Edit'), $file['edit_url'])
        ];
      }, getUnmanagedFiles()),
    ]];
  }

  public function duplicate_files() {
    return [[
      '#markup' => 'This is a report of duplicate files in the Drupal filesystem. For each file, the corresponding media library item is shown, if any.',
    ],[
      '#theme' => 'table',
      '#header' => ['Duplicates'],
      '#rows' => array_map(function ($dupes) {
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
          return join(' ', $cells);
        }, $dupes))]]];
      }, getDuplicateFiles())
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
