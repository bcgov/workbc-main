<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarketOutlook;

use Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarketOutlook\RegionTopFiveIndustriesBaseTable;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "lmo_report_2024_job_openings_vancouver_island_coast_table",
 *   label = @Translation("Table 5.1-1. Top five industries by total job openings, 2024-2034 - Vancouver Island/Coast"),
 *   description = @Translation("An extra field to display job openings regional table."),
 *   bundles = {
 *     "paragraph.lmo_charts_tables",
 *   }
 * )
 */
class RegionTopFiveIndustriesVancouverIslandCoastTable extends RegionTopFiveIndustriesBaseTable {}
