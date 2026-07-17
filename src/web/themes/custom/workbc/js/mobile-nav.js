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
      }).on('keyup', function(event) {
        if (event.key == "Enter" || event.key == " " || event.key == "Spacebar") {
          var opened = offCanvas.classList.contains("mm-menu--opened");
          mmenuApi[opened ? "close" : "open"]();
        }
      });

      function updateSplash(panel) {
        const $parent = $(`#${panel.dataset.mmParent}`, offCanvas);
        if ($parent.length > 0 && $parent[0].dataset.splash) {
          if (!$(".megamenu-splash", panel).length) {
            $(panel).append(`<div class="megamenu-splash">${$parent[0].dataset.splash}</div>`);
          }
        }
      }

      let parentId = null;
      function updateFocus(panel) {
        setTimeout(() => {
          $(`#${parentId} a`, offCanvas).focus();
          parentId = panel.dataset.mmParent ?? null;
        }, 0);
      }

      $(once("mobileNavClose", "#off-canvas", context)).each(() => {
        mmenuApi.bind('open:after', () => {
          const panel = $(".mm-panel--opened", offCanvas)[0];
          updateFocus(panel);
          updateSplash(panel);
        });
        mmenuApi.bind('openPanel:before', (panel) => {
          updateFocus(panel);
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
