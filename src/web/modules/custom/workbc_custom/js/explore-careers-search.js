(function ($, Drupal, once) {
  ("use strict");

  Drupal.behaviors.exploreCareersSearch = {
    attach: function (context) {
      $(once('exploreCareersSearch', '.career-profile-titles-toggle a', context)).on('click', function() {
        const $titles = $(this).closest('.views-row').find('.career-profile-titles');
        $titles.toggle();
        const $closest = $(this).closest('.card-left-or-right').find('.career-profile-titles');
        const $links = $(this).closest('.views-row').find('.career-profile-titles-toggle a');
        $links.text($closest.is(':visible') ? Drupal.t('Hide job titles') : Drupal.t('Show job titles'));
      });
      $(once('exploreCareerSearch', 'input.chosen-search-input', context)).each(function() {
        $(this).attr('aria-labelledby', $(this).closest('.js-form-item').find('label').attr('for'));
      });
    }
  }
})(jQuery, Drupal, once);
