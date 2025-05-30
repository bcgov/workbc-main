#!/bin/bash
#
# Reset the local Drupal site with a dump from the Backup/Restore files.
#
if [ -z "$1" ]; then
  docker-compose exec php drush bamls --files=private_files
  exit
fi

docker-compose exec -T postgres psql -U workbc workbc < src/scripts/workbc-reset.sql
gunzip -k -c "src/private/backup_migrate/$1" | docker-compose exec -T postgres psql -U workbc workbc
docker-compose exec php drush upwd admin 'password'
docker-compose exec php scripts/sync.sh -y
docker-compose exec php drush en -y devel devel_php devel_kint_extras views_ui dblog manage_state webform_ui
