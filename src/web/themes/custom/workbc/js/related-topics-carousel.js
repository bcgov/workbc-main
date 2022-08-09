(function ($, Drupal) {

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

})(jQuery, Drupal);