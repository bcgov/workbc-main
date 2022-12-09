#!/bin/bash
cp cases.txt urls.txt
xmllint --xpath "//*[local-name()='loc']/text()" $BASE_URL/sitemap.xml >> urls.txt
echo "Extracted $(wc -l urls.txt | awk '{print $1}') URLs from $BASE_URL/sitemap.xml. Running siege..."
siege --rc=./siege.conf
