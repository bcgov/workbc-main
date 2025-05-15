(function ($, Drupal, once) {
  ("use strict");

  Drupal.behaviors.accordionScroll = {
    attach: function (context, settings) {
      const accordions = once('accordion-scroll', '.workbc-accordion-component .accordion-item', context);
      $(accordions).on('show.bs.collapse', function (e) {
        const top = this.getBoundingClientRect().top;
        const siblings = $(this).siblings().find('.collapse.show, .collapse.collapsing');
        if (siblings.length) {
          const rect = siblings[0].getBoundingClientRect();
          if (rect.top < top && top < rect.height + 80) {
            window.scrollTo({ top: window.scrollY - rect.height, behavior: 'instant' });
          }
        }
      });
    }
  }
})(jQuery, Drupal, once);
