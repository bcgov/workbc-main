(function ($, Drupal, once) {
  ("use strict");

  let initSwiperCarousel = function() {
    const initSlideCount = jQuery('.swiper-video-banner .swiper-slide').length;

    const swiper = new Swiper('.swiper-video-banner',{
      pagination: {
        el: '.swiper-pagination',
        clickable: true
      },
    });
  };

  Drupal.behaviors.swiperCarousel = {
    attach: function (context, settings) {
      once('swiperCarousel', '.swiper-video-banner', context).forEach(initSwiperCarousel);
      const video = document.querySelector('.hero-video .media--type-video video');
      const videoButton = document.querySelector('.video-button');
      
      if (video && videoButton) {
        // Remove audio from video
        video.muted = true;
        
        // Handle video end event
        video.addEventListener('ended', () => {
          video.style.display = 'none';
          $('.career-trek-row').addClass('video-stop')
        });

        // Toggle play/pause on button click
        videoButton.addEventListener('click', function() {
          if (video.paused) {
            video.play();
          } else {
            video.pause();
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
