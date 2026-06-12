(function ($) {
  ("use strict");

  // Manage the main navigation menu open/close status.
  Drupal.behaviors.mainNav = {
    attach: function (context, settings) {
      $(once("mainNav", ".nav-t1 > .nav-item", context)).on('click', function(event) {
        const alreadyOpen = $(event.target).is('.open') || $(event.target).parent('.open').length > 0;
        $(this).parent().children(".nav-item").removeClass('open');
        if (!alreadyOpen) $(this).addClass('open');
        return false;
      });
      $(once("mainNav", "body", context)).on('click', function(event) {
        if ($(event.target).parents(".nav-t1").length > 0) return;
        $(".nav-t1 > .nav-item").removeClass('open');
      });
      $(once("mainNav", document, context)).on("keyup", function(event) {
        if (event.key == "Escape") {
          $(".nav-t1 > .nav-item").removeClass('open');
        }
      });
    }
  }

  // Scroll detection for Banner Top offset.
  Drupal.behaviors.bannerTop = {
    attach: function (context, settings) {
      // detect scroll and add class to body
      $(window).scroll(function () {
        var y_scroll_pos = window.scrollY;
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
