(function ($, Drupal, once) {
  ("use strict");

  // Mobile Nav Close
  Drupal.behaviors.mobileNavClose = {
    attach: function (context, settings) {
      var mobileNavClose = $(once("mobileNavClose", ".mobile-nav-close", context));
      var offCanvas = $("#off-canvas")[0];
      var mmenuApi = offCanvas.mmApi;

      mobileNavClose.click(function () {
        var opened = offCanvas.classList.contains("mm-menu--opened");
        // Trigger the open or close method of mmenu.js.
        mmenuApi[opened ? "close" : "open"]();
      });
    },
  };
})(jQuery, Drupal, once);
