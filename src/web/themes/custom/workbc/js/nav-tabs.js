(function ($, Drupal, once) {
  ("use strict");

  function findTab(name, context) {
    let triggerEl = $(`a[data-bs-target="${name}-content"]`, context);
    if (triggerEl.length) {
      return triggerEl;
    }
    const match = name.match(/(#.*?)-content-/);
    if (match) {
      triggerEl = $(`a[data-bs-target="${match[1]}-content"]`, context);
      if (triggerEl.length) {
        return triggerEl;
      }
    }
    return [];
  }

  Drupal.behaviors.nav_tabs = {
    attach: function (context) {
      // Detect URL anchor and activate corresponding tab if found.
      if (window.location.hash) {
        const triggerEl = findTab(window.location.hash, context);
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

      // Change the anchor when a tab is clicked.
      $('.nav-link', context).not('.new-logout-link .nav-link').not('.new-login-link .nav-link').on('click', function (e) {
        window.location.hash = $(e.target).attr('href');
      });

      // Change tab when navigating to a different anchor.
      $(window).on('hashchange', function () {
        const triggerEl = window.location.hash ?
          findTab(window.location.hash, context) :
          $('a[data-bs-target]:first', context);
        if (triggerEl.length && !triggerEl.hasClass('active')) {
          bootstrap.Tab.getOrCreateInstance(triggerEl[0]).show();
          document.querySelector(".profile-content-tabs").scrollIntoView({
            behavior: 'smooth',
            block: 'center'
          });
        }
      });
    },
  };

})(jQuery, Drupal, once);
