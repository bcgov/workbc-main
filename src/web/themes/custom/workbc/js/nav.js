(function ($) {
  ("use strict");

  // Manage the main navigation menu open/close status.
  Drupal.behaviors.mainNav = {
    attach: function (context, settings) {
      $(once("mainNav", ".nav-t1 > .nav-item", context)).on('focus', function(event) {
        $(this).parent().children(".nav-item").removeClass('open').attr('aria-expanded', 'false');
      }).on('blur', function(event) {
        if (event.relatedTarget && !$(event.relatedTarget).hasClass('nav-link') && !(event.relatedTarget.parent('.megamenu-splash').length > 0)) {
          $(this).removeClass('open').attr('aria-expanded', 'false');
        }
      }).on('click', function(event) {
        const alreadyOpen = $(event.target).is('.open') || $(event.target).parent('.open').length > 0;
        if (alreadyOpen) {
          $(this).parent().children(".nav-item").removeClass('open').attr('aria-expanded', 'false');
        }
        else {
          $(this).addClass('open').attr('aria-expanded', 'true');
        }
      }).on('keyup', function(event) {
        if (event.key == "Enter" || event.key == " " || event.key == "Spacebar") {
          const alreadyOpen = $(event.target).is('.open') || $(event.target).parent('.open').length > 0;
          if (alreadyOpen) {
            $(this).parent().children(".nav-item").removeClass('open').attr('aria-expanded', 'false');
          }
          else {
            $(this).addClass('open').attr('aria-expanded', 'true');
          }
          return false;
        }
      }).on('keypress', function(event) {
        if (event.key == " " || event.key == "Spacebar") {
          return false;
        }
      }).on('keydown', function(event) {
        if (event.key == " " || event.key == "Spacebar") {
          return false;
        }
      });
      $(once("mainNav", ".nav-t2 .nav-link", context)).on('blur', function(event) {
        if (event.relatedTarget && !$(event.relatedTarget).parents('.nav-t1').length) {
          $(".nav-t1 > .nav-item").removeClass('open').attr('aria-expanded', 'false');
        }
      });
      $(once("mainNav", "body", context)).on('click', function(event) {
        if ($(event.target).parents(".nav-t1").length > 0) return;
        $(".nav-t1 > .nav-item").removeClass('open').attr('aria-expanded', 'false');
      });
      $(document).on('keyup', function(event) {
        if (event.key == "Escape") {
          $(".nav-t1 > .nav-item.open").focus();
          $(".nav-t1 > .nav-item").removeClass('open').attr('aria-expanded', 'false');
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
