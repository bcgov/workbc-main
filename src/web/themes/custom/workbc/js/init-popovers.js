(function (Drupal, $, once) {
  ("use strict");

  // this set of functions is intended to cause popovers to close if the user clicks anywhere outside of them
  // see https://stackoverflow.com/a/69602400/495000
  const managePopoverClosure = function () {
    $(document).on('click', function (e) {
      const $target = $(e.target);
      //do nothing if there was a click on popover content
      if ($target.hasClass('popover') || $target.closest('.popover').length) {
        return;
      }
      $('[data-bs-toggle="popover"]').each(function () {
        const $popover = $(this);
        if (
          !$popover.is(e.target) &&
          $popover.has(e.target).length === 0 &&
          $('.popover').has(e.target).length === 0
        ) {
          $popover.popover('hide');
        }
      });
    });

    $(document).on('keyup', function(event) {
      if (event.key == "Escape") {
        $('[data-bs-toggle="popover"]').each(function () {
          $(this).popover('hide');
        });
      }
    });

    $(document).on('blur', '[data-bs-toggle="popover"]', function(event) {
      if (!event.relatedTarget || $(event.relatedTarget).parents('.popover').length == 0) {
        $(this).popover('hide');
      }
    });
  }

  const initPopovers = function () {
    $(document).ready(function() {
      managePopoverClosure();
      $('[data-bs-toggle="popover"]').popover();
    });
  };

  Drupal.behaviors.initPopoverBehavior = {
    attach: function (context, settings) {
      $(once('initPopoverBehavior', '.info-tooltip', context)).each(initPopovers);
    },
  };

})(Drupal, jQuery, once);
