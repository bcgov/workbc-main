#! /bin/bash
set -e
echo "Downloading WorkBC Introductory Blurbs..."
drush scr scripts/migration/gc-jsonl -- -i 14989150 332842 > scripts/migration/data/labour_market_introductions.jsonl
drush scr scripts/migration/gc-jsonl -- -i 14878299 332842 > scripts/migration/data/career_profile_introductions.jsonl
drush scr scripts/migration/gc-jsonl -- -i 15104789 332842 > scripts/migration/data/industry_profile_introductions.jsonl
drush scr scripts/migration/gc-jsonl -- -i 15227303 332842 > scripts/migration/data/regional_profile_introductions.jsonl
echo "Downloading WorkBC Main Content..."
drush scr scripts/migration/gc-jsonl -- -s "Content Revisions" -s "Manager Review" -s "Director Review" -s "ED Review" -s "GCPE Review" -s "Published" 284269 > scripts/migration/data/workbc.jsonl
echo "Downloading WorkBC Career Profiles..."
drush scr scripts/migration/gc-jsonl -- -s "Content Revisions" -s "Manager Review" -s "Director Review" -s "ED Review" -s "GCPE Review" -s "Published" 290255 > scripts/migration/data/career_profiles.jsonl
