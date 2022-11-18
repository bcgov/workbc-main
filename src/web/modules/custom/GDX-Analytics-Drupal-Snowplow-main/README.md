[![img](https://img.shields.io/badge/Lifecycle-Experimental-339999)]
## GDX-Analytics-Drupal-Snowplow

  The GDX Analytics Drupal Snowplow module installs and runs Snowplow 
  Javascript web trackers, and provides an interface to configure them.
  
## Requirements  

  This module requires Drupal 8.
  
## Project Status

This project is currently under development and actively supported by the GDX Analytics Team.
  
## Relevant Repositories
[GDX-Analytics/](https://github.com/bcgov/GDX-Analytics/)

This is the central repository for work by the GDX Analytics Team.

## Installation
 
  Install as you would normally install a contributed Drupal module. Visit:
  https://www.drupal.org/documentation/install/modules-themes/modules-7
  for further information.

  In your drupal installation, change directories to your sites/modules/custom folder.
  Clone the project from github: https://github.com/bcgov/GDX-Analytics-Drupal-Snowplow.
  Install the module in admin » extend.
  Navigate to admin » config » gdx_analytics_drupal_snowplow » config_settings and enter
  the collector environment, snowplow version number, and snowplow tracking script uri.

## Configuration

  Configure settings in Administration » Configuration » System 
    » GDX Analytics Drupal Snowplow » Config_settings.
    
  Use this Configuration Page to set the collector version, script version, and specify
  the uri of the tracking script.

## Getting Help or Reporting an Issue
 
For any questions regarding this project, or for inquiries about starting a new analytics account, please contact the GDX Analytics Team.

## Contributors

The GDX Analytics Team are the main contributors to this project and maintain the code.

## How to Contribute

If you would like to contribute, please see our [CONTRIBUTING](CONTRIBUTING.md) guideleines.

Please note that this project is released with a [Contributor Code of Conduct](CODE_OF_CONDUCT.md). By participating in this project you agree to abide by its terms.

## License

Copyright 2018 Province of British Columbia

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

   http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and limitations under the License.
