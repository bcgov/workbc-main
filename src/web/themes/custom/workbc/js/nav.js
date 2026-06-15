(function ($) {
  ("use strict");

  let timeStampFocus = 0;

  // Time in ms between focus and click event that we consider to be "the same user event".
  // Take into consideration reduced event.timeStamp precision for privacy purposes.
  // @see https://developer.mozilla.org/en-US/docs/Web/API/Event/timeStamp
  const TIMESTAMP_DELTA = 100;

  // Manage the main navigation menu open/close status.
  Drupal.behaviors.mainNav = {
    attach: function (context, settings) {
      $(once("mainNav", ".nav-t1 > .nav-item", context)).on('focus', function(event) {
        timeStampFocus = event.timeStamp;
        $(this).parent().children(".nav-item").removeClass('open');
        $(this).addClass('open');
      }).on('click', function(event) {
        if (Math.abs(event.timeStamp - timeStampFocus) > TIMESTAMP_DELTA) {
          const alreadyOpen = $(event.target).is('.open') || $(event.target).parent('.open').length > 0;
          if (alreadyOpen) {
            $(this).parent().children(".nav-item").removeClass('open');
          }
          else {
            $(this).addClass('open');
          }
        }
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
