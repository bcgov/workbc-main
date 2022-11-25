#! /bin/bash
set -e
composer install
if [ "$PROJECT_ENVIRONMENT" == "dev" ]; then yarn install; fi
drush updb -y
drush cim
drush cr
