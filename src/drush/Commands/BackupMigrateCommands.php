<?php

namespace Drush\Commands;

use Drush\Drush;
use Drush\Commands\DrushCommands;
use Drush\Boot\DrupalBootLevels;
use Drupal\backup_migrate\Core\Exception\BackupMigrateException;
use Drupal\backup_migrate\Core\Destination\ListableDestinationInterface;

class BackupMigrateCommands extends DrushCommands
{
    /**
     * List sources and destinations.
     *
     * @command backup_migrate:list
     * @aliases baml
     *
     */
    public function list() {
        Drush::bootstrapManager()->doBootstrap(DrupalBootLevels::FULL);
        $bam = \backup_migrate_get_service_object();
        print_r(array_keys($bam->sources()->getAll()));
        print_r(array_keys($bam->destinations()->getAll()));
    }

    /**
     * List files in a destinations.
     *
     * @command backup_migrate:list_files
     * @aliases bamls
     *
     * @param destination_id Identifier of the Backup Destination.
     *
     */
    public function list_files(
        $destination_id
    ) {
        Drush::bootstrapManager()->doBootstrap(DrupalBootLevels::FULL);
        $bam = \backup_migrate_get_service_object();
        $destination = $bam->destinations()->get($destination_id);
        if (!$destination) {
            throw new BackupMigrateException('The destination !id does not exist.', ['!id' => $destination_id]);
        }
        if (!$destination instanceof ListableDestinationInterface) {
            throw new BackupMigrateException('The destination !id is not listable.', ['!id' => $destination_id]);
        }
        print_r(array_keys($destination->listFiles()));
    }

    /**
     * Backup.
     *
     * @command backup_migrate:backup
     * @aliases bamb
     *
     * @param source_id Identifier of the Backup Source.
     * @param destination_id Identifier of the Backup Destination.
     *
     * @return string
     *
     * @throws \Drupal\backup_migrate\Core\Exception\BackupMigrateException

     */
    public function backup(
        $source_id,
        $destination_id
    ): string
    {
        Drush::bootstrapManager()->doBootstrap(DrupalBootLevels::FULL);
        $bam = \backup_migrate_get_service_object();
        $bam->backup($source_id, $destination_id);
        return t('Backup complete.');
    }

    /**
     * Restore.
     *
     * @command backup_migrate:restore
     * @aliases bamr
     *
     * @param source_id Identifier of the Backup Source.
     * @param destination_id Identifier of the Backup Destination.
     * @param file_id optional Identifier of the Destination file.
     *
     * @return string
     *
     * @throws \Drupal\backup_migrate\Core\Exception\BackupMigrateException

     */
    public function restore(
        $source_id,
        $destination_id,
        $file_id = null,
    ): string
    {
        Drush::bootstrapManager()->doBootstrap(DrupalBootLevels::FULL);
        $bam = \backup_migrate_get_service_object();
        $bam->restore($source_id, $destination_id, $file_id);
        return t('Restore complete.');
    }
}
