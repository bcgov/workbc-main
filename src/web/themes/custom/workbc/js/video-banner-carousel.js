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
      // the second parameter must be a selector specific to the content this script applies to, to ensure it's loaded after the content in the case the content is lazy loaded by Drupal
      once('swiperCarousel', '.swiper-video-banner', context).forEach(initSwiperCarousel);
    const video = document.querySelector('.hero-video .media--type-video video');
    const videoButton = document.querySelector('.video-button');
    
    if (video && videoButton) {
      videoButton.addEventListener('click', function() {
        if (video.paused) {
          video.play();
        } else {
          video.pause();
        }
      });
    }
    },
  };

})(jQuery, Drupal, once);
