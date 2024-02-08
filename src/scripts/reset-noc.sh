#!/bin/bash
#
# Reset the local Drupal site with a dump from the Backup/Restore files.
#
if [ -z "$1" ]; then
  drush bamls --files=private_files
  exit
fi
drush bamr default_db private_files $1
drush cr
./scripts/sync.sh -y
