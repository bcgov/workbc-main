workbc-main
===========

This is the WorkBC site on Drupal.

[![Lifecycle:Experimental](https://img.shields.io/badge/Lifecycle-Experimental-339999)](https://github.com/bcgov/workbc-ssot)

# Development
## Initial setup
- Start the environment: `docker-compose up`
- Adjust folder permissions:
  - `docker-compose exec php sudo chown www-data /var/www/html/private`
  - `docker-compose exec php sudo chown www-data /var/www/html/config/sync`
- Enable needed PostgreSQL extension: `docker-compose exec postgres psql -U workbc -d workbc -c "CREATE EXTENSION IF NOT EXISTS pg_trgm;"`
- Import a Drupal data dump: `docker-compose exec -T postgres psql --username workbc workbc < /path/to/workbc-dump.sql` (in Windows PowerShell: `cmd /c "docker-compose exec -T postgres psql --username workbc workbc < /path/to/workbc-dump.sql"`)
- Import a SSoT data dump: `docker-compose exec -T postgres psql --username workbc ssot < /path/to/ssot-dump.sql` (in Windows PowerShell: `cmd /c "docker-compose exec -T postgres psql --username workbc ssot < /path/to/ssot-dump.sql"`)
- Edit your `hosts` file to add the following line:
```
127.0.0.1       workbc.docker.localhost
```
- Run the update script: `docker-compose exec php scripts/update.sh`
- Open http://workbc.docker.localhost:8000/ to view the site and login as `admin` (obtain the password from your admin)
- Open http://localhost:8080/ to view the SSoT API

**For Windows users**, You need a [version of Windows that is able to run Docker using Hyper-V backend](https://docs.docker.com/desktop/windows/install/), e.g. Windows 10 Pro.

## Updating local dev environment after git pull
Run the update script: `docker-compose exec php scripts/update.sh`.

In some situations `drush cim` fails. In this case, the Drupal UI (Configuration -> Development -> Configuration Syncronization) should work. If errors still persist, you may need to manually enable new modules before running the configuration syncronization.

## Installing modules
From within the `php` container:
- Execute the composer requires command for the module. The module project page on Drupal.org provides this command, e.g. `composer require 'drupal/devel:^4.1'`
- Enable the module using `drush en module` or via the Drupal Admin Extend option
- Export updated configuration to the `/var/www/html/config/sync` folder using `drush cex`

## Backup / restore
The Backup and Migrate module does not currently support PostgresQL. Backing up and restoring your local dev site can be accomplished using `drush`:

- To backup: `drush sql:dump --result-file=example.sql`. For more info https://www.drush.org/latest/commands/sql_dump/
- To restore: `drush sql:cli < example.sql`. For more info https://www.drush.org/latest/commands/sql_cli/

## Theming / styling
Please see the `src/web/themes/custom/workbc/README.md` for more details.

## Make and development shortcut commands
This project includes a Makefile, which has been configured with a few command shortcuts to help forgetful developers (like me!) more easily manage all the different CLI tasks they might want to do.

From your host machine, in the `src/` directory, run `make <command>`:

1. `up`
1. `down`
1. `start`
1. `stop`
1. `prune`
1. `ps`
1. `shell`
1. `drush <command>`
1. `logs <service>`
1. `compilescss`
1. `watchscss`

**For Windows users**, follow [this guide on StackExchange to install and configure Make for Windows](https://superuser.com/a/1634350/221936).

## Troubleshooting

- If you run into timeout issues while installing/unzipping PHP, try the following:
  - `docker-compose exec php composer config --global process-timeout 600`
  - `docker-compose exec php composer install --prefer-dist --no-dev`
