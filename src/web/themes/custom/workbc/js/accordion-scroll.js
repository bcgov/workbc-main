(function ($, Drupal, once) {
  ("use strict");

  Drupal.behaviors.accordionScroll = {
    attach: function (context, settings) {
      let top = null;
      const accordions = once('accordion-scroll', '.workbc-accordion-component .accordion-item', context);
      $(accordions).on('show.bs.collapse', function (e) {
        if (!$('.accordion-collapse', this).hasClass('show')) {
          top = this.getBoundingClientRect().top;
        }
      });
      $(accordions).on('shown.bs.collapse', function (e) {
        if ($('.accordion-collapse', this).hasClass('show') && top) {
          window.scrollTo({ top: window.scrollY - (top - this.getBoundingClientRect().top), behavior: 'instant' });
        }
      });
    }
  }
})(jQuery, Drupal, once);
