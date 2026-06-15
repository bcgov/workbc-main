let currentCatId = null;

(function ($, Drupal, once) {

  Drupal.behaviors.supportCard = {
    attach: function (context, settings) {

      $(once('support-card', '.paragraph--type--action-cards-1-3 .card-support-expand')).on('click', function() {
        const cardId = $(this).data('card-id');
        const card = document.querySelectorAll(`#workbc-card-support-${cardId}`);
        const top = document.querySelectorAll(`#card-top-${cardId}`);
        const bottom = document.querySelectorAll(`#card-bottom-${cardId}`);

        $(top).toggleClass('mobile-is-hidden');
        $(bottom).toggleClass('mobile-is-hidden');

        if ($(top).hasClass('mobile-is-hidden')) {
          $(card).find('.card-support-expand img').attr('src', '/modules/custom/workbc_custom/icons/expand.svg');
        }
        else {
          $(card).find('.card-support-expand img').attr('src', '/modules/custom/workbc_custom/icons/collapse.svg');
        }
      });

    }
  }
})(jQuery, Drupal, once);
