#! /bin/bash
set -e
composer install
if [ "$PROJECT_ENVIRONMENT" == "dev" ]; then yarn install; fi
drush cr
drush updb -y --no-post-updates
drush cim $1
drush updb -y
drush cr
