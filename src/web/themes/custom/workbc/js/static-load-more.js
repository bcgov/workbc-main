(function ($, Drupal, once) {
  ("use strict");

  let initialize = function (containerJquery) {
    let container = $(containerJquery);
    let initialCount = container.data('static-load-more-initial');
    let moreText = container.data('static-more-text');
    let items = container.find('[data-static-load-more-items]').first().children();
    let trigger = container.find('[data-static-load-more-trigger]').first();

    // Hide the extra items: Either the ones outside the initial count, ...
    if (Number.isInteger(initialCount)) {
      items.slice(initialCount).hide();
    }
    // ... or the ones with "illustrative" flag = 0.
    else {
      items.filter(`[data-${initialCount}='0']`).hide();
    }

    trigger.on('click', function() { loadMore(container) });

    // If moreText parameter is populated, update link text, otherwise default to using
    // the text between the anchor tags.  ie: "<a ...>Show more</a>"
    if (moreText) {
      trigger.text(moreText)
    }

    container.show();
  };

  let loadMore = function (containerJquery) {
    let container = $(containerJquery);
    let lessText = container.data('static-less-text');
    let hiddenItems = container.find('[data-static-load-more-items]').first().children(':hidden');
    let trigger = container.find('[data-static-load-more-trigger]').first();

    // Save the location and show the next slice.
    container.data('scrollTop', document.documentElement.scrollTop ?? document.body.scrollTop);
    hiddenItems.show();

    // If lessText is populated, display the 'show less' link text
    if (lessText) {
      trigger.off('click');
      trigger.on('click', function() { showLess(container) });
      trigger.text(lessText);
    }
    // If lessText is not set, hide the 'show less' link
    else {
      trigger.hide();
    }
  }

  let showLess = function (containerJquery) {
    let container = $(containerJquery);
    let moreText = container.data('static-more-text');
    let initialCount = container.data('static-load-more-initial');
    let trigger = container.find('[data-static-load-more-trigger]').first();
    let items = container.find('[data-static-load-more-items]').first().children();

    // Hide the extra items: Either the ones outside the initial count, ...
    if (Number.isInteger(initialCount)) {
      items.slice(initialCount).hide();
    }
    // ... or the ones with "illustrative" flag = 0.
    else {
      items.filter(`[data-${initialCount}='0']`).hide();
    }

    // Scroll back to the container.
    document.documentElement.scrollTop = document.body.scrollTop = container.data('scrollTop');

    container.show();
    trigger.off('click');
    trigger.on('click', function() { loadMore(container) });
    trigger.text(moreText)
  }

  Drupal.behaviors.initStaticLoadMoreBehavior = {
    attach: function (context, settings) {
      once('initStaticLoadMoreBehavior', '[data-static-load-more-container]', context).forEach(initialize);
    },
  };

})(jQuery, Drupal, once);
