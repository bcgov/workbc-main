(function ($, Drupal, once) {
  ("use strict");

  Drupal.behaviors.nav_tabs = {
    attach: function (context) {
      // Detect URL anchor and activate corresponding tab if found.
      if (window.location.hash) {
        const triggerEl = $(once('nav_tabs', `a[data-bs-target="${window.location.hash}-content"]`, context));
        if (triggerEl.length > 0) {
          bootstrap.Tab.getOrCreateInstance(triggerEl[0]).show();
        }
      }

      // Change the anchor when a tab is opened.
      $('.nav-link', context).on('shown.bs.tab', function (e) {
        const anchor = $(e.target).attr('href');
        window.location.hash = anchor;
      });
    },
  };

})(jQuery, Drupal, once);
