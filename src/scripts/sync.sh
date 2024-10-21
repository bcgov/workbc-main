#! /bin/bash
set -e
composer install
if [ "$PROJECT_ENVIRONMENT" == "dev" ]; then yarn install; fi
drush cr
drush updb -y
drush cim $1
drush deploy:hook -y
drush cr
