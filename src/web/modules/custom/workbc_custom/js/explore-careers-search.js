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
        $(this).attr('aria-labelledby', $(this).closest('.form-item').find('label').attr('for'));
      });
      $(once('exploreCareerSearch', '.chosen-mobile', context)).each(function() {
        $(this).text($(this).closest('.form-item').find('select').data('description'));
      });
      $(once('exploreCareerSearch', '.chosen-enable', context)).on('change', function() {
        $(this).closest('.form-item').find('input.chosen-search-input').attr('placeholder', '');
      });
    }
  }
})(jQuery, Drupal, once);
