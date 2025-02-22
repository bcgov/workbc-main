<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarketOutlook;

use Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarketOutlook\RegionTopFiveIndustriesBaseTable;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "lmo_report_2024_job_openings_north_coast_nechako_table",
 *   label = @Translation("Top Five Industries by Total Job Openings, North Coast and Nechako (2024-2034)"),
 *   description = @Translation("An extra field to display job openings regional table."),
 *   bundles = {
 *     "paragraph.lmo_charts_tables",
 *   }
 * )
 */
class RegionTopFiveIndustriesNorthCoastNechakoTable extends RegionTopFiveIndustriesBaseTable {}
