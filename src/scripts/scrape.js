#!/usr/bin/env node
/**
 * Example usage: To convert all 512 career profiles to PDF.
 *
 * curl http://workbc.docker.localhost:8000/sitemap.xml | xmlstarlet sel -N x="http://www.sitemaps.org/schemas/sitemap/0.9" -T -t -v '//x:url/x:loc[contains(text(), "/career-profiles/")]' | while read f; do scrape.js "$f?template=embed"; done
 */
const { argv } = require('node:process');
const puppeteer = require('puppeteer');
const { URL } = require('node:url');

(async () => {
  // Create a browser instance
  const browser = await puppeteer.launch();

  // Create a new page
  const page = await browser.newPage();

  // Website URL to export as pdf
  const website_url = argv[2];
  const parsed_url = new URL(website_url);

  // Open URL in current page
  await page.goto(website_url, { waitUntil: 'networkidle0' });
  await page.emulateMediaType('print');

  // Downlaod the PDF
  const pdf = await page.pdf({
    path: parsed_url.pathname.split('/').at(-1) + '.pdf',
    margin: { top: '100px', right: '50px', bottom: '100px', left: '50px' },
    printBackground: true,
    format: 'A4',
  });

  // Close the browser instance
  await browser.close();
})();
