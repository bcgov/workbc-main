(function ($, Drupal, once) {
  ("use strict");

  let initSwiperCarousel = function() {

    const initSlideCount = jQuery('.swiper .swiper-slide').length;

    const swiper = new Swiper('.swiper', {
      direction: 'horizontal',
      loop: initSlideCount >= 3, // If we have less than three slides, we don't really need a loop since we can see them all, and it does weird things with duplicate slides.
      centeredSlides: true,
      loopedSlidesLimit: true,
      breakpoints: {
        // pooops the bed at <576px wide
        1: {
          slidesPerView: 1,
          spaceBetween: 50
        },
        640: {
          slidesPerView: 1,
          spaceBetween: 20
        },
        768: {
          slidesPerView: 2,
          spaceBetween: 20
        },
        992: {
          slidesPerView: 3,
          spaceBetween: 20
        },
      },

      pagination: {
        el: '.swiper-pagination',
      },

      navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },
    });
  };

  Drupal.behaviors.swiperCarousel = {
    attach: function (context, settings) {
      // the second parameter must be a selector specific to the content this script applies to, to ensure it's loaded after the content in the case the content is lazy loaded by Drupal
      once('swiperCarousel', '.swiper', context).forEach(initSwiperCarousel);
    },
  };

})(jQuery, Drupal, once);
