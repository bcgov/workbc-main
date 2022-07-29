(function ($, Drupal) {

  let carousel = $('#relatedTopicsCarousel');

  let updateVisible = function () {

    let items = carousel.find(".carousel-item");
    let active = items.filter(".active");
    let activeIndex = items.index(active);

    if(activeIndex < 1) {
      carousel.addClass('at-start');
    } else { 
      carousel.removeClass('at-start');     
    } 

    if (activeIndex == items.length - 1) {
      carousel.addClass('at-end');
    } else { 
      carousel.removeClass('at-end');
    } 

    // update index with changes
    active = items.filter(".active");
    activeIndex = items.index(active);

    items.removeClass("prev");
    items.removeClass("next");

    $(items[activeIndex - 1]).addClass("prev");
    $(items[activeIndex + 1]).addClass("next");
  };

  carousel.on('slid.bs.carousel', updateVisible);

  updateVisible();

})(jQuery, Drupal);