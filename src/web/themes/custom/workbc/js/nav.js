(function ($) {
  ("use strict");

  // Scroll detection for Banner Top offset.
  Drupal.behaviors.bannerTop = {
    attach: function (context, settings) {
      // detect scroll and add class to body
      $("body").scroll(function () {
        var y_scroll_pos = document.body.scrollTop;
        var scroll_pos = 142;

        if (y_scroll_pos > scroll_pos) {
          $("body").addClass("nav-fixed");
        } else {
          $("body").removeClass("nav-fixed");
        }
      });
    },
  };
})(jQuery);
