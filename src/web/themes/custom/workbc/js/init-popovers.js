(function ($, Drupal, once) {
  ("use strict");

  let initPopovers = function () {
    if($().popover) {
      $('[data-bs-toggle="popover"]').popover();
    }
  };

  Drupal.behaviors.initPopoverBehavior = {
    attach: function (context, settings) {

      initPopovers();

    },
  };

})(jQuery, Drupal, once);
