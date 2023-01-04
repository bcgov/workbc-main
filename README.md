workbc-main
===========

This is the WorkBC site on Drupal.

[![Lifecycle:Experimental](https://img.shields.io/badge/Lifecycle-Experimental-339999)](https://github.com/bcgov/workbc-main)

# Development
## Initial setup
- Copy `.env.example` to `.env`
- Start the environment: `docker-compose up`
- Adjust folder permissions:
  - `mkdir src/private && docker-compose exec php sudo chown www-data /var/www/html/private`
  - `docker-compose exec php sudo chown www-data /var/www/html/config/sync`
- Import the init data dumps:
  - `gunzip -k -c src/scripts/workbc-init.sql.gz | docker-compose exec -T postgres psql -U workbc workbc`
  - `gunzip -k -c src/scripts/ssot-full.sql.gz | docker-compose exec -T postgres psql -U workbc ssot && docker-compose kill -s SIGUSR1 ssot`
- Create the Solr index:
  - `docker-compose exec -u 0 solr sh -c "chown -R solr:solr /opt/solr/server/solr/workbc_dev"`
  - `docker-compose exec solr sh -c "curl -sIN 'http://localhost:8983/solr/admin/cores?action=CREATE&name=workbc_dev&configSet=workbc&instanceDir=workbc_dev'"`
  - `docker-compose exec php bash -c "drush sapi-r && drush sapi-i"`
- Edit your `hosts` file to add the following line:
```
127.0.0.1       workbc.docker.localhost
```
- Run the sync script: `docker-compose exec php scripts/sync.sh`
- Open http://workbc.docker.localhost:8000/ to view the site and login as `admin` (obtain the password from your admin)
- Open http://localhost:8080/ to view the SSoT API

**For Windows users**, you need a [version of Windows that is able to run Docker using Hyper-V backend](https://docs.docker.com/desktop/windows/install/), e.g. Windows 10 Pro. When running a command above in PowerShell, you may need to wrap it using `cmd /c "command"`.

## Updating local dev environment after git pull
`make sync` from the `src/` folder should perform any post-pull actions needed
or run the sync script directly: `docker-compose exec php scripts/sync.sh`

In some situations `drush cim` fails. In this case, the [Drupal Admin UI](http://workbc.docker.localhost:8000/admin/config/development/configuration) should work.
If errors still persist, you may need to manually enable new modules before running the configuration synchronization with `drush en module`.

## Updating local dev environment from a deployment stage
You may want to get the latest data from a deployment stage (DEV, TEST or PROD). In that case, follow these steps:
- Import the init data dump `gunzip -k -c src/scripts/workbc-init.sql.gz | docker-compose exec -T postgres psql -U workbc workbc`
- Download a fresh dump from your desired stage via Backup/Migrate module at `https://<stage>.workbc.ca/admin/config/development/backup_migrate` and select Backup Source **Default Drupal Database**
- Restore the fresh dump on your local at http://workbc.docker.localhost:8000/admin/config/development/backup_migrate/restore
- Repeat the above two steps for Backup Source **Public Files Directory** in case you also need the latest files

## Installing modules
From within the `php` container:
- Execute the composer requires command for the module. The module project page on Drupal.org provides this command, e.g. `composer require 'drupal/devel:^4.1'`
- Enable the module using `drush en module` or via the [Drupal Admin UI](http://workbc.docker.localhost:8000/admin/modules).
- Export updated configuration to the `/var/www/html/config/sync` folder using `drush cex`

## Backup / restore
This repo includes a patched version of Backup and Migrate that supports PostgreSQL using the native `pg_dump` and `psql` tools. You can backup and restore Drupal, SSoT databases as well as Drupal public files using the module, using either the [Drupal Admin UI](http://workbc.docker.localhost:8000/admin/config/development/backup_migrate) or using `drush`:

- `drush backup_migrate:list [--files:destination_id]` to list available backup sources, destinations and optionally backup files for a given destination.
- `drush backup_migrate:backup source_id destination_id` to backup a given source (e.g. `default_db`) to a given destination (e.g. `private_files`).
- `drush backup_migrate:restore source_id destination_id file_id` to restore a given file (e.g. `backup-2023-01-03T12-02-04.sql.gz`) from a given destination (e.g. `private_files`) to a given source (e.g. `default_db`).

## Theming / styling
Please see the `src/web/themes/custom/workbc/README.md` for more details.

## Make and development shortcut commands
This project includes a Makefile, which has been configured with a few command shortcuts to help forgetful developers (like me!) more easily manage all the different CLI tasks they might want to do.

From your host machine, in the `src/` directory, run `make <command>`:

### Docker related:
1. `up`
1. `down`
1. `start`
1. `stop`
1. `prune`
1. `ps`

### Drupal related:
1. `shell`
1. `sync`
1. `drush <command>`
1. `navrebuild`

### SCSS related:
1. `compilescss`
1. `watchscss`

**For Windows users**, follow [this guide on StackExchange to install and configure Make for Windows](https://superuser.com/a/1634350/221936).

## Troubleshooting

- If you notice that Search API is no longer finding results even though you rebuilt the Solr indexes, try the following:
  - `docker-compose exec solr sh -c "curl 'http://localhost:8983/solr/workbc_dev/update?commit=true' -H 'Content-Type: text/xml' --data-binary '<delete><query>*:*</query></delete>'"`
  - `docker-compose exec php bash -c "drush sapi-r && drush sapi-i"`

- If you run into timeout issues while installing/unzipping PHP, try the following:
  - `docker-compose exec php composer config --global process-timeout 600`
  - `docker-compose exec php composer install --prefer-dist --no-dev`
  - There is also one instance where making sure you were logged into docker helped (`docker login` or logging in via the UI)
