(function ($) {
  ("use strict");

  // Scroll detection for Banner Top offset.
  Drupal.behaviors.bannerTop = {
    attach: function (context, settings) {
      // add class to body if top banner is present
      $(document).ready(function () {
        if ($(".alert-ribbon").length) {
          $("body").addClass("page-has-alert");
        }
      });

      // detect scroll and add class to body
      $(window).scroll(function () {
        var y_scroll_pos = window.pageYOffset;
        var scroll_pos_test = 142;

        if (y_scroll_pos > scroll_pos_test) {
          $("body").addClass("nav-fixed");
        } else {
          $("body").removeClass("nav-fixed");
        }
      });
    },
  };
})(jQuery);
