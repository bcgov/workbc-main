#! /bin/bash
set -e
composer install
# Add all patches here.
# Notice `|| true` ending which is needed because patch returns a non-zero code when it ignores a hunk.
patch --forward -p1 -d web/modules/contrib/backup_migrate/ < patches/0002-postgresql-support.patch || true
yarn install
drush updb -y
drush cim
drush cr
