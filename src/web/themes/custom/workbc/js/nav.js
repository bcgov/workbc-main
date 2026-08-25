(function ($) {
  ("use strict");

  // Manage the main navigation menu open/close status.
  Drupal.behaviors.mainNav = {
    attach: function (context, settings) {
      $(once("mainNav", ".nav-t1 .nav-link", context)).on("focus", function(event) {
        console.log(".nav-link FOCUS");
      }).on("blur", function(event) {
        console.log(".nav-link BLUR");
        $(this).parents(".nav-t1").children(".nav-item").removeClass("open").children(".nav-link").attr("aria-expanded", "false");
      });
      $(once("mainNav", ".nav-t1 > .nav-item", context)).on("click", function(event) {
        console.log(".nav-item CLICK");
        const alreadyOpen = $(event.target).is(".open") || $(event.target).parent(".open").length > 0;
        if (alreadyOpen) {
          $(this).parent().children(".nav-item").removeClass("open").children(".nav-link").attr("aria-expanded", "false");
        }
        else {
          $(this).addClass("open").children(".nav-link").attr("aria-expanded", "true").focus();
        }
      }).on("keyup", function(event) {
        console.log(".nav-item KEYUP");
        if (event.key == "Enter" || event.key == " " || event.key == "Spacebar") {
          const alreadyOpen = $(event.target).is(".open") || $(event.target).parent(".open").length > 0;
          if (alreadyOpen) {
            $(this).parent().children(".nav-item").removeClass("open").children(".nav-link").attr("aria-expanded", "false");
          }
          else {
            $(this).addClass("open").children(".nav-link").attr("aria-expanded", "true").focus();
          }
          return false;
        }
      }).on("keypress", function(event) {
        console.log(".nav-item KEYPRESS");
        if (event.key == " " || event.key == "Spacebar") {
          return false;
        }
      }).on("keydown", function(event) {
        console.log(".nav-item KEYDOWN");
        if (event.key == " " || event.key == "Spacebar") {
          return false;
        }
      });
      $(once("mainNav", ".nav-t2 .nav-link, .megamenu-splash *", context)).on("blur", function(event) {
        console.log(".nav-link BLUR");
        if (event.relatedTarget && !$(event.relatedTarget).parents('.nav-t1').length) {
          $(".nav-t1 > .nav-item").removeClass("open").children(".nav-link").attr("aria-expanded", "false");
        }
      });
      $(once("mainNav", "body", context)).on("click", function(event) {
        console.log("body CLICK");
        if ($(event.target).parents(".nav-t1").length > 0) return;
        $(".nav-t1 > .nav-item").removeClass("open").children(".nav-link").attr("aria-expanded", "false");
      });
      $(document).on('keyup', function(event) {
        console.log("document KEYUP");
        if (event.key == "Escape") {
          $(".nav-t1 > .nav-item.open").focus();
          $(".nav-t1 > .nav-item").removeClass("open").children(".nav-link").attr("aria-expanded", "false");
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
