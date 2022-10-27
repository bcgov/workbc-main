(function ($, Drupal, once) {
  ("use strict");

  let initialize = function (containerJquery) {
    let container = $(containerJquery);
    let initialCount = container.data('static-load-more-initial');
    let items = container.children('[data-static-load-more-items]').first().children();
    let trigger = container.find('[data-static-load-more-trigger]').first();

    items.slice(initialCount).hide();

    trigger.on('click', function() { loadMore(container) });

    container.show();
  };

  let loadMore = function (containerJquery) {
    let container = $(containerJquery);
    let stepCount = container.data('static-load-more-step');
    let hiddenItems = container.children('[data-static-load-more-items]').first().children(':hidden');
    
    hiddenItems.slice(0, stepCount).show();
      
    if(hiddenItems.length <= stepCount) {
      let trigger = container.find('[data-static-load-more-trigger]').first();
      trigger.hide();
    }
  }

  Drupal.behaviors.initStaticLoadMoreBehavior = {
    attach: function (context, settings) {
      once('initStaticLoadMoreBehavior', '[data-static-load-more-container]', context).forEach(initialize);
    },
  };

})(jQuery, Drupal, once);
