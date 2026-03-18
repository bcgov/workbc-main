# Amazon Simple Email Service Drupal Mailer

This module provides a **very simple** AWS SES mailer implementation for Drupal.

## Installation

This module requires you to have the AWS SDK loaded in your classpath.

The recommended approach is to have a composer file in your project root,
and run:

```
composer require aws/aws-sdk-php:~3.0
```

## Configuration

You need to specify the SES Mailer as the default system mailer. This can be
done in your settings.php file by adding
the following configuration:

```
$config['system.mail']['interface']['default'] = 'ses_mail';
```
Alternatively, you can use drush to set it, then export as part of your site
configuration.

```
drush config-set system.mail interface.default ses_mail
```

## AWS Authentication

This module does not require setting AWS access keys, and assumes you are
following best practices and following the SDK Guide on [Credentials]
(http://docs.aws.amazon.com/aws-sdk-php/v3/guide/guide/credentials.html)

This means, either using:

- IAM Roles
- Exporting credentials using environment variables
- Using a profile in a ~/.aws/credentials file

You will need to add the `ses:SendEmail` and `ses:ListIdentities` actions to
your IAM policy.

```
{
    "Effect": "Allow",
    "Action": [
        "ses:ListIdentities",
        "ses:SendEmail"
    ],
    "Resource": [
        "*"
    ]
}
```
