(function ($, Drupal, once) {
  ("use strict");

  let initSwiperCarousel = function() {
    const swiper = new Swiper('.swiper', {
      direction: 'horizontal',
      loop: true,
      centeredSlides: true,
      breakpoints: {
        // pooops the bed at <576px wide
        640: {
          slidesPerView: 1,
          spaceBetween: 50
        },
        768: {
          slidesPerView: 2,
          spaceBetween: 50
        },
        1024: {
          slidesPerView: 2,
          spaceBetween: 20
        },
        1200: {
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