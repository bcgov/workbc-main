workbc-main
===========

This is the [WorkBC.ca](https://workbc.ca) site on Drupal.

[![Lifecycle:Maturing](https://img.shields.io/badge/Lifecycle-Maturing-007EC6)](https://github.com/bcgov/workbc-main)

# Initial setup
- Copy `.env.example` to `.env`
- Start the environment: `docker-compose up`
- Adjust folder permissions:
  - `mkdir src/private && docker-compose exec php sudo chown www-data /var/www/html/private`
  - `docker-compose exec php sudo chown www-data /var/www/html/config/sync`
- Import the init data dumps:
  - `gunzip -k -c src/scripts/workbc-init.sql.gz | docker-compose exec -T postgres psql -U workbc workbc`
  - Restore the SSOT data dump as per the [`workbc-ssot` README](https://github.com/bcgov/workbc-ssot?tab=readme-ov-file#development). Assuming your SSOT repo lives at `../workbc-ssot`:
```bash
docker-compose exec -T postgres psql --username workbc ssot < ../workbc-ssot/ssot-reset.sql \
&& gunzip -k -c ../workbc-ssot/ssot-full.sql.gz | docker-compose exec -T postgres psql --username workbc ssot \
&& docker-compose kill -s SIGUSR1 ssot
```
- Create the Solr index:
  - `docker-compose exec -u 0 solr sh -c "chown -R solr:solr /opt/solr/server/solr/workbc_dev"`
  - `docker-compose exec solr sh -c "curl -sIN 'http://localhost:8983/solr/admin/cores?action=CREATE&name=workbc_dev&configSet=workbc&instanceDir=workbc_dev'"`
  - `docker-compose exec php bash -c "drush sapi-r && drush sapi-i"`
- Edit your `hosts` file to add the following line:
```
127.0.0.1       workbc.docker.localhost
```
- Run the sync script: `docker-compose exec php scripts/sync.sh`
- Open http://workbc.docker.localhost:8000/ to view the site and login as `admin` (obtain the password from your admin or change the password using `drush upwd admin 'password'`)
- Open http://localhost:8080/ to view the SSoT API

**For Windows users**, you need a [version of Windows that is able to run Docker using Hyper-V backend](https://docs.docker.com/desktop/windows/install/), e.g. Windows 10 Pro. When running a command above in PowerShell, you may need to wrap it using `cmd /c "command"`.

# Updating local dev environment from a deployment stage
You may want to get the latest data from a deployment stage (DEV, TEST or PROD). In that case, follow these steps:
- Take a full database dump: `docker-compose exec -T postgres pg_dump --clean --username workbc workbc | gzip > workbc-backup.sql.gz`
- Reset your database `docker-compose exec -T postgres psql -U workbc workbc < src/scripts/workbc-reset.sql`
- Import the init data dump `gunzip -k -c src/scripts/workbc-init.sql.gz | docker-compose exec -T postgres psql -U workbc workbc`
- Download a fresh dump from your desired stage via Backup/Migrate module at `https://<stage>.workbc.ca/admin/config/development/backup_migrate` and select Backup Source **Default Drupal Database**
- Restore the fresh dump on your local at http://workbc.docker.localhost:8000/admin/config/development/backup_migrate/restore
- Repeat the above two steps for Backup Source **Public Files Directory** in case you also need the latest files
- Run the sync script: `docker-compose exec php scripts/sync.sh`

# Installing modules
- Access the container: `docker-compose exec php bash`
- Execute the composer requires command for the module. The module project page on Drupal.org provides this command, e.g. `composer require 'drupal/devel:^4.1'`
- Enable the module using `drush en module` or via the [Drupal Admin UI](http://workbc.docker.localhost:8000/admin/modules).
- Export updated configuration to the `/var/www/html/config/sync` folder using `drush cex`

# Backup / restore
This repo includes a patched version of Backup and Migrate that supports PostgreSQL using the native `pg_dump` and `psql` tools. You can backup and restore Drupal, SSoT databases as well as Drupal public files using the module, using either the [Drupal Admin UI](http://workbc.docker.localhost:8000/admin/config/development/backup_migrate) or using `drush`:

- `drush backup_migrate:list [--files:destination_id]` to list available backup sources, destinations and optionally backup files for a given destination.
- `drush backup_migrate:backup source_id destination_id` to backup a given source (e.g. `default_db`) to a given destination (e.g. `private_files`).
- `drush backup_migrate:restore source_id destination_id file_id` to restore a given file (e.g. `backup-2023-01-03T12-02-04.sql.gz`) from a given destination (e.g. `private_files`) to a given source (e.g. `default_db`).

# Theming / styling
The [WorkBC theme](src/web/themes/custom/workbc) is a [Bootstrap 5](https://www.drupal.org/project/bootstrap5) subtheme.

## Fonts
B.C. Government digital services are expected to make use of BCSans.
See https://developer.gov.bc.ca/Typography for more details.

## Development
The `php` Docker container has `yarn`, `grunt`, and `grunt-dart-sass` set up for compilation.
You will need to `yarn install` from `src/` to install all of the dependencies first.

Once dependencies are in place:
`yarn run grunt dart-sass` will compile everything starting with the style.scss, and
`yarn run grunt watch` will start a watch on all .scss files, and compile on detecting changes.

# Testing / debugging

## Load-testing
Refer to the [`src/scripts/test`](src/scripts/test/README.md) folder for instructions on load-testing the site.

## Xdebug
The instructions here concern setting up Xdebug with Visual Studio Code. The current `docker-compose.yml` file enables Xdebug and attempts to connect the PHP container to VS Code's debugger listening on port 9003.

- Install extension [PHP Debug](https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug)
- Create or edit the file `.vscode/launch.json`:
```json
{
  "version": "0.2.0",
  "configurations": [
    {
      "name": "Listen for Xdebug",
      "type": "php",
      "request": "launch",
      "port": 9003,
      "pathMappings": {
        "/var/www/html/": "${workspaceFolder}/src"
      }
    }
  ]
}
```
- Click Run > Start Debugging
- Place a breakpoint somewhere in the Drupal code

## Testing the Drupal cache
By default, caching is disabled in the local development environment. To turn it on, set
```php
const LOCAL_CACHE_ACTIVE = TRUE;
```
in the file `settings.local.php`.

# Content migration / seeding
- Content migrations are located in the [`workbc_custom.post_update.php`](src/web/modules/custom/workbc_custom/workbc_custom.post_update.php) file.
- For development purposes, the script [`reset_hook_post_update.php`](src/scripts/reset_hook_post_update.php) can be used to selectively reset migration runs in order to re-run them. Usage: `drush scr scripts/reset_hook_post_update.php -- workbc_custom`.
- Refer to the [`src/scripts/migration`](src/scripts/migration/README.md) folder for instructions on seeding content from legacy sources into this site.

# Troubleshooting
- If you notice that Search API is no longer finding results even though you rebuilt the Solr indexes, try the following:
  - `docker-compose exec php bash -c "drush scr scripts/reset_solr.php"`
  - `docker-compose exec php bash -c "drush sapi-r && drush sapi-i"`

- If you run into timeout issues while installing/unzipping PHP, try the following:
  - `docker-compose exec php composer config --global process-timeout 600`
  - `docker-compose exec php composer install --prefer-dist --no-dev`
  - There is also one instance where making sure you were logged into docker helped (`docker login` or logging in via the UI)

- In some situations `drush cim` fails. In this case, the [Drupal Admin UI](http://workbc.docker.localhost:8000/admin/config/development/configuration) should work. If errors still persist, you may need to manually enable new modules before running the configuration synchronization with `drush en module`.
