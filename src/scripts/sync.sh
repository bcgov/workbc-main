#! /bin/bash
set -e
composer install
if [ "$PROJECT_ENVIRONMENT" == "dev" ]; then yarn install; fi
drush updb -y --no-post-updates
drush cim
drush updb -y
drush cr
