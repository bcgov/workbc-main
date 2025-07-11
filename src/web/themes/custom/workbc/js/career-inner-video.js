(function ($, Drupal, once) {
    "use strict";

    Drupal.behaviors.careerInnerVideo = {
      attach: function (context, settings) {
        // Autoplay on video click
        $('.profile-video', context).each(function () {
          $(this).addClass('active');


          const iframe = $(this).find('iframe');
          $('.profile-video')[0].offsetHeight;
          $(window).on('resize.careerInnerVideo', function () {
            iframe.css({
              'width': $('.profile-video').parent().width() ?? "100%"
            });
          });

          setTimeout(() => {
              iframe.css({
                  'position': 'absolute',
                  'display': 'block',
                  'z-index': '1',
                  'width': $('.profile-video').parent().width() ?? "100%"
              })
          }, 200);
          // Force reflow

          if (iframe.length) {
          } else {
            console.warn('No iframe found inside .profile-video');
          }
        });

        // Sticky scroll behavior
        const $topVideoRow = $('.top-video-row', context);
        if ($topVideoRow.length) {
          const originalOffsetTop = $topVideoRow.offset().top;

          $(window).on('scroll.careerInnerVideoSticky', function () {
            const scrollTop = $(window).scrollTop();

            if (scrollTop >= originalOffsetTop) {
              $topVideoRow.css({
                'position': 'sticky',
                'top': '0',
                left: 0,
                'z-index': '499',
                'background': '#fff'
              });
            } else {
              $topVideoRow.css({
                'position': 'static',
                'z-index': '1'
              });
            }
          });
        }
      }
    };
  })(jQuery, Drupal, once);
