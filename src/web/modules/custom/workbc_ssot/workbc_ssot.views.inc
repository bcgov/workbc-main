<?php

/**
 * Implements hook_views_data().
 */
function workbc_ssot_views_data() {
  return ['workbc_ssot_log' => [
    'table' => [
      'group' => t('WorkBC SSoT'),
      'provider' => 'workbc_ssot',
      'base' => [
        'field' => 'oid',
        'title' => t('Operations'),
        'help' => t('Stores the SSoT operations log.'),
      ],
      'join' => [
        'users' => [
          'left_field' => 'uid',
          'field' => 'uid',
        ],
        'user_field_data' => [
          'left_field' => 'uid',
          'field' => 'uid',
        ],
        'file_managed' => [
          'left_field' => 'file_id',
          'field' => 'fid',
        ],
      ]
    ],
    'oid' => [
      'title' => t('Oid'),
      'help' => t('Primary identifier for operation log entry.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ],
    'timestamp' => [
      'title' => t('Timestamp'),
      'help' => t('Timestamp of when operation occurred.'),
      'field' => [
        'id' => 'date',
      ],
      'argument' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
      'sort' => [
        'id' => 'date',
      ],
    ],
    'uid' => [
      'title' => t('User'),
      'help' => t('User who performed the operation.'),
      'field' => [
        'id' => 'standard',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'relationship' => [
        'title' => t('User'),
        'label' => t('User'),
        'help' => t('User who performed the operation.'),
        'base' => 'users_field_data',
        'base field' => 'uid',
        'id' => 'standard',
      ],
    ],
    'dataset_name' => [
      'title' => t('Dataset'),
      'help' => t('Dataset (aka SSoT endpoint) that was updated.'),
      'field' => [
        'id' => 'standard',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ],
    'dataset_period' => [
      'title' => t('Period'),
      'help' => t('Date period of the dataset.'),
      'field' => [
        'id' => 'date',
        'click sortable' => true,
      ],
      'argument' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
      'sort' => [
        'id' => 'date',
      ],
    ],
    'file_id' => [
      'title' => t('File'),
      'help' => t('File of the uploaded spreadsheet.'),
      'field' => [
        'id' => 'standard',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'relationship' => [
        'title' => t('File'),
        'label' => t('File'),
        'help' => t('File of the uploaded spreadsheet.'),
        'base' => 'file_managed',
        'base field' => 'fid',
        'id' => 'standard',
      ],
    ],
    'file_timestamp' => [
      'title' => t('File Timestamp'),
      'help' => t('Timestamp (client-side) of the uploaded spreadsheet.'),
      'field' => [
        'id' => 'date',
      ],
      'argument' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
      'sort' => [
        'id' => 'date',
      ],
    ],
    'notes' => [
      'title' => t('Notes'),
      'help' => t('Notes about the operation.'),
      'field' => [
        'id' => 'standard',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ],
    'latest' => [
      'title' => t('Latest'),
      'help' => t('Whether or not this upload is the latest for the dataset/period.'),
      'field' => [
        'id' => 'boolean',
      ],
      'filter' => [
        'id' => 'boolean',
        'label' => t('Latest'),
        'type' => 'yes-no',
        'accept null' => TRUE,
        'use_equal' => TRUE,
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ],
  ]];
}
