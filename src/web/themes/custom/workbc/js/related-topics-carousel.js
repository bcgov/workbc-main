(function ($, Drupal, once) {
  ("use strict");

  let initRelatedTopicsCarousel = function() {
    // see https://www.codeply.com/p/0CWffz76Q9 for inspiration on desktop size multiple panel view
    let items = document.querySelectorAll('.carousel .carousel-item');
    const minPerSlide = Math.min(items.length, 3);

    items.forEach((el) => {
      let next = el.nextElementSibling;
      for (var i = 1; i < minPerSlide; i++) {
        if (!next) {
            // wrap carousel by using first child
          next = items[0]
        }
        let cloneChild = next.cloneNode(true)
        el.appendChild(cloneChild.children[0])
        next = next.nextElementSibling
      }
    })
  };

  Drupal.behaviors.relatedTopicsCarousel = {
    attach: function (context, settings) {
      // the second parameter must be a selector specific to the content this script applies to, to ensure it's loaded after the content in the case the content is lazy loaded by Drupal
      once('relatedTopicsCarousel', '.workbc-card-carousel', context).forEach(initRelatedTopicsCarousel);
    },
  };

})(jQuery, Drupal, once);