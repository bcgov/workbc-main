WorkBC Content Migration
========================

This document explains the architecture and tools used to import content into the WorkBC Drupal CMS.

# Rationale and architecture
The main idea behind the migration system here is to provide the ability to recreate the site content at any time and repeatedly, provided that the sources of content are identified and available. This allows for more robust development and maintenance of the site, and avoids relying on CMS database dumps which mix content, configuration, and operational data.

The architecture of the migration system is exceedingly simple: it consists of a series of PHP scripts that import various pieces of content. In most cases, this content is supplied here in the form of CSV or JSONL files. The remainder of this document provides a complete reference about the scripts that are currently used, and the sources of these data files.

# Running the migration
Assuming an initialized WorkBC Drupal database and updated data files:
```
drush scr scripts/migration/taxonomy -- -v definitions /var/www/html/scripts/migration/data/definitions.csv
drush scr scripts/migration/taxonomy -- -v event_type /var/www/html/scripts/migration/data/event_type.csv
drush scr scripts/migration/taxonomy -- -v occupational_interests /var/www/html/scripts/migration/data/occupational_interests.csv
drush scr scripts/migration/taxonomy -- -v video_categories /var/www/html/scripts/migration/data/video_categories.csv
drush scr scripts/migration/taxonomy -- -v content_groups /var/www/html/scripts/migration/data/content_groups.csv
drush scr scripts/migration/skills
drush scr scripts/migration/education
drush scr scripts/migration/video_library
drush scr scripts/migration/ia
drush scr scripts/migration/workbc
drush scr scripts/migration/career_profiles
```
For more details, refer to the headers of these scripts.

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

The script `gc_jsonl.php` is used to dump items from a given GC project into a local JSONL file.

## Labour Market Office Data (SSoT)
The BC Labour Market Office supplies statistical data about the BC job market and the industry. This information is stored in a separate API service called the Single Source of Truth (SSoT) which the migration scripts here access to create some of the non-editorial content (such as the list of Career Profiles).

## Legacy site (LS)
Some content is unavailable anywhere but on the legacy WorkBC site itself. When such content is needed here, we transform it into a CSV file and use a custom script to import it into Drupal.

## Business requirements document (BRD)
Some content is explicitly listed in the BRD specification of this project or amendments including Jira / Confluence / private communications.

## YouTube (YT)
The YouTube [CareerTrekBC](https://www.youtube.com/user/CareerTrekBC) and [WorkBC](https://www.youtube.com/user/WorkBC) channels are imported into a JSONL file using the commands below (running on the host):
```
yt-dlp --flat-playlist --print url https://www.youtube.com/user/CareerTrekBC | while read u; do yt-dlp --no-download --dump-json "$u"; done > src/scripts/migration/data/video_library.jsonl
yt-dlp --flat-playlist --print url https://www.youtube.com/user/WorkBC | while read u; do yt-dlp --no-download --dump-json "$u"; done >> src/scripts/migration/data/video_library.jsonl
```

# Import scripts
The import scripts listed here are all written using PHP and are meant to be run from within the Drupal container (`php`) via the Drupal console tool `drush`. Typically, a script invocation looks like the following:
```bash
drush scr scripts/migration/script.php -- --some-option=some-option-value csv-filename-or-other-operand
```
Each script listed here includes a short documentation header that details its usage, as well as instructions on reverting the import process to start again in case of errors.

| Script | Data source(s) | Output(s) |
| -------| -------------- | -----------------|
| ia.php  | IA (data/ia.csv) | Content type `page`<br>Menu `main` |
| workbc.php | [GC WorkBC](https://number41media1.gathercontent.com/content/284269/items/) (data/workbc.jsonl) | Content types `blog`, `news`, `success_story` |
| career_profiles.php | SSoT<br>[GC WorkBC Career Profiles](https://number41media1.gathercontent.com/content/290255/items/) (data/career_profiles.jsonl)<br>[GC WorkBC Introductory Blurbs](https://number41media1.gathercontent.com/content/332842/items/) (data/career_profile_introductions.jsonl) | Content types `career_profile`, `career_profile_introductions` |
| education.php | SSoT | Taxonomy `education` |
| skills.php | SSoT | Taxonomy `skills` |
| taxonomy.php | [LS](https://www.workbc.ca/Jobs-Careers/Career-Toolkit/Definitions.aspx) (data/definitions.csv) | Taxonomy `definitions` |
| taxonomy.php | [LS](https://www.workbc.ca/Labour-Market-Industry/Skills-for-the-Future-Workforce.aspx#characteristics) (data/occupational_interests.csv) | Taxonomy `occupational_interests` |
| taxonomy.php | [LS](https://www.workbc.ca/videolibrary/) (data/video_categories.csv) | Taxonomy `video_categories` |
| taxonomy.php | BRD (data/event_type.csv) | Taxonomy `event_type` |
| taxonomy.php | BRD (data/content_groups.csv) | Taxonomy `content_groups` |
| video_library.php | YT [CareerTrekBC](https://www.youtube.com/user/CareerTrekBC) and [WorkBC](https://www.youtube.com/user/WorkBC) (data/video_library.jsonl) | Media type `remote_video` |
| gc-jsonl.php | GC | JSONL file |
