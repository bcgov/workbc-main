(function ($, Drupal, once, drupalSettings) {
  "use strict";
  Drupal.behaviors.iconAppend = {
    attach: function (context, settings) {
      function fetchSvgIcon(url) {
        return fetch(url)
          .then(function(response) {
            if (!response.ok) throw new Error('SVG not found');
            return response.text();
          });
      }
      once('iconAppendSingle', '.plan-careercareer-trek-videos .node-page-content .js-form-item-search-api-fulltext', context).forEach(function (element) {
        var iconUrl = "/" . drupalSettings?.workbc_career_trek?.icon_search ?? '/themes/custom/workbc/assets/icons/icon-search.svg';
        fetchSvgIcon(iconUrl).then(function(svg) {
          $(element).append(`<span class="icon-single">${svg}</span>`);
        });
      });

      $(context).on('click', '.plan-careercareer-trek-videos .node-page-content .js-form-item-search-api-fulltext .icon-single', function () {
        var $form = $(this).closest('form');
        $form.find('input[type="submit"]:not([name="reset"])').trigger('click');
      });

      once('iconAppendDateSingle', '.career-profile-content .block-workbc-jobboard .job-footer .job-post-date', context).forEach(function (element) {
        var iconUrl = "/" . drupalSettings?.workbc_career_trek?.icon_calendar ?? '/themes/custom/workbc/assets/icons/icon-calendar.svg';
        fetchSvgIcon(iconUrl).then(function(svg) {
          $(element).prepend(`<span class="icon-single-date">${svg}</span>`);
        });
      });
      once('iconAppendOccupationalCategories', '.view-career-trek-node .career-profile-content .icon-occupational-categories', context).forEach(function (element) {
        var iconUrl = "/" . drupalSettings?.workbc_career_trek?.icon_occupational_categories ?? '/themes/custom/workbc/assets/icons/occupational-categories.svg';
        fetchSvgIcon(iconUrl).then(function(svg) {
          $(element).prepend(`${svg}`);
        });
      });
      once('iconAppendProfileLocation', '.view-career-trek-node .career-profile-content .icon-profile-location', context).forEach(function (element) {
        var iconUrl = "/" . drupalSettings?.workbc_career_trek?.icon_profile_location ?? '/themes/custom/workbc/assets/icons/profile-location.svg';
        fetchSvgIcon(iconUrl).then(function(svg) {
          $(element).prepend(`${svg}`);
        });
      });
      once('iconAppendNoc', '.view-career-trek-node .career-profile-content .icon-noc', context).forEach(function (element) {
        var iconUrl = "/" . drupalSettings?.workbc_career_trek?.icon_noc ?? '/themes/custom/workbc/assets/icons/icon-noc.svg';
        fetchSvgIcon(iconUrl).then(function(svg) {
          $(element).prepend(`${svg}`);
        });
      });
    },
  };
})(jQuery, Drupal, once, drupalSettings);