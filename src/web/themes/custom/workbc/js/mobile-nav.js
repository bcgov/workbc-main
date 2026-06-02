(function ($, Drupal, once) {
  ("use strict");

  // Mobile Nav Close
  Drupal.behaviors.mobileNavClose = {
    attach: function (context, settings) {
      const offCanvas = document.querySelector("#off-canvas");
      const mmenuApi = offCanvas.mmApi;

      $(once("mobileNavClose", ".mobile-nav-close", context)).on('click', function() {
        var opened = offCanvas.classList.contains("mm-menu--opened");
        // Trigger the open or close method of mmenu.js.
        mmenuApi[opened ? "close" : "open"]();
      });

      function updateSplash(panel) {
        const $parent = $(`#${panel.dataset.mmParent}`, offCanvas);
        if ($parent.length > 0 && $parent[0].dataset.splash) {
          if (!$(".megamenu-splash", panel).length) {
            $(panel).append(`<div class="megamenu-splash">${$parent[0].dataset.splash}</div>`);
          }
        }
      }

      $(once("mobileNavClose", "#off-canvas", context)).each(() => {
        mmenuApi.bind('open:after', () => {
          updateSplash($(".mm-panel--opened", offCanvas)[0]);
        });
        mmenuApi.bind('openPanel:before', (panel) => {
          updateSplash(panel);
        });
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
