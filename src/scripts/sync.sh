#! /bin/bash
set -e
composer install
yarn install
drush updb -y
drush cim
drush cr
