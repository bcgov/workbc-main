(function ($, Drupal) {

  let carousel = $('#relatedTopicsCarousel');

  let updateVisible = function () {

    let items = carousel.find(".carousel-item");
    let active = items.filter(".active");
    let activeIndex = items.index(active);

    // This should generally be handled by intercepting the bootstrap slide event, but we need this for initial load
    if(activeIndex < 1) {
      // don't allow 'active' to be at the very start, it always needs a previous sibling
      active.removeClass('active');
      $(items[1]).addClass('active');
    } else if (activeIndex == items.length - 1) {
      // don't allow 'active' to be at the very end - it always needs a next sibling
      active.removeClass('active');
      $(items[items.length - 2]).addClass('active');
    }

    // update index with changes
    active = items.filter(".active");
    activeIndex = items.index(active);

    items.removeClass("prev");
    items.removeClass("next");

    $(items[activeIndex - 1]).addClass("prev");
    $(items[activeIndex + 1]).addClass("next");
  };

  let checkBounds = function (event) {
    // this prevents the Bootstrap code from allowing a slide to the very first or last item, since we always want to maintain siblings on either side
    let items = carousel.find(".carousel-item");

    if(event.type == 'slide' && (event.to == 0 || event.to == items.length - 1)) {
        event.stopPropagation();
        return false;
    }
  };

  carousel.on('slide.bs.carousel', checkBounds);
  carousel.on('slid.bs.carousel', updateVisible);

  updateVisible();

})(jQuery, Drupal);