(function ($, Drupal, once) {
  ("use strict");
  Drupal.behaviors.viewSwitcher = {
    attach: function (context, settings) {

      function applyViewMode() {
        let isList = $('.toggle-icon .list').hasClass('active');
        let winWidth = window.innerWidth;
        $('.switcher-row .switcher-column', context).each(function () {
          $(this).removeClass('col-lg-4 col-sm-6 col-12 grid-view col-lg-6 col-12 list-column');
          // On screens <= 575px, always use list view
          if (winWidth <= 575) {
            $(this).addClass('col-lg-6 col-12 list-column');
            $('.toggle-icon .list').addClass('active');
            $('.toggle-icon .grid').removeClass('active');
          } else if (isList) {
            $(this).addClass('col-lg-6 col-12 list-column');
          } else {
            $(this).addClass('col-lg-4 col-sm-6 col-12 grid-view');
          }
        });
      }

      // the second parameter must be a selector specific to the content this script applies to, to ensure it's loaded after the content in the case the content is lazy loaded by Drupal
      once('viewSwitcher', '.toggle-icon .list', context).forEach(function (element) {
          element.onclick = function () {
            $('.toggle-icon .list').addClass('active');
            $('.toggle-icon .grid').removeClass('active');
            $(`.switcher-row .switcher-column`).fadeOut(300, function() {
              $(this).removeClass('col-lg-4 col-sm-6 col-12 grid-view').addClass('col-lg-6 col-12  list-column').fadeIn(300);
            });
          };
      });
      once('viewSwitcher', '.toggle-icon .grid', context).forEach(function (element) {
        element.onclick = function () {
          $('.toggle-icon .grid').addClass('active');
          $('.toggle-icon .list').removeClass('active');
          $(`.switcher-row .switcher-column`).fadeOut(300, function() {
            $(this).removeClass('col-lg-6 col-12 list-column').addClass('col-lg-4 col-sm-6 col-12 grid-view').fadeIn(300);
          });
        };
      });
      let isList = $('.toggle-icon .list').hasClass('active');
      $('.switcher-row .switcher-column', context).each(function () {
        $(this).removeClass('col-lg-4 col-sm-6 col-12 grid-view col-lg-6 col-12 list-column');
        if (isList) {
          $(this).addClass('col-lg-6 col-12 list-column');
        } else {
          $(this).addClass('col-lg-4 col-sm-6 col-12 grid-view');
        }
      });

      applyViewMode();
      $(window).on('resize', function () {
        applyViewMode();
      });

      $(document).ajaxComplete(function() {
        // applyViewMode();
        $('.plan-careercareer-trek-videos .view-id-career_trek_video_library.view-display-id-block_1 .workbc-card .workbc-card-container .workbc-card-content .workbc-card-title a, .view-id-related_careers_videos.view-display-id-block_1 .related-carrer .workbc-card-content .title a', context).each(function() {
          var text = $(this).text();
          $(this).text(toSentenceCase(text.trim()));
        });
        var totalCount = $('.plan-careercareer-trek-videos .view-content.result-view .switcher-row div.career-grid').length;
        // Update the .update-result text
        $('.plan-careercareer-trek-videos .result-summary .update-result').text(totalCount);
      });
      // once('viewSwitcher', '.career-videos-filters details', context).forEach(function (element, index) {
      //   if (index < 2) {
      //     element.setAttribute('open', '');
      //   }
      // });


      once('viewSwitcher', '.career-trek-sidebar .responsive-filter-video-btn', context).forEach(function (element) {
        element.onclick = function () {
            $(this).siblings('.career-trek-sidebar-panel').addClass('active');
        };
      });

      once('viewSwitcher', '.career-trek-sidebar .career-sidebar-close-btn', context).forEach(function (element) {
          element.onclick = function () {
              $(this).closest('.career-trek-sidebar-panel').removeClass('active');
          };
      });
      const checkCardImages = function(context) {
        once('workbcCardImageCheck', '.plan-careercareer-trek-videos .view-career-trek-video-library .workbc-card img', context).forEach(function(element) {
          const img = new Image();
          img.src = element.src;
          img.onload = function() {
            if (this.naturalWidth === 0) {
              element.closest('.workbc-card').classList.add('no-image-exist');
            }
          };
          img.onerror = function() {
            element.closest('.workbc-card').classList.add('no-image-exist');
          };
        });
      };

      const toSentenceCase = function(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
      };

      $('.plan-careercareer-trek-videos .view-id-career_trek_video_library.view-display-id-block_1 .workbc-card .workbc-card-container .workbc-card-content .workbc-card-title a, .view-id-related_careers_videos.view-display-id-block_1 .related-carrer .workbc-card-content .title a, .view-career-trek-node .career-title span', context).each(function() {
        var text = $(this).text();
        $(this).text(toSentenceCase(text.trim()));
      });

      // Run on initial load
      checkCardImages(context);

      // Run on ajaxComplete for infinite scroll
      $(document).ajaxComplete(function() {
        checkCardImages(document);
      });
    },
  };

})(jQuery, Drupal, once);
