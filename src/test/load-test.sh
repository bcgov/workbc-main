#!/bin/bash
sitemap=$BASE_URL/sitemap.xml
xmllint  --xpath "//*[local-name()='loc']/text()" $sitemap > urls.txt
echo "Extracted $(wc -l urls.txt | awk '{print $1}') URLs from $sitemap. Running siege..."
siege --rc=./siege.conf
