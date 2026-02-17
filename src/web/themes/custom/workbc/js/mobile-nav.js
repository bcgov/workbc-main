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

      $(once("mobileNavClose", ".menu-name--account > a", context))
      .not('#menu-item-unlogged-account')
      .not('#menu-item-logged-account')
      .on('click' , function() {
        if (window.location.pathname !== "/account") return;
        if ($(this).prev('#menu-item-unlogged-account').length > 0) return;
        if ($(this).prev('#menu-item-logged-account').length > 0) return;
        mmenuApi["close"]();
      });
    },
  };
})(jQuery, Drupal, once);
