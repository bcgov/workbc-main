include .env

default: up

PROJECT_NAME ?= "WorkBC Refresh"

SOLUTION_ROOT ?= $(abspath $(lastword $(MAKEFILE_LIST))/../../)
COMPOSER_ROOT ?= $(SOLUTION_ROOT)
DRUPAL_CONTAINER ?= php


.PHONY: debug
debug:
	@echo SOLUTION_ROOT: $(SOLUTION_ROOT)
	@echo COMPOSER_ROOT: $(COMPOSER_ROOT)
	@echo DRUPAL_CONTAINER: $(DRUPAL_CONTAINER)

## up	:	Start up containers.
.PHONY: up
up:
	@echo "Starting up containers for $(PROJECT_NAME)..."
	docker-compose --project-directory "$(SOLUTION_ROOT)" pull
	docker-compose --project-directory "$(SOLUTION_ROOT)" up -d --remove-orphans

## down	:	Stop containers.
.PHONY: down
down: stop

## start	:	Start containers without updating.
.PHONY: start
start:
	@echo "Starting containers for $(PROJECT_NAME) from where you left off..."
	@docker-compose --project-directory "$(SOLUTION_ROOT)" start

## stop	:	Stop containers.
.PHONY: stop
stop:
	@echo "Stopping containers for $(PROJECT_NAME)..."
	@docker-compose --project-directory "$(SOLUTION_ROOT)" stop

## prune	:	Remove containers and their volumes.
##		You can optionally pass an argument with the service name to prune single container
##		prune mariadb	: Prune `mariadb` container and remove its volumes.
##		prune mariadb solr	: Prune `mariadb` and `solr` containers and remove their volumes.
.PHONY: prune
prune:
	@echo "Removing containers for $(PROJECT_NAME)..."
	@docker-compose --project-directory "$(SOLUTION_ROOT)" down -v $(filter-out $@,$(MAKECMDGOALS))

## ps	:	List running containers.
.PHONY: ps
ps:
	@docker ps --filter name='$(PROJECT_NAME)*'

## shell	:	Access `php` container via shell.
.PHONY: shell
shell:
	@docker-compose --project-directory "$(SOLUTION_ROOT)" exec php bash

## install	:	Install a variety of dependencies
.PHONY: install
install:
	@docker-compose --project-directory "$(SOLUTION_ROOT)" exec php yarn install

## drush	:	Executes `drush` command in a specified `DRUPAL_ROOT` directory (default is `/var/www/html/web`).
##		To use "--flag" arguments include them in quotation marks.
##		For example: make drush "watchdog:show --type=cron"
.PHONY: drush
drush:
	@docker-compose --project-directory "$(SOLUTION_ROOT)" exec php drush $(filter-out $@,$(MAKECMDGOALS))

## logs	:	View containers logs.
##		You can optinally pass an argument with the service name to limit logs
##		logs php	: View `php` container logs.
##		logs nginx php	: View `nginx` and `php` containers logs.
.PHONY: logs
logs:
	@docker-compose --project-directory "$(SOLUTION_ROOT)" logs -f $(filter-out $@,$(MAKECMDGOALS))

## compilescss	:	Compile the scss per Gruntfile configuration
.PHONY: compilescss
compilescss:
	@docker-compose --project-directory "$(SOLUTION_ROOT)" exec php yarn run grunt dart-sass

## compilescss	:	Watch the scss for changes and react per Gruntfile configuration
.PHONY: watchscss
watchscss:
	@docker-compose --project-directory "$(SOLUTION_ROOT)" exec php yarn run grunt watch


# https://stackoverflow.com/a/6273809/1826109
%:
	@:
