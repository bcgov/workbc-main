CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

This module uses Job Board API to display current jobs and their description pages.
It also lets a user create a Job Board account and save Jobs and Job Alerts of his preference.
Basic user access features, such as changing passwords, logout etc., are also available.

This module links to six different pages within Drupal.
1. /account: This page contains the following sub-pages
  1.1 register: Used for account creation at the Job Board site, using its API.
  1.2 login: Standard account login page
  1.3 dashboard: Displays all links a user can utilize
  1.4 saved-jobs: Shows user's saved jobs
  1.5 recommended-jobs: Displays a list of recommended jobs based on the user's inputs at the registration stage.
  1.6 job-alerts/create: Helps users save personalized job alerts. Users can further define if mail alerts should be received and, if yes, at what frequency.
  1.7 job-alert: Displays saved job alerts
  1.8 saved-career-profiles: Displays saved career profiles and links to two Drupal pages (#2 & #3 in this list)
  1.9 saved-industry-profiles: Displays the list of saved industry profiles and points to two Drupal pages (#4 & #5 in this list)
  1. A personal-settings: Helps user set his account preferences
  1. B logout: Standard logout page interface for the user, implemented using JS.
2. /jobs-careers/explore-careers: This path is set in settings.local.php & settings.aws.php $config['jobboard']['search_career_profile_link']. The static page it points to is out of the scope of this module.
3. /labour-market-industry/labour-market-outlook: This path is set in settings.local.php & settings.aws.php. The static page it points to is out of the scope of this module.
4. /labour-market-industry/industry-sector-outlooks: This path is set in settings.local.php & settings.aws.php. The static page it points to is out of the scope of this module.
5. /labour-market-industry/industry-profiles: This path is set in settings.local.php & settings.aws.php. The static page it points to is out of the scope of this module.
6. /search-and-prepare-job/find-jobs: This page contains the following sub-pages
  6.1 job-search: Main job search page with various filters and job save feature for logged-in users.
  6.2 job-details/<JOB-NUMBER>: Provides details of a specific page.

REQUIREMENTS
------------

Access to Job Board API.


INSTALLATION
------------

 * Install as you would typically install a contributed Drupal module.


CONFIGURATION
-------------
API URL and links to all pages have to be set in settings.local.php & settings.aws.php. Since API URLs have to be different in different environments and Drupal page URLs can change in DB for different environments, these settings are set separately in both the file with the following configurations
  1. $config['jobboard']['jobboard_api_url']
  2. $config['jobboard']['find_job_url']
  3. $config['jobboard']['find_job_account_url']
  4. $config['jobboard']['search_career_profile_link']
  5. $config['jobboard']['labour_market_outlook']
  6. $config['jobboard']['explore_industryand_sector_outlooks']
  7. $config['jobboard']['view_industry_profiles']


MAINTAINERS
-----------

Developed By: Anurag Parihar (Anurag@Parihar.ca)
