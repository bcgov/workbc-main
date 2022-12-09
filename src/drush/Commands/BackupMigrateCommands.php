<?php

namespace Drush\Commands;

use Drush\Drush;
use Drush\Commands\DrushCommands;
use Drush\Boot\DrupalBootLevels;
use Drupal\backup_migrate\Core\Exception\BackupMigrateException;
use Drupal\backup_migrate\Core\Destination\ListableDestinationInterface;
use Symfony\Component\Console\Input\InputOption;

class BackupMigrateCommands extends DrushCommands
{
    /**
     * List sources and destinations.
     *
     * @command backup_migrate:list
     * @aliases bamls
     *
     * @option sources Flag to list sources (default: yes, use --no-sources to hide)
     * @option destinations Flag to list destinations (default: yes, use --no-destinations to hide)
     * @option files Flag to list files for a comma-separated list of destination identifiers (default: none)
     *
     * @param options
     *
     * @return string JSON listing of sources, destinations, files
     *
     */
    public function list(array $options = [
        'sources' => true,
        'destinations' => true,
        'files' => InputOption::VALUE_REQUIRED,
    ]): string {
        Drush::bootstrapManager()->doBootstrap(DrupalBootLevels::FULL);
        $bam = \backup_migrate_get_service_object();
        $output = [];
        if ($options['sources']) {
            $output['sources'] = array_keys($bam->sources()->getAll());
        }
        if ($options['destinations']) {
            $output['destinations'] = array_keys($bam->destinations()->getAll());
        }
        if ($options['files']) {
            foreach(array_map('trim', explode(',', $options['files'])) as $destination_id) {
                $destination = $bam->destinations()->get($destination_id);
                if (!$destination) {
                    $this->logger()->warning(dt('The destination !id does not exist.', ['!id' => $destination_id]));
                    continue;
                }
                if (!$destination instanceof ListableDestinationInterface) {
                    $this->logger()->warning(dt('The destination !id is not listable.', ['!id' => $destination_id]));
                    continue;
                }
                try {
                    $output['files'][$destination_id] = array_keys($destination->listFiles());
                }
                catch (\Exception $e) {
                    $this->logger()->error(dt('The destination !id caused an error: !error', [
                        '!id' => $destination_id,
                        '!error' => $e->getMessage()
                    ]));
                }
            }
        }
        return json_encode($output, JSON_PRETTY_PRINT);
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
     * @return string Backup completion status
     *
     * @throws \Drupal\backup_migrate\Core\Exception\BackupMigrateException
     *
     */
    public function backup(
        $source_id,
        $destination_id
    ): string
    {
        Drush::bootstrapManager()->doBootstrap(DrupalBootLevels::FULL);
        $bam = \backup_migrate_get_service_object();
        $bam->backup($source_id, $destination_id);
        return json_encode([
            'status' => 'success',
            'message' => dt('Backup complete.')
        ], JSON_PRETTY_PRINT);
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
     * @return string Restore completion status
     *
     * @throws \Drupal\backup_migrate\Core\Exception\BackupMigrateException
     *
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
        return json_encode([
            'status' => 'success',
            'message' => dt('Restore complete.')
        ], JSON_PRETTY_PRINT);
    }
}
