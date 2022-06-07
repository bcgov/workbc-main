workbc-main
===========

This is the WorkBC site on Drupal.

[![Lifecycle:Experimental](https://img.shields.io/badge/Lifecycle-Experimental-339999)](https://github.com/bcgov/workbc-ssot)

# Development
- Start the environment: `docker-compose up`
- In a separate terminal, install the latest dependencies: `docker-compose exec php composer install`
- Import a Drupal data dump: `docker-compose exec -T postgres psql --username workbc workbc < /path/to/workbc-dump.sql` (in Windows PowerShell: `cmd /c "docker-compose exec -T postgres psql --username workbc workbc < /path/to/workbc-dump.sql"`)
- Import a SSoT data dump: `docker-compose exec -T postgres psql --username workbc ssot < /path/to/ssot-dump.sql` (in Windows PowerShell: `cmd /c "docker-compose exec -T postgres psql --username workbc ssot < /path/to/ssot-dump.sql"`)
- Edit your `hosts` file to add the following line:
```
127.0.0.1       workbc.docker.localhost
```
- Open http://workbc.docker.localhost:8000/ to view the site and login as `admin` (obtain the password from your admin)
- Open http://localhost:8080/ to view the SSoT API

## Windows
If you are experiencing errors running the prototype on a Windows computer (ie. white screen of death) this is likely due to issues with WSL 2. Try unchecking "Use the WSL 2 based engine" in the Docker Desktop options.

If that doesn't work you can use [WAMP](https://www.wampserver.com/en/) as your web server and PHP service and follow the steps below:

- Edit your `hosts` file to add the following lines:
```
127.0.0.1       workbc.localhost
127.0.0.1       ssot
127.0.0.1       postgres
```
- Edit your `httpd-vhosts.conf` file and add the following lines:
```
<VirtualHost *:80>
    ServerAdmin webmaster@workbc.localhost
    DocumentRoot "C:/Path/To/htdocs/workbc-main/src/web"
    ServerName workbc.localhost
    ErrorLog "logs/workbc-error.log"
    CustomLog "logs/workbc-access.log" common
  	<Directory "C:/Path/To/htdocs/workbc-main/src/web">
	    Options -Indexes +FollowSymLinks +Includes
    	AllowOverride All
    	Require local
  	</Directory>
</VirtualHost>
```
- `docker-compose -f docker-compose.yml -f docker-compose.wamp.yml up`
