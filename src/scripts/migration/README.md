WorkBC Content Migration
========================

This document explains the architecture and tools used to import content into the WorkBC Drupal CMS.

# Rationale and architecture
The main idea behind the migration system here is to provide the ability to recreate the site content at any time and repeatedly, provided that the sources of content are identified and available. This allows for more robust development and maintenance of the site, and avoids relying on CMS database dumps which mix content, configuration, and operational data.

The architecture of the migration system is exceedingly simple: it consists of a series of PHP scripts that import various pieces of content. The remainder of this document provides a complete reference about the scripts that are currently used.

# Data sources
The sources providing original WorkBC content are the following:

## Annotated Information Architecture (IA) spreadsheet
The business team maintains an Excel spreadsheet that defines the content tree of the site. This content tree drives the development of the wireframes, which in turn informs the Drupal features and theme implementation. The IA spreadsheet is also mirrored in GatherContent, an online CMS where the business team is entering the text copy that is then migrated to Drupal.

The development team maintains a copy of the IA spreadsheet that is annotated with various implementation-related information for each IA item. These are:
- The Drupal content type for each item, which is used to instantiate the correct type during migration (column **Drupal Content Type**)
- The GatherContent link for each item, which is used to populate the corresponding Drupal object (column **TODO**)
- Whether the item should appear in the main navigation menu (column **Mega Menu?**)
- The specific URL, if any, that the item should have in the header menu (column **New URL**)

## GatherContent (GC)
GatherContent is a CMS that the business team uses to collaborate on writing the text copy (editorial content) that goes into each page of the site. The design team maintains the GatherContent _templates_ which represent the structure (the fields) of the different pages. During migration, an import script maps the GC content fields to Drupal content fields in order to populate the content.

## Labour Market Office Data (SSoT)
The BC Labour Market Office supplies statistical data about the BC job market and the industry. This information is stored in a separate API service called the Single Source of Truth (SSoT) which the migration scripts here access to create some of the non-editorial content (such as the list of Career Profiles).

## Legacy site (LS)
Some content is unavailable anywhere but on the legacy WorkBC site itself. When such content is needed here, we transform it into a CSV file and use a custom script to import it into Drupal.

# Import scripts
The import scripts listed here are all written using PHP and are meant to be run from within the Drupal container (`php`) via the Drupal console tool `drush`. Typically, a script invocation looks like the following:
```bash
drush scr scripts/migration/script.php -- --some-option=some-option-value csv-filename-or-other-operand
```
Each script listed here includes a short documentation header that details its usage, as well as instructions on reverting the import process to start again in case of errors.

| Script | Data source(s) | Drupal output(s) |
| -------| -------------- | -----------------|
| ia.php  | IA (ia.csv)<br>GC | Content type `page`<br>Content type `landing_page`<br>Menu `main` |
| career_profiles.php | SSoT<br>GC | Content type `career_profile` |
| education.php | SSoT | Taxonomy `education` |
| skills.php | SSoT | Taxonomy `skills` |
| taxonomy.php | LS ([definitions.csv](https://www.workbc.ca/Jobs-Careers/Career-Toolkit/Definitions.aspx)) | Taxonomy `definitions` |
| taxonomy.php | LS ([occupational_interests.csv](https://www.workbc.ca/Labour-Market-Industry/Skills-for-the-Future-Workforce.aspx#characteristics)) | Taxonomy `occupational_interests` |
| taxonomy.php | LS ([video_categories.csv](https://www.workbc.ca/videolibrary/)) | Taxonomy `video_categories` |
