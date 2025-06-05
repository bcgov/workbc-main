(function ($, Drupal, once) {
  ("use strict");

  // Mobile Nav Close
  Drupal.behaviors.mobileNavClose = {
    attach: function (context, settings) {
      const offCanvas = $("#off-canvas")[0];
      const mmenuApi = offCanvas.mmApi;

      $(once("mobileNavClose", ".mobile-nav-close", context)).on('click', function() {
        var opened = offCanvas.classList.contains("mm-menu--opened");
        // Trigger the open or close method of mmenu.js.
        mmenuApi[opened ? "close" : "open"]();
      });

      $(once("mobileNavClose", ".new-logout-link > .nav-link", context)).on('click' , function() {
        if (window.location.pathname == "/account") {
          mmenuApi["close"]();
        }
      });
    },
  };
})(jQuery, Drupal, once);
