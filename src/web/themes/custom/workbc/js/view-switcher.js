(function ($, Drupal, once) {
    ("use strict");
    Drupal.behaviors.viewSwitcher = {
      attach: function (context, settings) {
        // the second parameter must be a selector specific to the content this script applies to, to ensure it's loaded after the content in the case the content is lazy loaded by Drupal
        once('viewSwitcher', '.toggle-icon .list', context).forEach(function (element) {
            element.onclick = function () {
              $(`.switcher-row .switcher-column`).fadeOut(300, function() {
                $(this).removeClass('col-4 grid-view').addClass('col-12 list-column').fadeIn(300);
              });
            };
        });
        once('viewSwitcher', '.toggle-icon .grid', context).forEach(function (element) {
            element.onclick = function () {
              $(`.switcher-row .switcher-column`).fadeOut(300, function() {
                $(this).removeClass('col-12 list-column').addClass('col-4 grid-view').fadeIn(300);
              });
            };
        });
      },
    };
  
  })(jQuery, Drupal, once);
  