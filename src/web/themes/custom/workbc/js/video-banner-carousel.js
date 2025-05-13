(function ($, Drupal, once) {
  ("use strict");

  let initBannerSwiperCarousel = function() {
    const initSlideCount = jQuery('.swiper-video-banner .swiper-slide').length;

    const swiper = new Swiper('.swiper-video-banner',{
      pagination: {
        el: '.swiper-pagination',
        clickable: true
      },
    });
  };

  Drupal.behaviors.bannerSwiperCarousel = {
    attach: function (context, settings) {
      once('bannerSwiperCarousel', '.swiper-video-banner', context).forEach(initBannerSwiperCarousel);
      const video = document.querySelector('.hero-video .media--type-video video');
      const videoButton = document.querySelector('.video-button');

      if (video && videoButton) {
        // Remove audio from video
        video.muted = true;

        // Handle video end event
        video.addEventListener('ended', () => {
          // video.style.display = 'none';
          $('.career-trek-row').addClass('video-stop')
        });

        // Toggle play/pause on button click
        videoButton.addEventListener('click', function() {
          if (video.paused) {
            video.play();
            videoButton.innerHTML = '<svg width="23" height="27" viewBox="0 0 23 27" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.625 2.25C2.99219 2.25 2.5 2.8125 2.5 3.375V23.625C2.5 24.2578 2.99219 24.75 3.625 24.75H7C7.5625 24.75 8.125 24.2578 8.125 23.625V3.375C8.125 2.8125 7.5625 2.25 7 2.25H3.625ZM0.25 3.375C0.25 1.54688 1.72656 0 3.625 0H7C8.82812 0 10.375 1.54688 10.375 3.375V23.625C10.375 25.5234 8.82812 27 7 27H3.625C1.72656 27 0.25 25.5234 0.25 23.625V3.375ZM16 2.25C15.3672 2.25 14.875 2.8125 14.875 3.375V23.625C14.875 24.2578 15.3672 24.75 16 24.75H19.375C19.9375 24.75 20.5 24.2578 20.5 23.625V3.375C20.5 2.8125 19.9375 2.25 19.375 2.25H16ZM12.625 3.375C12.625 1.54688 14.1016 0 16 0H19.375C21.2031 0 22.75 1.54688 22.75 3.375V23.625C22.75 25.5234 21.2031 27 19.375 27H16C14.1016 27 12.625 25.5234 12.625 23.625V3.375Z" fill="white"/></svg>'
          } else {
            video.pause();
            videoButton.innerHTML = '<svg class="play" width="32" height="32" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="white"><path d="M233 511c-49-5-95-23-135-54-20-15-45-43-59-66-6-10-19-35-23-47-5-13-10-34-13-50-3-19-3-56 0-76 5-28 12-50 24-75 13-27 27-46 49-68 22-22 41-36 68-49 25-12 47-19 75-24 19-3 56-3 76 0 28 5 50 12 75 24 27 13 46 27 68 49 22 22 36 41 49 68 9 18 13 30 18 48 6 24 8 37 8 65 0 28-2 41-8 65-5 18-9 30-18 48-13 27-27 46-49 68-22 22-41 36-68 49-25 12-47 19-75 24-15 2-47 3-62 2zm55-23c34-5 66-17 95-35 25-16 54-45 70-70 14-23 26-51 32-76 4-18 5-30 5-51 0-28-3-47-11-71-12-37-30-66-58-94-28-28-57-46-94-58-25-8-44-11-71-11-28 0-47 3-71 11-37 12-66 30-94 58-37 37-59 80-67 134-2 15-2 48 0 63 5 35 17 67 35 95 16 25 45 54 70 70 48 30 105 43 159 35z"/><path d="M198 362c-7-3-6 3-6-106 0-95 0-98 2-101 3-5 8-6 13-4 8 4 153 98 154 100 2 3 2 8 0 11-1 2-122 81-150 98-6 4-9 4-13 3z m75-68c32-21 59-38 59-38 0-1-116-75-118-76-1 0-1 28-1 76 0 48 0 76 1 76 1 0 27-17 59-38z"/></svg>';
          }
        });

        // Delay video start by 3 seconds
        setTimeout(() => {
          const playPromise = video.play();

          if (playPromise !== undefined) {
            playPromise.then(() => {
              // Video started playing successfully
            }).catch(error => {
              console.log('Autoplay failed:', error);
            });
          }
        }, 3000); // 3000 milliseconds = 3 seconds
      }
    },
  };

})(jQuery, Drupal, once);
