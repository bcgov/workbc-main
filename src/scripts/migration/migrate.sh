#! /bin/bash
set -e
drush sapi-disa
drush scr scripts/migration/taxonomy -- -v definitions /var/www/html/scripts/migration/data/definitions.csv
drush scr scripts/migration/taxonomy -- -v event_type /var/www/html/scripts/migration/data/event_type.csv
drush scr scripts/migration/taxonomy -- -v occupational_interests /var/www/html/scripts/migration/data/occupational_interests.csv
drush scr scripts/migration/taxonomy -- -v video_categories /var/www/html/scripts/migration/data/video_categories.csv
drush scr scripts/migration/taxonomy -- -v content_groups /var/www/html/scripts/migration/data/content_groups.csv
drush scr scripts/migration/skills
drush scr scripts/migration/education
drush scr scripts/migration/video_library
drush scr scripts/migration/ia
drush scr scripts/migration/career_profiles
drush scr scripts/migration/service_centres
drush scr scripts/migration/publications
drush scr scripts/migration/workbc
drush sapi-ena && drush sapi-r && drush sapi-i
drush cron
