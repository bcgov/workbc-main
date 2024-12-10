(function ($, Drupal, once) {
  ("use strict");

  Drupal.behaviors.nav_tabs = {
    attach: function (context) {
      // Detect URL anchor and activate corresponding tab if found.
      if (window.location.hash) {
        const triggerEl = $(`a[data-bs-target="${window.location.hash}-content"]`, context);
        if (triggerEl.length) {
          bootstrap.Tab.getOrCreateInstance(triggerEl[0]).show();
        }
      }
      else {
        const triggerEl = $('a[data-bs-target]:first', context);
        if (triggerEl.length) {
          window.location.hash = triggerEl.data('bs-target').replace('-content', '');
        }
      }

      // Change the anchor when a tab is opened.
      $('.nav-link', context).on('shown.bs.tab', function (e) {
        const anchor = $(e.target).attr('href');
        window.location.hash = anchor;
      });

      // Change tab when navigating to a different anchor.
      $(window).on('hashchange', function () {
        const triggerEl = window.location.hash ?
          $(`a[data-bs-target="${window.location.hash}-content"]`, context) :
          $('a[data-bs-target]:first', context);
        if (triggerEl.length && !triggerEl.hasClass('active')) {
          bootstrap.Tab.getOrCreateInstance(triggerEl[0]).show();
        }
      });
    },
  };

})(jQuery, Drupal, once);
