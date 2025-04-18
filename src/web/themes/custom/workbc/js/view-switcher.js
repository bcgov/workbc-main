(function ($, Drupal, once) {
  ("use strict");
  Drupal.behaviors.viewSwitcher = {
    attach: function (context, settings) {
      // the second parameter must be a selector specific to the content this script applies to, to ensure it's loaded after the content in the case the content is lazy loaded by Drupal
      once('viewSwitcher', '.toggle-icon .list', context).forEach(function (element) {
          element.onclick = function () {
            $('.toggle-icon .list').addClass('active');
            $('.toggle-icon .grid').removeClass('active');
            $(`.switcher-row .switcher-column`).fadeOut(300, function() {
              $(this).removeClass('col-lg-4 col-md-6 col-12 grid-view').addClass('col-lg-6 col-12  list-column').fadeIn(300);
            });
          };
      });
      once('viewSwitcher', '.toggle-icon .grid', context).forEach(function (element) {
        element.onclick = function () {
          $('.toggle-icon .grid').addClass('active');
          $('.toggle-icon .list').removeClass('active');
          $(`.switcher-row .switcher-column`).fadeOut(300, function() {
            $(this).removeClass('col-lg-6 col-12 list-column').addClass('col-lg-4 col-md-6 col-12 grid-view').fadeIn(300);
          });
        };
      });
      let isList = $('.toggle-icon .list').hasClass('active');
      $('.switcher-row .switcher-column', context).each(function () {
        $(this).removeClass('col-lg-4 col-md-6 col-12 grid-view col-lg-6 col-12 list-column');
        if (isList) {
          $(this).addClass('col-lg-6 col-12 list-column');
        } else {
          $(this).addClass('col-lg-4 col-md-6 col-12 grid-view');
        }
      });
      $(document).ajaxComplete(function() {

        var totalCount = $('.plan-careercareer-trek-videos .view-content.result-view .switcher-row div.career-grid').length;
        // Update the .update-result text
        $('.plan-careercareer-trek-videos .result-summary .update-result').text(totalCount);
      });
      once('viewSwitcher', '.career-videos-filters details', context).forEach(function (element, index) {
        if (index < 2) {
          element.setAttribute('open', '');
        }
      });
    },
  };

})(jQuery, Drupal, once);
