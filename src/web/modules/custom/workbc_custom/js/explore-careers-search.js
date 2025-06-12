(function ($, Drupal, once) {
  ("use strict");

  Drupal.behaviors.exploreCareersSearch = {
    attach: function (context) {
      $(once('exploreCareersSearch', '.career-profile-titles-toggle a', context)).on('click', function() {
        const $titles = $(this).closest('.card-left, .card-right').children('.career-profile-titles');
        $titles.toggle();
        $(this).text($titles.is(':visible') ? Drupal.t('Hide job titles') : Drupal.t('Show job titles'));
      });
    }
  }
})(jQuery, Drupal, once);
